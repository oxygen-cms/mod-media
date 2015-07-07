<?php

namespace OxygenModule\Media;

use Illuminate\Filesystem\Filesystem;
use OxygenModule\ImportExport\WorkerInterface;
use OxygenModule\Media\Repository\MediaRepositoryInterface;
use Illuminate\Config\Repository;
use ZipArchive;

class MediaWorker implements WorkerInterface {

    protected $prefix = 'content/media/';

    protected $files;

    /**
     * Constructs the MediaWorker.
     *
     * @param MediaRepositoryInterface          $media
     * @param \Illuminate\Filesystem\Filesystem $files
     * @param Repository                        $config
     */

    public function __construct(MediaRepositoryInterface $media, Filesystem $files, Repository $config) {
        $this->media = $media;
        $this->config = $config;
        $this->files = $files;
    }

    /**
     * Returns an array of files to add to the archive.
     *
     * @param string $backupKey
     * @return mixed
     */
    public function export($backupKey) {
        $media = $this->media->columns(['filename']);
        $files = [];
        $baseDir = $this->config->get('oxygen.mod-media.directory.filesystem');
        foreach($media as $item) {
            $fullFile = $baseDir . '/' . $item['filename'];
            $files[$fullFile] = $this->prefix . $item['filename'];
        }
        return $files;
    }

    /**
     * Cleans up any temporary files that were created after they have been added to the ZIP archive.
     *
     * @param string $backupKey
     * @return void
     */
    public function postExport($backupKey) {
        // no temporary files created
    }

    /**
     * Cleans up any temporary files that were created after they have been added to the ZIP archive.
     *
     * @param \ZipArchive $zip
     */
    public function import(ZipArchive $zip) {
        $mediaFiles = [];
        $mediaDir = null;

        $regex = '/^([a-zA-Z0-9\-]+)\/' . preg_quote($this->prefix, '/') . '/';

        for($i = 0; $i < $zip->numFiles; $i++) {
            $filename = $zip->getNameIndex($i);

            $matches = [];
            if(preg_match($regex, $filename, $matches)) {
                $mediaFiles[] = $filename;

                if($mediaDir == null) {
                    $mediaDir = $matches[1];
                }

            }
        }

        $path = $this->config->get('oxygen.mod-media.directory.filesystem');
        $this->files->cleanDirectory($path);
        $zip->extractTo($path, $mediaFiles);

        // move `public/content/media/local-56-30-12/content/media` to `public/content/media`
        $this->files->copyDirectory($path . '/' . $mediaDir . '/' . $this->prefix, $path);
        $this->files->deleteDirectory($path . '/' . $mediaDir);
    }
}