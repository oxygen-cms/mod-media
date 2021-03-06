<?php

namespace OxygenModule\Media\Repository;

use Doctrine\ORM\NoResultException as DoctrineNoResultException;
use Doctrine\ORM\Query\Expr\Join;
use Oxygen\Data\Repository\ExcludeTrashedScope;
use Oxygen\Data\Repository\ExcludeVersionsScope;
use OxygenModule\Media\Entity\Media;
use Oxygen\Data\Exception\NoResultException;
use Oxygen\Data\Repository\Doctrine\Repository;
use Oxygen\Data\Repository\Doctrine\Versions;
use Oxygen\Data\Repository\QueryParameters;
use OxygenModule\Media\Entity\MediaDirectory;

class DoctrineMediaRepository extends Repository implements MediaRepositoryInterface {

    use Versions;

    /**
     * The name of the entity.
     *
     * @var string
     */
    protected $entityName = Media::class;

    /**
     * Finds an Media item based upon the path.
     *
     * @param string $path
     * @return Media
     * @throws \Doctrine\ORM\NonUniqueResultException
     * @throws NoResultException
     */
    public function findByPath(string $path): Media {
        $pathParts = explode('/', $path);
        $finalPart = array_pop($pathParts);

        $qb = $this->entities
            ->createQueryBuilder()
            ->select('d0')
            ->from($this->entityName, 'd0')
            ->andWhere('d0.slug = :name0')
            ->setParameter('name0', $finalPart);

        $i = 1;
        while($nextPart = array_pop($pathParts)) {
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
        (new ExcludeVersionsScope())->apply($qb, 'd0');

        $q = $qb->getQuery();
        try {
            return $q->getSingleResult();
        } catch(DoctrineNoResultException $e) {
            throw $this->makeNoResultException($e, $q);
        }
    }

    /**
     * Lists all media items by the slug.
     *
     * @return array
     */
    public function listBySlug() {
        $results = $this->getQuery($this->createSelectQuery()->orderBy('o.headVersion', 'ASC')->addOrderBy('o.updatedAt', 'DESC'), new QueryParameters([new ExcludeTrashedScope()]))->getResult();

        $sluggedResults = [];

        foreach(array_reverse($results) as $media) {
            $sluggedResults[$media->getSlug()] = $media;
        }

        return $sluggedResults;
    }

}
