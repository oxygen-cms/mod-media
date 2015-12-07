<?php

namespace OxygenModule\Media\Controller;

use Config;
use Exception;
use Illuminate\Http\Request;
use Input;
use Lang;
use Response;
use Illuminate\Support\Str;
use Validator;
use View;

use Intervention\Image\Image;
use OxygenModule\Media\MediaFieldSet;
use OxygenModule\Media\Entity\Media;
use OxygenModule\Media\MacroProcessor;
use Oxygen\Data\Repository\QueryParameters;
use OxygenModule\Media\Repository\MediaRepositoryInterface;
use Illuminate\Support\MessageBag;
use Oxygen\Core\Blueprint\BlueprintManager;
use Oxygen\Core\Http\Notification;
use Oxygen\Crud\Controller\VersionableCrudController;
use Oxygen\Data\Exception\InvalidEntityException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Intervention\Image\Facades\Image as ImageFacade;

class MediaController extends VersionableCrudController {

    /**
     * Constructs the PagesController.
     *
     * @param MediaRepositoryInterface          $repository
     * @param BlueprintManager                  $manager
     * @param \OxygenModule\Media\MediaFieldSet $fields
     * @throws \Oxygen\Core\Blueprint\BlueprintNotFoundException
     */

    public function __construct(MediaRepositoryInterface $repository, BlueprintManager $manager, MediaFieldSet $fields) {
        parent::__construct($repository, $manager->get('Media'), $fields);
    }

    /**
     * List all entities.
     *
     * @param \Oxygen\Data\Repository\QueryParameters $queryParameters
     * @return \Illuminate\Http\Response
     */
    public function getList($queryParameters = null) {
        $items = $this->repository->paginate(25, $queryParameters == null ? new QueryParameters(['excludeTrashed', 'excludeVersions'], 'id', QueryParameters::DESCENDING) : $queryParameters);

        // render the view
        return View::make('oxygen/mod-media::list', [
            'items' => $items,
            'isTrash' => false,
            'fields' => $this->crudFields,
            'title' => Lang::get('oxygen/crud::ui.resource.list')
        ]);
    }

    /**
     * Show the upload form.
     *
     * @return \Illuminate\Http\Response
     */
    public function getView($slug) {
        $media = $this->repository->findBySlug($slug);

        return new BinaryFileResponse(Config::get('oxygen.mod-media.directory.filesystem') . '/' . $media->getFilename(), 200, [], true);
    }

    /**
     * Returns the raw resource.
     *
     * @param          $item
     * @param array    $input
     * @param callable $respond
     * @throws \Exception
     * @return \Illuminate\Http\Response
     */
    public function getRaw($item, array $input = null, $respond = null) {
        $item = $this->getItem($item);
        $filename = Config::get('oxygen.mod-media.directory.filesystem') . '/' . $item->getFilename();

        if($input === null) {
            $input = Input::all();
        }

        if($respond === null) {
            $respond = function($image, $newVersion, $oldVersion) {
                return $image->response();
            };
        }

        if($item->getType() === Media::TYPE_IMAGE && count($input) > 0) {
            if(isset($input['save']) && $input['save'] === 'true') {
                $oldVersion = $this->repository->makeNewVersion($item, false);
                $item->makeNewFilename();
            }

            $macro = $input;
            unset($macro['save'], $macro['name'], $macro['slug']);

            $macroProcessor = new MacroProcessor($macro);
            $image = $macroProcessor->process(ImageFacade::make($filename));

            if(isset($input['save']) && $input['save'] === 'true') {
                $image->save(Config::get('oxygen.mod-media.directory.filesystem') . '/' . $item->getFilename());

                $name = $input['name'];
                $slug = $input['slug'];
                $item->setName(is_callable($name) ? $name($image) : $name);
                $item->setSlug(is_callable($slug) ? $slug($image) : $slug);
                $this->repository->persist($item, 'overwrite');
            }

            return $respond($image, $item, isset($oldVersion) ? $oldVersion : null);
        } else {
            return new BinaryFileResponse($filename);
        }
    }

    /**
     * Shows the update form.
     *
     * @param mixed $item the item
     * @return \Illuminate\Http\Response
     */
    public function getUpdate($item) {
        $item = $this->getItem($item);

        return View::make('oxygen/mod-media::update', [
            'item' => $item,
            'fields' => $this->crudFields,
            'title' => Lang::get('oxygen/crud::ui.resource.update')
        ]);
    }

    /**
     * Displays the image editor.
     *
     * @param mixed $item
     * @return \Illuminate\Http\Response
     */
    public function getEditImage($item) {
        $item = $this->getItem($item);

        if($item->getType() !== Media::TYPE_IMAGE) {
            return Response::notification(new Notification(Lang::get('oxygen/mod-media::messages.onlyAbleToEditImages'), Notification::FAILED));
        }

        return View::make('oxygen/mod-media::editImage', [
            'item' => $item,
            'fields' => $this->crudFields,
            'title' => Lang::get('oxygen/mod-media::ui.editImage.title')
        ]);
    }

