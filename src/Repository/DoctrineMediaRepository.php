<?php

namespace OxygenModule\Media\Repository;

use Doctrine\ORM\NoResultException as DoctrineNoResultException;
use OxygenModule\Media\Entity\Media;
use Oxygen\Data\Exception\NoResultException;
use Oxygen\Data\Repository\Doctrine\Repository;
use Oxygen\Data\Repository\Doctrine\SoftDeletes;
use Oxygen\Data\Repository\Doctrine\Versions;
use Oxygen\Data\Repository\QueryParameters;

class DoctrineMediaRepository extends Repository implements MediaRepositoryInterface {

    use SoftDeletes, Versions;

    /**
     * The name of the entity.
     *
     * @var string
     */
    protected $entityName = Media::class;

    /**
     * Finds an Media item based upon the slug.
     *
     * @param string $slug
     * @throws NoResultException
     * @return Media
     */
    public function findBySlug($slug) {
        $q = $this->getQuery(
            $this->createSelectQuery()
                 ->andWhere('o.slug = :slug')
                 ->setParameter('slug', $slug)
                 ->orderBy('o.headVersion', 'ASC')
                 ->addOrderBy('o.updatedAt', 'DESC')
                 ->setMaxResults(1),
            new QueryParameters(['excludeTrashed'])
        );
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
        $results = $this->getQuery($this->createSelectQuery()->orderBy('o.headVersion', 'ASC')->addOrderBy('o.updatedAt', 'DESC'), new QueryParameters(['excludeTrashed']))->getResult();

        $sluggedResults = [];

        foreach(array_reverse($results) as $media) {
            $sluggedResults[$media->getSlug()] = $media;
        }

        return $sluggedResults;
    }

}