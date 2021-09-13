<?php

namespace OxygenModule\Media\Controller;

use Doctrine\ORM\NonUniqueResultException;
use Org_Heigl\Ghostscript\Ghostscript;
use Symfony\Component\Console\Output\BufferedOutput;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Str;

use SensioLabs\AnsiConverter\AnsiToHtmlConverter;
use Oxygen\Crud\Controller\BasicCrudApi;
use Oxygen\Crud\Controller\SoftDeleteCrudApi;
use Oxygen\Crud\Controller\VersionableCrudApi;
use Oxygen\Data\Exception\NoResultException;
use Oxygen\Data\Pagination\PaginationService;
use Oxygen\Data\Repository\ExcludeTrashedScope;
use Oxygen\Data\Repository\ExcludeVersionsScope;
use Oxygen\Data\Repository\OnlyTrashedScope;
use Oxygen\Data\Repository\SearchMultipleFieldsClause;
use OxygenModule\Media\Entity\Media;
use Oxygen\Data\Repository\QueryParameters;
use OxygenModule\Media\ImageVariantGenerator;
use OxygenModule\Media\ImageVariantGeneratorOutputInterface;
use OxygenModule\Media\Repository\InRootDirectoryClause;
use OxygenModule\Media\Repository\MediaDirectoryRepositoryInterface;
use OxygenModule\Media\Repository\MediaRepositoryInterface;
use Illuminate\Support\MessageBag;
use Oxygen\Core\Http\Notification;
use Oxygen\Data\Exception\InvalidEntityException;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\File\Exception\FileNotFoundException;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class MediaController extends Controller implements ImageVariantGeneratorOutputInterface {

    const PER_PAGE = 50;

    const LANG_MAPPINGS = [
        'resource' => 'Media Item',
        'pluralResource' => 'Media Items'
    ];

    /**
     * @var MediaRepositoryInterface
     */
    private $repository;

    /**
     * @var MediaDirectoryRepositoryInterface
     */
    private $directoryRepository;
    /**
     * @var ImageVariantGenerator
     */
    private $variantGenerator;
    /**
     * @var BufferedOutput
     */
    private $consoleOutput = null;

    use BasicCrudApi, SoftDeleteCrudApi, VersionableCrudApi {
        VersionableCrudApi::getListQueryParameters insteadof BasicCrudApi, SoftDeleteCrudApi;
        SoftDeleteCrudApi::deleteDeleteApi insteadof BasicCrudApi;
    }

    /**
     * Constructs the MediaController.
     *
     * @param MediaRepositoryInterface $repository
     * @param MediaDirectoryRepositoryInterface $directoryRepository
     * @param ImageVariantGenerator $variantGenerator
     */
    public function __construct(MediaRepositoryInterface $repository, MediaDirectoryRepositoryInterface $directoryRepository, ImageVariantGenerator $variantGenerator) {
        $this->repository = $repository;
        $this->directoryRepository = $directoryRepository;
        $this->variantGenerator = $variantGenerator;

        BasicCrudApi::setupLangMappings(self::LANG_MAPPINGS);
    }

    /**
     * List all entities.
     *
     * @param Request $request
     * @param PaginationService $paginationService
     * @return JsonResponse
     * @throws NonUniqueResultException
     */
    public function getListApi(Request $request, PaginationService $paginationService): JsonResponse {
        $path = $request->get('path');
        $trash = $request->get('trash') == 'true';

        if($trash) {
            $paginator = $this->repository->paginate(self::PER_PAGE,
                QueryParameters::make()
                    ->addClause(new ExcludeVersionsScope())
                    ->addClause(new OnlyTrashedScope())
                    ->orderBy('name', QueryParameters::ASCENDING)
            );
            $childDirectories = [];
        } else if ($path !== '' && $path !== null) {
            $directory = $this->directoryRepository->findByPath($path);
            $paginator = $directory->paginateChildFiles($paginationService, self::PER_PAGE, $paginationService->getCurrentPage());
            $childDirectories = $directory->getChildDirectories()->toArray();
        } else {
            $query = QueryParameters::make()
                ->addClause(new ExcludeVersionsScope())
                ->addClause(new ExcludeTrashedScope())
                ->orderBy('name', QueryParameters::ASCENDING);

            $searchQuery = $request->input('q', null);
            if($searchQuery !== null) {
                $query->addClause(new SearchMultipleFieldsClause(Media::getSearchableFields(), $searchQuery));
            } else {
                $query->addClause(new InRootDirectoryClause());
            }

            $directoryQueryParameters = QueryParameters::make();
            if($searchQuery == null) {
                $directoryQueryParameters->addClause(new InRootDirectoryClause());
            } else {
                $directoryQueryParameters->addClause(new SearchMultipleFieldsClause(['slug', 'name'], $searchQuery));
            }

            $directoryQueryParameters
                ->addClause(new ExcludeTrashedScope())
                ->orderBy('name', QueryParameters::ASCENDING);
            $paginator = $this->repository->paginate(self::PER_PAGE, $query);
            $childDirectories = $this->directoryRepository->all($directoryQueryParameters);
        }

        return response()->json([
            'currentDirectory' => isset($directory) ? $directory->toArray() : null,
            'directories' => array_map(function ($item) {
                return $item->toArray();
            }, $childDirectories),
            'files' => array_map(function ($item) {
                return $item->toArray();
            }, $paginator->items()),
            'totalFiles' => $paginator->total(),
            'filesPerPage' => $paginator->perPage(),
            'status' => Notification::SUCCESS,
        ]);
    }

    /**
     * View this image.
     *
     * @return BinaryFileResponse
     */
    public function getView($slug, $extension): ?BinaryFileResponse {
        try {
            $media = $this->repository->findByPath($slug);
            return new BinaryFileResponse($this->getMediaFilepath($media), 200, [], true);
        } catch(FileNotFoundException | NoResultException $e) {
            abort(404);
            return null;
        }
    }

    /**
     * Generates a preview of the first page of a PDF file, for use in the admin UI...
     *
     * @param Media $media
     * @return JsonResponse|BinaryFileResponse
     * @throws Exception if the PDF failed to generate an image preview
     */
    public function getPreviewImage(Media $media) {
        if($media->getType() !== Media::TYPE_DOCUMENT) {
            return response()->json([
                'content' => 'Not a document',
                'status' => Notification::FAILED
            ]);
        }

        $originalFilepath = $this->getMediaFilepath($media);
        $previewFilepath = $this->getMediaFilepath($media, '.preview.jpg');

        if(!is_readable($previewFilepath)) {
            if(!is_readable($originalFilepath)) {
                abort(404);
                return null;
            }
            // WARNING: ensure Ghostscript is kept up to date as there have been some severe
            // remote-code execution vulnerabilities in it when used on malicious PDFs.
            // Since normal uses cannot upload images to this CMS, the risk is lower, but still
            // something to keep in mind.
            $gs = new Ghostscript();
            $gs->setDevice('jpeg')
                ->setInputFile($originalFilepath)
                ->setOutputFile(basename($previewFilepath))
                ->setResolution(72)
                ->setTextAntiAliasing(Ghostscript::ANTIALIASING_HIGH);
            if(false === $gs->render()) {
                logger()->error('Ghostscript failed to render PDF preview: ' . $gs->getRenderString());
                throw new Exception('Ghostscript failed to render PDF preview: ' . $gs->getRenderString());
            }
        }

        return new BinaryFileResponse($previewFilepath, 200, [], false);
    }

    /**
     * Uploads a Media item.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function postCreateApi(Request $request): JsonResponse {
        return $this->postUpload($request);
    }

    /**
     * Process the uploaded file.
     *
     * @return JsonResponse
     */
    public function postUpload(Request $input) {
        // if no file has been uploaded
        if(!$input->hasFile('file')) {
            // guess if post_max_size has been reached
            if (empty($_FILES) && empty($_POST) && isset($_SERVER['REQUEST_METHOD']) && strtolower($_SERVER['REQUEST_METHOD']) == 'post') {
                $message = __('oxygen/crud::messages.upload.tooLarge');
            } else {
                $message = __('oxygen/crud::messages.upload.noFiles');
            }

            return notify(
                new Notification($message, Notification::FAILED)
            );
        }

        $files = $input->file('file');
        $name = $input->get('name', '')  === '' ? null : $input->get('name');
        $slug = $input->get('slug', '')  === '' ? null : $input->get('slug');
        $parentDirectoryId = $input->get('parentDirectory', null) ? intval($input->get('parentDirectory')) : null;
        $headVersion = $input->get('headVersion', '_new') === '_new' ? null : (int) $input->get('headVersion');
        $text = '';
        $success = true;

        foreach($files as $file) {
            $text .= '<strong>' . $file->getClientOriginalName() . '</strong><br>';

            $return = $this->makeFromFile($file, $name, $slug, $headVersion, $parentDirectoryId);

            $text .= implode('<br>', $return->all()) . '<br>';

            if(!$return->has('success')) {
                $success = false;
                break;
            }
        }

        $this->repository->flush();

        if($success) {
            return response()->json([
                'content' => $text,
                'status' => Notification::SUCCESS
            ]);
        } else {
            return response()->json([
                'content' => $text,
                'status' => Notification::FAILED
            ]);
        }
    }

    /**
     * Processes an uploaded image.
     *
     * @param UploadedFile $file
     * @param null|string $name
     * @param null|string $slug
     * @param mixed $headVersion
     * @param int|null $parentDirectoryId
     * @return \Illuminate\Contracts\Support\MessageBag messages
     */
    protected function makeFromFile(UploadedFile $file, $name = null, $slug = null, $headVersion = null, ?int $parentDirectoryId = null): \Illuminate\Contracts\Support\MessageBag {
        if(!$file->isValid()) {
            $messages = new MessageBag();
            return $messages->add('exists', __('oxygen/crud::messages.upload.failed', [
                'name' => $file->getClientOriginalName(),
                'error' => $file->getError()
            ]));
        }

        $validator = validator(
            ['file' => $file],
            ['file' => 'max:10000|mimes:jpeg,png,gif,webm,webp,mp3,mp4a,aif,wav,mpga,ogx,pdf']
        );

        if($validator->fails()) {
            return $validator->getMessageBag();
        }

        if($name === null) {
            $name = Str::title(str_replace(['-', '_'], ' ', explode('.', $file->getClientOriginalName())[0]));
        }
        if($slug === null) {
            $slug = strtolower(Str::slug($name));
        }
        $extension = str_replace('jpeg', 'jpg', $file->guessExtension());
        $type = Media::IMAGE_MIME_MAP[$extension]['type'];

        try {
            $media = $this->repository->make();
            $media->setName($name)
                  ->setSlug($slug)
                  ->setType($type)
                  ->setParentDirectory($parentDirectoryId)
                  ->setFilename($this->variantGenerator->hashFileAndMove($file, $extension));

            if($headVersion !== null) {
                $media->setHead($this->repository->getReference($headVersion));
            }

            $this->repository->persist($media, false);

            $messages = new MessageBag();

            return $messages->add('success', __('oxygen/crud::messages.upload.success', [
                'name' => $file->getClientOriginalName()
            ]));
        } catch(InvalidEntityException $e) {
            return $e->getErrors();
        }
    }

    /**
     * Makes multiple 'responsive' versions of the image.
     *
     * @return JsonResponse
     * @throws Exception
     */
    public function postMakeResponsive(): JsonResponse {
        $this->consoleOutput = new BufferedOutput(OutputInterface::VERBOSITY_NORMAL, true);

        $numGenerated = $this->variantGenerator->generateAllImageVariants($this);

        $converter = new AnsiToHtmlConverter();

        return response()->json([
            'content' => __('oxygen/mod-media::messages.madeResponsive', ['num' => $numGenerated]),
            'log' => $converter->convert($this->consoleOutput->fetch()),
            'status' => $numGenerated === 0 ? Notification::INFO : Notification::SUCCESS
        ]);
    }

    public function setProgressTotal(int $total) {
        // ignore
    }

    public function advanceProgress() {
        // ignore
    }

    public function writeln(string $line) {
        $this->consoleOutput->writeln($line);
    }

    public function clearProgress() {
        // ignore
    }

    private function getMediaFilepath(Media $media, $extension = null): string {
        $filename = basename($media->getFilename());
        $info = pathinfo($filename);
        if($extension !== null) {
            $filename = $info['filename'] . $extension;
        }
        return config('oxygen.mod-media.directory.filesystem') . '/' . $filename;
    }
}
