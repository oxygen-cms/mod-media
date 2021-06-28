<?php


namespace OxygenModule\Media;

use Exception;

use Intervention\Image\Image;
use Intervention\Image\Facades\Image as ImageFacade;
use Oxygen\Data\Repository\ExcludeTrashedScope;
use Oxygen\Data\Repository\ExcludeVersionsScope;
use Oxygen\Data\Repository\QueryParameters;
use OxygenModule\Media\Entity\Media;
use OxygenModule\Media\Repository\MediaRepositoryInterface;
use Symfony\Component\HttpFoundation\File\File;

class ImageVariantGenerator {

    const RESPONSIVE_SIZES = [320, 640, 960, 1280];
    const MIN_RESPONSIVE_WIDTH = 400;

    /**
     * @var MediaRepositoryInterface
     */
    private $mediaRepository;

    public function __construct(MediaRepositoryInterface $mediaRepository) {
        $this->mediaRepository = $mediaRepository;
    }

    public function hashFileAndMove(File $file, $extension): string {
        // this way we also deduplicate files
        $fileHash = hash_file('sha256', $file->getRealPath(), false);
        $filename = $fileHash . '.' . $extension;
        $file->move(config('oxygen.mod-media.directory.filesystem'), $filename);
        return $filename;
    }

    /**
     * Makes a resized version of the image.
     *
     * @param Media $media
     * @param int $width
     * @throws Exception
     */
    protected function resizeImage(Media $media, int $width) {
        if($media->getType() !== Media::TYPE_IMAGE) {
            throw new Exception('Media item is not an image');
        }
        $image = ImageFacade::make(config('oxygen.mod-media.directory.filesystem') . '/' . $media->getFilename());
        $image->resize($width, null, function($constraint) {
            $constraint->aspectRatio();
            $constraint->upsize();
        });
        $tmpFilename = config('oxygen.mod-media.directory.filesystem') . '/' . basename($media->getFilename()) . '.' . $width . '.tmp.' . basename($media->getExtension());
        $image->save($tmpFilename);
        $variantFilename = $this->hashFileAndMove(new File($tmpFilename), $media->getExtension());
        $media->addVariant($variantFilename, $width);
    }

    /**
     * @throws Exception
     */
    public function generateImageVariants(Media $media) {
        $generated = false;
        foreach(self::RESPONSIVE_SIZES as $size) {
            if(!$media->hasVariant($size)) {
                $this->resizeImage($media, $size);
                $generated = true;
            }
        }
        $this->mediaRepository->persist($media, $generated);
    }

    /**
     * @throws Exception
     */
    public function generateAllImageVariants(ImageVariantGeneratorOutputInterface $output): int {
        $generated = 0;
        $missing = 0;
        $skipped = 0;

        $all = $this->mediaRepository->all(new QueryParameters([new ExcludeVersionsScope(), new ExcludeTrashedScope()]));

        $output->setProgressTotal(count($all));
        foreach($all as $media) {
            $output->advanceProgress();
            if($media->getType() !== Media::TYPE_IMAGE) {
                $output->writeln('<fg=yellow>Skipping</> ' . $media->getFullPath() . ' - type=' . $media->getTypeAsString());
                $skipped++;
                continue;
            }

            $filepath = config('oxygen.mod-media.directory.filesystem') . '/' . $media->getFilename();

            if(!file_exists($filepath)) {
                $output->writeln('<fg=red>Error</> ' . $media->getFullPath() . ' - original does not exist');
                $missing++;
                continue;
            }

            $output->writeln('<fg=green>Generating</> variants for ' . $media->getFullPath());

            $image = ImageFacade::make($filepath);
            if($image->width() < self::MIN_RESPONSIVE_WIDTH) {
                $output->writeln('<fg=yellow>Skipping</> width too small');
                $skipped++;
                continue;
            }

            $prevCount = count($media->getVariants());
            $this->generateImageVariants($media);
            $generatedVariants = count($media->getVariants()) - $prevCount;

            $output->writeln('<fg=green>Generated</> ' . $generatedVariants . ' variants for ' . $media->getFullPath());

            $generated += $generatedVariants;
        }

        $this->mediaRepository->flush();

        $output->clearProgress();
        $output->writeln('<fg=green>Summary:</> skipped items: ' . $skipped . ', generated variants: ' . $generated . ', missing files: ' . $missing);

        return $generated;
    }

}