    /**
     * Show the upload form.
     *
     * @return \Illuminate\Http\Response
     */
    public function getUpload() {
        return View::make('oxygen/mod-media::upload', [
            'fields' => $this->crudFields,
            'media' => $this->repository->listKeysAndValues('id', 'name', new QueryParameters(['excludeVersions'])),
            'title' => Lang::get('oxygen/mod-media::ui.upload.title')
        ]);
    }

    /**
     * Process the uploaded file.
     *
     * @return \Illuminate\Http\Response
     */
    public function postUpload(Request $input) {
        // if no file has been uploaded
        if(!$input->hasFile('file')) {
            // guess if post_max_size has been reached
            if (empty($_FILES) && empty($_POST) && isset($_SERVER['REQUEST_METHOD']) && strtolower($_SERVER['REQUEST_METHOD']) == 'post') {
                $message = Lang::get('oxygen/mod-media::messages.upload.tooLarge');
            } else {
                $message = Lang::get('oxygen/mod-media::messages.upload.noFiles');
            }

            return Response::notification(
                new Notification($message, Notification::FAILED)
            );
        }

        $files = $input->file('file');
        $name = $input->get('name', '')  === '' ? null : $input->get('name');
        $slug = $input->get('slug', '')  === '' ? null : $input->get('slug');
        $headVersion = $input->get('headVersion', '_new') === '_new' ? null : (int) $input->get('headVersion');
        $text = '';
        $success = true;

        foreach($files as $file) {
            $text .= '<strong>' . $file->getClientOriginalName() . '</strong><br>';

            $return = $this->makeFromFile($file, $name, $slug, $headVersion);

            if(!$return->has('success')) {
                $success = false;
            }

            $text .= implode('<br>', $return->all()) . '<br>';
        }

        $this->repository->flush();

        if($success) {
            return Response::notification(
                new Notification($text),
                ['redirect' => $this->blueprint->getRouteName('getList')]
            );
        } else {
            return Response::notification(
                new Notification($text, Notification::FAILED)
            );
        }
    }

    /**
     * Processes an uploaded image.
     *
     * @param UploadedFile $file
     * @param string $name
     * @param string $slug
     * @param string $headVersion
     * @return MessageBag messages
     */
    protected function makeFromFile(UploadedFile $file, $name = null, $slug = null, $headVersion = null) {
        if(!$file->isValid()) {
            $messages = new MessageBag();
            return $messages->add('exists', Lang::get('oxygen/mod-media::messages.upload.failed', [
                'name' => $file->getClientOriginalName(),
                'error' => $file->getError()
            ]));
        }

        $validator = Validator::make(
            ['file' => $file],
            ['file' => 'max:10000|mimes:jpeg,png,gif,mp3,mp4a,aif,wav,mpga,ogx,pdf']
        );

        if($validator->fails()) {
            return $validator->messages();
        }

        if($name === null) {
            $name = Str::title(str_replace(['-', '_'], ' ', explode('.', $file->getClientOriginalName())[0]));
        }
        if($slug === null) {
            $slug = Str::slug($name);
        }
        $extension = str_replace('jpeg', 'jpg', $file->guessExtension());
        $type = Media::TYPE_DOCUMENT;

        switch($file->guessExtension()) {
            case 'jpeg':
            case 'png':
            case 'gif':
                $type = Media::TYPE_IMAGE;
                break;
            case 'mp3':
            case 'mp4a':
            case 'ogx':
            case 'mpga':
            case 'aif':
            case 'wav':
                $type = Media::TYPE_AUDIO;
                break;
        }

        try {
            $media = $this->repository->make();
            $media->setName($name)
                  ->setSlug($slug)
                  ->setType($type)
                  ->makeNewFilename($extension);

            if($headVersion !== null) {
                $media->setHead($this->repository->getReference($headVersion));
            }

            $this->repository->persist($media, false);

            $file->move(Config::get('oxygen.mod-media.directory.filesystem'), $media->getFilename());

            $messages = new MessageBag();
            return $messages->add('success', Lang::get('oxygen/mod-media::messages.upload.success', [
                'name' => $file->getClientOriginalName()
            ]));
        } catch(InvalidEntityException $e) {
            return $e->getErrors();
        }
    }

    /**
     * Makes a resized version of the image.
     *
     * @param Media $image
     * @param string $size
     * @param string $name
     * @return Media
     */
    protected function resizeImage(Media $image, $size, $name) {
        return $this->getRaw($image, [
            'resize' => ['width' => $size, 'keepAspectRatio' => true, 'preventUpsize' => true],
            'save' => 'true',
            'name' => $image->getName() . ' (' . $name . ')',
            'slug' => function(Image $intervention) use($image) {
                return $image->getSlug() . '/' . $intervention->getWidth();
            }
        ], function(Image $image, $newVersion, $oldVersion) {
            return $oldVersion;
        });
    }

    /**
     * Makes multiple 'responsive' versions of the image.
     *
     * @param mixed $item
     * @return \Illuminate\Http\Response
     */
    public function postMakeResponsive($item) {
        $original = $this->getItem($item);

        $original = $this->resizeImage($original, 320, 'Small');
        $original = $this->resizeImage($original, 640, 'Medium');
        $original = $this->resizeImage($original, 1280, 'Large');

        $this->repository->makeHeadVersion($original);

        return Response::notification(new Notification(Lang::get('oxygen/mod-media::messages.madeResponsive')), ['refresh' => true]);
    }

}
