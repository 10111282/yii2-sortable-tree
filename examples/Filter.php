<?php

use yii\db\Query;

class Filter implements \serj\sortableTree\FilterInterface
{
    /**
     * @inheritdoc
     */
    public function applyFilter(Query $query)
    {
        $query->andWhere([
            'deleted' => false
        ]);

        return $query;
    }
}