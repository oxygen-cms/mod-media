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

    const RESPONSIVE_SIZES = [320, 640, 960, 1280, null];
    const FALLBACK_MODE = 'fallback';
    const PRIMARY_FALLBACK_FORMAT = 'jpg';
    const FALLBACK_FORMATS = ['image/jpeg', 'image/png'];
    const IMAGE_FORMATS = [null, 'webp', self::FALLBACK_MODE];

    /**
     * @var MediaRepositoryInterface
     */
    private MediaRepositoryInterface $mediaRepository;

    public function __construct(MediaRepositoryInterface $mediaRepository) {
        $this->mediaRepository = $mediaRepository;
    }

    /**
     * Moves the file to an appropriate location, according to the hash of its contents.
     *
     * @param File $file
     * @param $extension
     * @return string
     */
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
     * @param int|null $width width to resize to (if any)
     * @param string|null $format format to convert to
     * @throws Exception
     */
    protected function generateVariant(Media $media, ?int $width, ?string $format) {
        if($media->getType() !== Media::TYPE_IMAGE) {
            throw new Exception('Media item is not an image');
        }
        $image = ImageFacade::make(config('oxygen.mod-media.directory.filesystem') . '/' . basename($media->getFilename()));
        if($width !== null) {
            $image->resize($width, null, function($constraint) {
                $constraint->aspectRatio();
                $constraint->upsize();
            });
        }
        $tmpFilename = config('oxygen.mod-media.directory.filesystem') . '/' . basename($media->getFilename()) . '.' . $width . '.tmp.' . basename($format ?: $media->getExtension());
        $image->save($tmpFilename, null, $format);
        $variantFilename = $this->hashFileAndMove(new File($tmpFilename), $format ?: $media->getExtension());
        $format = $format ?: $media->getExtension();
        $mimeFormat = Media::IMAGE_MIME_MAP[$format]['mime'];
        $media->addVariant($variantFilename, $width, $mimeFormat);
    }

    /**
     * Generates variants for a given media item, if required...
     *
     * @throws Exception
     */
    public function generateImageVariants(Media $media) {
        if($media->getType() !== Media::TYPE_IMAGE) {
            throw new \Exception('media item is not an image');
        }

        $generated = false;
        foreach(self::RESPONSIVE_SIZES as $size) {
            foreach(self::IMAGE_FORMATS as $format) {
                if($format === self::FALLBACK_MODE) {
                    // ensure that we have an appropriate fallback image format (jpeg, png) to use just in case...
                    $hasFallback = false;
                    foreach(self::FALLBACK_FORMATS as $fallbackFormat) {
                        if ($media->hasVariant($size, $fallbackFormat)) {
                            $hasFallback = true;
                        }
                    }
                    if(!$hasFallback) {
                        $this->generateVariant($media, $size, self::PRIMARY_FALLBACK_FORMAT);
                    }
                } else if(!$media->hasVariant($size, $format === null ? null : Media::IMAGE_MIME_MAP[$format]['mime'])) {
                    $this->generateVariant($media, $size, $format);
                    $generated = true;
                }
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
