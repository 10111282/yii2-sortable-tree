<?php

use serj\sortableTree\Tree;
use serj\sortableTree\TreeExtended;

class SortableTreePostgresTest extends SortableTreeBase
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
                    'dsn' => "pgsql:host=sortable-tree-postgres;dbname=sortable_tree_test",
                    'username' => 'postgres',
                    'password' => 'secret',
                    'charset' => 'utf8',
                ],
            ]
        ];

        new yii\console\Application($config);
    }
}