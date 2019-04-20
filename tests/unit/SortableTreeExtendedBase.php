<?php


class SortableTreeExtendedBase extends \Codeception\Test\Unit
{
    /**
     * @var \UnitTester
     */
    protected $tester;


    protected function _before() {}

    protected function _after() {}


    public function testCreateTree()
    {
        // 1(Root)
        //   2(1.1)
        //   4(1.3)
        //   5(1.4)
        //     6(2.1)
        // 3(1.2)
        $r1 = TreeExtended::addItem(0, null, null , ['title' => 'Root']);
        $r1_1 = TreeExtended::addItem($r1->id, null, null, ['title' => '1.1']);
        $r1_2 = TreeExtended::addItem($r1->id, null, null, ['title' => '1.2']);
        //var_dump($r1->id,  $r1_2->id); exit;
        $r1_3 = TreeExtended::addItem($r1->id,  $r1_2->id, 'before', ['title' => '1.3']);
        $r1_4 = TreeExtended::addItem($r1->id,  $r1_3->id, 'after', ['title' => '1.4']);
        $r2_1 = TreeExtended::addItem($r1_4->id,  null, null, ['title' => '2.1']);

        $this->assertTrue($r2_1->title == '2.1' && $r2_1->parent_id == $r1_4->id);

        $tree = TreeExtended::getDescendingTree();

        $this->assertTrue($tree[0]['children'][2]['children'][0]['title'] == '2.1');
    }

    public function testEditTitle()
    {
        $r = TreeExtended::addItem(0, null, null , ['title' => 'Hello']);
        $r = TreeExtended::editTitle($r->id, 'Good bye');

        $this->assertTrue($r->title == 'Good bye');
    }

    public function testDeleteRecursive()
    {
        $r1 = TreeExtended::addItem(0, null, null , ['title' => '1']);
        $r1_1 = TreeExtended::addItem($r1->id, null, null, ['title' => '1.1']);
        $r1_1_1 = TreeExtended::addItem($r1_1->id, null, null, ['title' => '1.1.1']);

        $r = TreeExtended::deleteRecursive($r1_1->id);
        $this->assertTrue($r === 2);

        $tree = TreeExtended::getDescendingFlatTree($r1->id);
        $this->assertTrue(count($tree) === 1);
    }
}