<?php

use Doctrine\ORM\EntityManagerInterface;
use Illuminate\Database\Migrations\Migration;
use Oxygen\Data\Repository\QueryParameters;
use OxygenModule\Media\Entity\MediaDirectory;
use OxygenModule\Media\Repository\MediaDirectoryRepositoryInterface;
use OxygenModule\Media\Repository\MediaRepositoryInterface;

class FolderCreationInfo {

    public const NOT_SET = 'not_set';
    public $name;
    public $parent;
    public $children;
    public $entity = self::NOT_SET;

    public function __construct($current) {
        $parts = explode('/', $current);
        $this->name = array_pop($parts);
        $this->parent = implode('/', $parts);
        if($this->parent == '') {
            $this->parent = null;
        }
        $this->children = [];
    }
}

class AddDirectoriesToMedia extends Migration {

    /**
     * Adds MediaDirectory items to represent directories in the media item stack.
     * Assigns media items to those particular directories.
     */
    public function up() {
        $media = app(MediaRepositoryInterface::class);
        $directories = app(MediaDirectoryRepositoryInterface::class);

        $mediaItems = $media->all(QueryParameters::make()->excludeVersions());
        $folders = [];
        foreach($mediaItems as $item) {
            $slugParts = explode('/', $item->getSlug());
            // get rid of filename
            array_pop($slugParts);
            $dirFull = implode('/', $slugParts);

            if(!isset($folders[$dirFull])) {
                $folders[$dirFull] = new FolderCreationInfo($dirFull);
            }
            $folders[$dirFull]->children[] = $item;
            while(array_pop($slugParts)) {
                $dirCurrent = implode('/', $slugParts);

                if(!isset($folders[$dirCurrent])) {
                    $folders[$dirCurrent] = new FolderCreationInfo($dirCurrent);
                }
            }
        }

        $keys = array_keys($folders);
        sort($keys);
        dump($keys);

        while($folder = $this->getNextDirToAdd($folders)) {
            $mediaDirectory = null;
            $fullPath = null;
            if($folder->name != '') {
                $mediaDirectory = new MediaDirectory();
                $mediaDirectory->setSlug($folder->name);
                $mediaDirectory->setParentDirectory($folder->parent);
                $directories->persist($mediaDirectory, false);
                $fullPath = ltrim($mediaDirectory->getFullPath(), '/');
            }

            echo 'Migrating ' . $fullPath . "\n";

            foreach($folders as $innerFolder) {
                if($innerFolder->parent === $fullPath) {
                    $innerFolder->parent = $mediaDirectory;
                }
            }

            foreach($folder->children as $child) {
                $child->setDirectory($mediaDirectory);
                $slugParts = explode('/', $child->getSlug());
                $newSlug = array_pop($slugParts);
                $child->setSlug($newSlug);
                $media->persist($child, false);
            }

            $folder->entity = $mediaDirectory;
        }

//        dump(array_filter($folders, function($folder) {
//            return $folder->entity === null;
//        }));

        app(EntityManagerInterface::class)->flush();
    }

    /**
     * @param $folders
     * @return FolderCreationInfo|null
     */
    private function getNextDirToAdd($folders): ?FolderCreationInfo {
        foreach($folders as $folder) {
            if($folder->entity === FolderCreationInfo::NOT_SET && ($folder->parent instanceof MediaDirectory || $folder->parent == null)) {
                return $folder;
            }
        }
        return null;
    }

    /**
     * Reverse the migrations.
     */
    public function down() {
        $em = app(EntityManagerInterface::class);
        $media = app(MediaRepositoryInterface::class);

        foreach($media->all(QueryParameters::make()->excludeVersions()) as $item) {
            echo 'Setting ' . $item->getSlug() . ' to ' . $item->getFullPath() . "\n";
            $item->setSlug($item->getFullPath());
            $item->setDirectory(null);

            $media->persist($item, false);
        }
        app(EntityManagerInterface::class)->flush();

        // truncate the `media_directories` table disabling foreign key checks
        $conn = $em->getConnection();
        $conn->executeQuery('SET FOREIGN_KEY_CHECKS = 0;');
        $truncateSql = $conn->getDatabasePlatform()->getTruncateTableSQL('media_directories');
        $conn->executeUpdate($truncateSql);
        $conn->executeQuery('SET FOREIGN_KEY_CHECKS = 1;');
    }
}
