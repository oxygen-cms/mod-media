<?php

namespace OxygenModule\Media;

use Log;
use Illuminate\Filesystem\Filesystem;
use OxygenModule\ImportExport\WorkerInterface;
use OxygenModule\Media\Repository\MediaRepositoryInterface;
use Illuminate\Config\Repository;
use OxygenModule\ImportExport\Strategy\ExportStrategy;
use OxygenModule\ImportExport\Strategy\ImportStrategy;

class MediaWorker implements WorkerInterface {

    protected $files;

    /**
     * Constructs the MediaWorker.
     *
     * @param \Illuminate\Filesystem\Filesystem $files
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
     * @param ExportStrategy $strategy
     * @return void
     */
    public function export(ExportStrategy $strategy) {
        $media = $this->media->columns(['filename']);

        $baseDir = $this->config->get('oxygen.mod-media.directory.filesystem');
        foreach($media as $item) {
            $fullFile = $baseDir . '/' . $item['filename'];

            if($this->files->exists($fullFile)) {
                Log::warning($fullFile . ' was referenced by a media item but does not exist in the filesystem');
                $strategy->addFile($fullFile, dirname($baseDir));
            }
        }
    }

    /**
     * Cleans up any temporary files that were created after they have been added to the ZIP archive.
     *
     * @param ExportStrategy $strategy
     * @return void
     */
    public function postExport(ExportStrategy $strategy) {
        // no temporary files created
    }

    /**
     * Imports the media items.
     *
     * @param ImportStrategy $strategy
     */
    public function import(ImportStrategy $strategy) {
        $files = $strategy->getFiles();
        $mediaPath = $this->config->get('oxygen.mod-media.directory.filesystem');

        foreach($files as $file) {
            $path = $file->getPathname();
            $search = basename($mediaPath);

            if (
                $file->isFile() &&
                str_contains($path, $search) &&
                in_array($file->getExtension(), ['jpeg','png','gif','mp3','mp4a','aif','wav','mpga','ogx','pdf'])
            ) {
                if(app()->runningInConsole()) {
                    echo 'Importing media file from ' . $path . "\n";
                }

                $newPath = $mediaPath . '/' . basename($path);

                if($this->files->exists($newPath)) {
                    echo 'WARNING: Overwriting ' . $newPath . "\n";
                }

                $this->files->move($file->getPathname(), $newPath);
            }
        }
    }
}