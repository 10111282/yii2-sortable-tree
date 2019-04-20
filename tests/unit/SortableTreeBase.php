<?php

use serj\sortableTree\Tree;
use serj\sortableTree\TreeExtended;

class SortableTreeBase extends \Codeception\Test\Unit
{
    /**
     * @var \UnitTester
     */
    protected $tester;


    protected function _before() {}

    protected function _after() {}

    protected function createTree()
    {
        // 1
        //   2
        //   3
        //   4
        //     6
        //   5
        $r1 = Tree::addItem(0);
        $r1_1 = Tree::addItem($r1->id);
        $r1_2 = Tree::addItem($r1->id);
        $r1_3 = Tree::addItem($r1->id);
        $r1_4 = Tree::addItem($r1->id);
        $r2_1 = Tree::addItem($r1_3->id);
    }

    public function testCreateTree()
    {
        // 1
        //   2
        //   4
        //   5
        //     6
        // 3

        $r1 = Tree::addItem(0);
        $this->assertTrue($r1->parent_id === 0);

        $r1_1 = Tree::addItem($r1->id);

        $this->assertTrue($r1_1->parent_id === $r1->id);

        $r1_2 = Tree::addItem($r1->id);
        $this->assertTrue($r1_2->parent_id === $r1->id);

        $r1_3 = Tree::addItem($r1->id, $r1_2->id, 'before');
        $this->assertTrue($r1_3->parent_id === $r1->id);

        $r1_4 = Tree::addItem($r1->id, $r1_3->id, 'after');
        $this->assertTrue($r1_4->parent_id === $r1->id);

        $r2_1 = Tree::addItem($r1_4->id);
        $this->assertTrue($r2_1->parent_id === $r1_4->id);

        $tree = Tree::getDescendingTree();

        $this->assertTrue((int)$tree[0]['children'][2]['children'][0]['id'] === $r2_1->id);
    }

    public function testMoveTo()
    {
        $this->createTree();

        //Expected result
        // 1
        //   2
        //   3
        //      4
        //          6
        //   5
        $r = Tree::moveTo(4, 3);
        $this->assertTrue($r->parent_id === 3);

        $tree = Tree::getDescendingTree();
        $this->assertTrue((int)$tree[0]['children'][1]['children'][0]['children'][0]['id'] === 6);


        //Expected result
        // 1
        //   2
        //   3
        //      4
        //          5
        //          6
        $r = Tree::moveTo(5, 4, 6, 'before');
        $this->assertTrue($r->parent_id === 4);

        $tree = Tree::getDescendingTree();
        $this->assertTrue((int)$tree[0]['children'][1]['children'][0]['children'][0]['id'] === 5);
    }

    public function testDeleteRecursive()
    {
        $this->createTree();

        $r = Tree::deleteRecursive(4);
        $this->assertTrue($r === 2);

        $tree = Tree::getDescendingFlatTree(1);
        $this->assertTrue(count($tree) === 4);
    }

    public function testGetDescendingTree()
    {
        $this->createTree();

        $tree = Tree::getDescendingTree(1);

        $this->assertTrue((int)$tree['id'] === 1);
        $this->assertTrue((int)$tree['children'][0]['id'] === 2);
        $this->assertTrue((int)$tree['children'][1]['id'] === 3);
        $this->assertTrue((int)$tree['children'][2]['id'] === 4);
        $this->assertTrue((int)$tree['children'][2]['children'][0]['id'] === 6);
        $this->assertTrue((int)$tree['children'][3]['id'] === 5);

    }

    public function testGetDescendingFlatTree()
    {
        $this->createTree();

        $tree = Tree::getDescendingFlatTree(1);

        $this->assertTrue(count($tree) === 6);
        $this->assertTrue((int)$tree[0]['id'] === 1);
        $this->assertTrue((int)$tree[5]['id'] === 6);
    }

    public function testGetAscendingFlatTree()
    {
        $this->createTree();

        $tree = Tree::getAscendingFlatTree(6);
        $this->assertTrue(count($tree) === 3);
        $this->assertTrue((int)$tree[0]['id'] === 1);
        $this->assertTrue((int)$tree[1]['id'] === 4);
        $this->assertTrue((int)$tree[2]['id'] === 6);
    }

    public function testGetAscendingTree()
    {
        $this->createTree();

        $tree = Tree::getAscendingTree(6);

        $this->assertTrue((int)$tree['id'] === 1);
        $this->assertTrue((int)$tree['children'][0]['id'] === 4);
        $this->assertTrue((int)$tree['children'][0]['children'][0]['id'] === 6);
    }

    public function testMultipleRoots()
    {
        $this->createTree();
        $this->createTree();

        $trees = Tree::getDescendingFlatTree();
        $this->assertTrue(count($trees[0]) === 6);
        $this->assertTrue(count($trees[1]) === 6);


        $trees = Tree::getDescendingTree();
        $this->assertTrue(count($trees) === 2);

        $roots = Tree::getRoots();
        $this->assertTrue((int)$roots[0] === 1);
        $this->assertTrue((int)$roots[1] === 7);
    }

    public function testGetItemsByLevel()
    {
        $this->createTree();

        $items = Tree::getItemsByLevel(1);
        $this->assertTrue(count($items) === 4);

        $items = Tree::getItemsByLevel(2);
        $this->assertTrue(count($items) === 1);

        $items = Tree::getItemsByLevel(2, 1);
        $this->assertTrue(count($items) === 0);
    }

    public  function testGetLevelById()
    {
        $this->createTree();

        $this->assertTrue((int)Tree::getLevelById(1) === 0);
        $this->assertTrue((int)Tree::getLevelById(4) === 1);
        $this->assertTrue((int)Tree::getLevelById(6) === 2);
    }

    public  function testEvents()
    {
        $r = Tree::addItem();
        $r2 = Tree::addItem($r->id);

        \Yii::$app->on(Tree::EVENT_AFTER_ADD, function (\yii\base\Event $event) {
            $this->assertTrue($event->sender->id === 3);
            $this->assertTrue($event->senderData['target_id'] === 2);
            $this->assertTrue($event->senderData['position'] === 'before');
        });
        $r3 = Tree::addItem($r->id, $r2->id, 'before');

        \Yii::$app->on(Tree::EVENT_BEFORE_MOVE, function (\yii\base\Event $event) {
            $this->assertTrue($event->senderData['id'] === 3);
            $this->assertTrue($event->senderData['new_parent_id'] === 1);
            $this->assertTrue($event->senderData['target_id'] === 2);
            $this->assertTrue($event->senderData['position'] === 'after');
        });
        \Yii::$app->on(Tree::EVENT_AFTER_MOVE, function (\yii\base\Event $event) {
            $this->assertTrue($event->sender->id === 3);
            $this->assertTrue($event->sender->parent_id === 1);
        });

        \Yii::$app->on(Tree::EVENT_BEFORE_TREE_QUERY, function (\yii\base\Event $event) {
            $this->assertTrue(get_class($event->senderData['query']) === 'yii\db\ActiveQuery');
        });
        Tree::getDescendingFlatTree(1);

        \Yii::$app->on(Tree::EVENT_BEFORE_DELETE, function (\yii\base\Event $event) {
            $this->assertTrue(count($event->senderData['ids']) === 3);
        });
        \Yii::$app->on(Tree::EVENT_AFTER_DELETE, function (\yii\base\Event $event) {
            $this->assertTrue(count($event->senderData['ids']) === 3);
            $this->assertTrue($event->senderData['cntDeleted'] === 3);
        });
        Tree::deleteRecursive(1);
    }
}