<?php


namespace OxygenModule\Media\Repository;

use Doctrine\ORM\NoResultException as DoctrineNoResultException;
use Doctrine\ORM\Query\Expr\Join;
use Oxygen\Data\Exception\NoResultException;
use Oxygen\Data\Repository\ExcludeTrashedScope;
use Oxygen\Data\Repository\RepositoryInterface;
use OxygenModule\Media\Entity\Media;
use OxygenModule\Media\Entity\MediaDirectory;

interface MediaDirectoryRepositoryInterface extends RepositoryInterface {



}
