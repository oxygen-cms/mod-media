<?php


namespace OxygenModule\Media\Repository;

use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException as DoctrineNoResultException;
use Doctrine\ORM\Query\Expr\Join;
use Oxygen\Data\Exception\NoResultException;
use Oxygen\Data\Repository\Doctrine\Repository;
use Oxygen\Data\Repository\ExcludeTrashedScope;
use OxygenModule\Media\Entity\MediaDirectory;

class DoctrineMediaDirectoryRepository extends Repository implements MediaDirectoryRepositoryInterface {

    /**
     * The name of the entity.
     *
     * @var string
     */
    protected $entityName = MediaDirectory::class;

    /**
     * Finds a directory based upon the path.
     *
     * @param string $path
     * @return MediaDirectory
     * @throws NonUniqueResultException
     * @throws NoResultException
     */
    public function findByPath(string $path): MediaDirectory {
        $slugParts = explode('/', $path);
        $finalPart = array_pop($slugParts);

        $qb = $this->entities
            ->createQueryBuilder()
            ->select('d0')
            ->from($this->entityName, 'd0')
            ->andWhere('d0.slug = :name0')
            ->setParameter('name0', $finalPart);

        $i = 1;
        while($nextPart = array_pop($slugParts)) {
            $prevI = $i-1;
            $qb = $qb->innerJoin(MediaDirectory::class, "d$i", Join::WITH, "d$prevI.parentDirectory = d$i.id")
                ->andWhere("d$i.slug = :name$i")
                ->andWhere($qb->expr()->orX("d$i.deletedAt is NULL", "d$i.deletedAt > :currentTimestamp"))
                ->setParameter("name$i", $nextPart);
            $i++;
        }
        $prevI = $i-1;
        $qb->andWhere("d$prevI.parentDirectory is NULL");

        (new ExcludeTrashedScope())->apply($qb, 'd0');

        $q = $qb->getQuery();
        try {
            return $q->getSingleResult();
        } catch(DoctrineNoResultException $e) {
            throw $this->makeNoResultException($e, $q);
        }
    }

}
