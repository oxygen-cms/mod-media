<?php


namespace OxygenModule\Media\Repository;

use Doctrine\ORM\NonUniqueResultException;
use Oxygen\Data\Exception\NoResultException;
use Oxygen\Data\Repository\RepositoryInterface;
use OxygenModule\Media\Entity\MediaDirectory;

interface MediaDirectoryRepositoryInterface extends RepositoryInterface {

    /**
     * Finds a directory based upon the path.
     *
     * @param string $path
     * @return MediaDirectory
     * @throws NonUniqueResultException
     * @throws NoResultException
     */
    public function findByPath(string $path): MediaDirectory;

}
