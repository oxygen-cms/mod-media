<?php

namespace OxygenModule\Media;

use Exception;
use Illuminate\Support\Facades\Log;
use Illuminate\Filesystem\Filesystem;
use OxygenModule\ImportExport\WorkerInterface;
use OxygenModule\Media\Repository\MediaRepositoryInterface;
use Illuminate\Config\Repository;
use Illuminate\Support\Str;
use RecursiveIteratorIterator;
use Symfony\Component\Console\Output\OutputInterface;

class MediaWorker implements WorkerInterface {

    protected $files;
    /**
     * @var Repository
     */
    private $config;
    /**
     * @var MediaRepositoryInterface
     */
    private $media;

    /**
     * Constructs the MediaWorker.
     *
     * @param Filesystem $files
     * @param Repository                        $config
     */

    public function __construct(MediaRepositoryInterface $media, Filesystem $files, Repository $config) {
        $this->media = $media;
        $this->config = $config;
        $this->files = $files;
    }

    /**
     * Adds the media items to the backup.
     *
     * @param OutputInterface $output
     * @return array
     * @throws Exception
     */
    public function export(OutputInterface $output): array {
        $media = $this->media->columns(['filename']);

        $baseDir = $this->config->get('oxygen.mod-media.directory.filesystem');
        $files = [];
        foreach($media as $item) {
            $fullFile = $baseDir . '/' . $item['filename'];

            if($this->files->exists($fullFile)) {
                Log::warning($fullFile . ' was referenced by a media item but does not exist in the filesystem');
                $files[basename($fullFile)] = $fullFile;
            }
        }
        return $files;
    }

    /**
     * Cleans up any temporary files that were created after they have been added to the ZIP archive.
     *
     * @param OutputInterface $output
     * @return void
     */
    public function postExport(OutputInterface $output) {
        // no temporary files created
    }

    /**
     * Imports the media items.
     *
     * @param RecursiveIteratorIterator $files
     * @param OutputInterface $output
     */
    public function import(RecursiveIteratorIterator $files, OutputInterface $output) {
        $mediaPath = $this->config->get('oxygen.mod-media.directory.filesystem');

        foreach($files as $file) {
            $path = $file->getPathname();
            $search = basename($mediaPath);

            if (
                $file->isFile() &&
                Str::contains($path, $search) &&
                in_array($file->getExtension(), ['jpeg','png','gif','mp3','mp4a','aif','wav','mpga','ogx','pdf'])
            ) {
                $output->writeln('Importing media file from ' . $path);

                $newPath = $mediaPath . '/' . basename($path);

                if($this->files->exists($newPath)) {
                    $output->writeln('WARNING: Overwriting ' . $newPath);
                }

                $this->files->move($file->getPathname(), $newPath);
            }
        }
    }
}
