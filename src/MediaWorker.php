<?php

namespace OxygenModule\Media;

use OxygenModule\ImportExport\WorkerInterface;
use OxygenModule\Media\Repository\MediaRepositoryInterface;
use Illuminate\Config\Repository;

class MediaWorker implements WorkerInterface {

    /**
     * Constructs the MediaWorker.
     *
     * @param MediaRepositoryInterface  $media
     * @param Repository                $config
     */

    public function __construct(MediaRepositoryInterface $media, Repository $config) {
        $this->media = $media;
        $this->config = $config;
    }

    /**
     * Returns an array of files to add to the archive.
     *
     * @param string $backupKey
     * @return mixed
     */
    public function getFiles($backupKey) {
        $media = $this->media->columns(['filename']);
        $files = [];
        $baseDir = $this->config->get('oxygen.mod-media.directory.filesystem');
        foreach($media as $item) {
            $fullFile = $baseDir . '/' . $item['filename'];
            $files[$fullFile] = '/content/media' . '/' . $item['filename'];
        }
        return $files;
    }

}