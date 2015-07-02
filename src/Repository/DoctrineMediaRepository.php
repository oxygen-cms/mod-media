<?php

namespace OxygenModule\Media\Repository;

use OxygenModule\Media\Entity\Media;
use Exception;
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
     * @return Media
     */
    public function findBySlug($slug) {
        try {
            $qb = $this->getQuery(
                $this->createSelectQuery()
                ->andWhere('o.slug = :slug')
                ->setParameter('slug', $slug)
                ->setMaxResults(1),
                new QueryParameters(['excludeTrashed'])
            );
            return $qb->getSingleResult();
        } catch(Exception $e) {
            throw new NoResultException($e, $this->replaceQueryParameters($qb->getDQL(), $qb->getParameters()));
        }
    }

    /**
     * Lists all media items by the slug.
     *
     * @return array
     */

    public function listBySlug() {
        $results = $this->getQuery($this->createSelectQuery(), new QueryParameters(['excludeTrashed']))->getArrayResult();

        foreach($results as $key => $media) {
            unset($results[$key]);
            $results[$media['slug']] = $media;
        }

        return $results;
    }

}