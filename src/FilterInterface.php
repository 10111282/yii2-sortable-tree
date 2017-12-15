<?php

namespace serj\sortableTree;

use yii\db\Query;


interface FilterInterface
{
    /**
     * Applies filter to query object.
     * It can be any possible modifications of the Query object.
     * E.g. $query->andWhere(...)
     *
     * @param Query $query
     */
    public function applyFilter(Query $query);

}