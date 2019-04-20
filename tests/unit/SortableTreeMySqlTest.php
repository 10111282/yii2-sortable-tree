<?php

use serj\sortableTree\Tree;
use serj\sortableTree\TreeExtended;
use serj\sortable\Sortable;

class SortableTreeMySqlTest extends SortableTreeBase
{
    /**
     * @var \UnitTester
     */
    protected $tester;


    protected function _before() {
        $config = [
            'id' => 'test case',
            'basePath' => dirname(dirname(__DIR__)),
            'components' => [
                'db' => [
                    'class' => 'yii\db\Connection',
                    'dsn' => 'mysql:host=sortable-tree-mysql;dbname=sortable_tree_test',
                    'username' => 'tester',
                    'password' => 'secret',
                    'charset' => 'utf8',
                ],
            ]
        ];

        new yii\console\Application($config);

        Tree::setSortManager(
            new Sortable([
                'targetTable' => Tree::tableName(),
                'pkColumn' => 'id',
                'srtColumn' => 'sort',
                'grpColumn' => 'parent_id',
                'databaseDriver' => Sortable::DB_DRIVER_MYSQL
            ])
        );
    }
}