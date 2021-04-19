<?php


namespace OxygenModule\Media\Repository;


use Doctrine\ORM\QueryBuilder;
use Oxygen\Data\Repository\QueryClauseInterface;

class InRootDirectoryClause implements QueryClauseInterface {

    /**
     * InRootDirectoryClause constructor.
     */
    public function __construct() {
    }

    public function apply(QueryBuilder $qb, string $alias): QueryBuilder {
        return $qb->andWhere('o.parentDirectory is NULL');
    }
}
