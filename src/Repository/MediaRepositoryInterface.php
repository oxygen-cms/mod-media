<?php

namespace OxygenModule\Media\Repository;

use OxygenModule\Media\Entity\Media;
use Oxygen\Data\Repository\RepositoryInterface;

interface MediaRepositoryInterface extends RepositoryInterface {

    /**
     * Finds an Media item based upon the slug.
     *
     * @param string $path
     * @return Media
     */
    public function findByPath(string $path): Media;

    /**
     * Lists all media items by the slug.
     *
     * @return array
     */
    public function listBySlug();

}
