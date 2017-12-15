<?php

$basePath = dirname(dirname(__DIR__));
$vandorPath = $basePath."/vendor";
require("$vandorPath/autoload.php");


spl_autoload_register(function ($class) {
    $basePath = dirname(dirname(__DIR__));
    if ($class == 'serj\sortableTree\Tree') {
        include "$basePath/src/Tree.php";
    }
    else if ($class == 'TreeExtended') {
        include "$basePath/examples/TreeExtended.php";
    }
    else if ($class == 'Filter') {
        include "$basePath/examples/Filter.php";
    }
    else if ($class == 'Yii') {
        include "$basePath/vendor/yiisoft/yii2/Yii.php";
    }
});


$config = [
    'id' => 'test case',
    'basePath' => dirname(dirname(__DIR__)),
    'components' => [
        'db' => [
            'class' => 'yii\db\Connection',
            'dsn' => "pgsql:host=localhost;dbname=sortable_tree_test",
            'username' => 'postgres',
            'password' => '',
            'charset' => 'utf8',
        ],
    ]
];

new yii\console\Application($config);
