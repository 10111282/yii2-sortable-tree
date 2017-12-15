<?php

namespace serj\sortableTree;

use serj\sortable\Sortable;
use yii\db\ActiveQuery;
use yii\db\Expression;
use yii\db\Query;
use yii\helpers\ArrayHelper;


class Tree extends RecordTreeData
{
    const EVENT_AFTER_ADD = 'tree.after_add';
    const EVENT_BEFORE_MOVE = 'tree.before_move';
    const EVENT_AFTER_MOVE = 'tree.after_move';
    const EVENT_BEFORE_DELETE = 'tree.before_delete';
    const EVENT_AFTER_DELETE = 'tree.after_delete';
    const EVENT_BEFORE_TREE_QUERY = 'tree.before_tree_query';

    /**
     * @var array
     */
    public $children = [];

    /**
     * @var Sortable
     */
    protected $sortManager;

    /**
     * @param Sortable $instance
     * @return $this
     */
    public function setSortManager(Sortable $instance)
    {
        $this->sortManager = $instance;

        return $this;
    }

    /**
     * @return Sortable
     */
    public function getSortManager()
    {
        if (!$this->sortManager) {
            $this->sortManager = new Sortable([
                'targetTable' => static::tableName(),
                'pkColumn' => 'id',
                'srtColumn' => 'sort',
                'grpColumn' => 'parent_id'
            ]);
        }

        return $this->sortManager;
    }

    /**
     * Adds a new item to the specified position.
     * With no parameters passed creates a new root.
     *
     * @param int $parentId
     * @param int|null $targetId Relative to this id a new item will be inserted.
     * @param string $position Specifies how to interpret targetId: before, after.
     * @param array $data Extra data to populate domain model (in case of extending this class).
     * @return static
     * @throws \Exception
     * @throws \yii\db\Exception
     */
    public static function addItem(int $parentId = 0, int $targetId = null, $position = null, array $data = [])
    {
        $db = \Yii::$app->db;
        $transaction = $db->beginTransaction();

        $model = static::instantiate(null);

        try {
            $model->setScenario('addItem');
            $model->setAttributes($data);
            $model->parent_id = $parentId;
            $level = 0;
            if ($parentId !== 0) { //root
                $parentRecord = $model->getRecord($parentId);
                $level = $parentRecord->level + 1;
            }
            $model->level = $level;

            if (!$targetId || !$position) {
                $sortVal = $model->getSortManager()->getSortValAfterAll($model->parent_id);
            }
            else {
                $sortVal = $model->getSortManager()->getSortVal($targetId, $position, $model->parent_id);
            }
            $model->sort = $sortVal;


            if (!$model->save()) {
                $transaction->rollback();

                return $model;
            }

            RecordTreeStructure::add($model->parent_id, $model->id);

            $event = new EventTree([
                'sender' => $model,
                'senderData' => [
                    'target_id' => $targetId,
                    'position' => $position,
                    'data' => $data
                ]
            ]);
            \Yii::$app->trigger(self::EVENT_AFTER_ADD, $event);

            $transaction->commit();

        }
        catch (\Exception $e) {
            $transaction->rollback();
            throw $e;
        }

        return $model;
    }

    /**
     * Moves an item along with its children to another parent, or just reorders items under the same parent.
     *
     * @param int $id Item to move.
     * @param int $newParentId New parent or the same one for reordering.
     * @param int $targetId Where to move an item.
     * @param null $position Specifies how to interpret targetId: before, after.
     * @return null|RecordTreeData|\yii\db\ActiveRecord
     * @throws SortableTreeException
     * @throws \Exception
     * @throws \yii\db\Exception
     */
    public static function moveTo(int $id, int $newParentId, int $targetId = null, $position = null)
    {
        $model = static::instantiate(null);
        $record = $model->getRecord($id);
        $record->setScenario('moveItem');
        $newParentRecord = $model->getRecord($newParentId);

        $db = \Yii::$app->db;
        $transaction = $db->beginTransaction();

        try {
            if (!$targetId || !$position) {
                $sortVal = $model->getSortManager()->getSortValAfterAll($newParentId);
            }
            else {
                $sortVal = $model->getSortManager()->getSortVal($targetId, $position, $newParentRecord->id);
            }

            $event = new EventTree([
                'sender' => $record,
                'senderData' => [
                    'id' => $id,
                    'new_parent_id' => $newParentId,
                    'target_id' => $targetId,
                    'position' => $position
                ]
            ]);
            \Yii::$app->trigger(self::EVENT_BEFORE_MOVE, $event);

            RecordTreeStructure::resetParent($id, $newParentRecord->id);

            $record->parent_id = $newParentRecord->id;
            $record->sort = $sortVal;
            $record->save();

            self::updateLevelRecursive($record, $newParentRecord->level + 1);

            \Yii::$app->trigger(self::EVENT_AFTER_MOVE, new EventTree(['sender' => $record]));

            $transaction->commit();
        }
        catch (\Exception $e) {
            $transaction->rollback();
            throw $e;
        }


        return $record;
    }

    /**
     * Deletes an item and all its children.
     *
     * @param int $id
     * @return int
     * @throws SortableTreeException
     * @throws \Exception
     * @throws \yii\db\Exception
     */
    public static function deleteRecursive(int $id)
    {
        $model = static::instantiate(null);
        $items = $model->flatTree($id, 'descending', null, false, true, true);
        $ids = ArrayHelper::getColumn($items, 'id');
        array_unshift($ids, $id);
        $ids = array_values(array_unique($ids));
        $event = new EventTree([
            'sender' => $model,
            'senderData' => [
                'ids' => $ids
            ]
        ]);
        \Yii::$app->trigger(self::EVENT_BEFORE_DELETE, $event);
        $ids = $event->senderData['ids'];

        $db = \Yii::$app->db;
        $transaction = $db->beginTransaction();
        try {
            $cntAffected = static::deleteItems($ids);

            $event = new EventTree([
                'sender' => $model,
                'senderData' => [
                    'ids' => $ids,
                    'cntDeleted' => $cntAffected
                ]
            ]);
            \Yii::$app->trigger(self::EVENT_AFTER_DELETE, $event);

            $transaction->commit();

            return $cntAffected;
        }
        catch (\Exception $e ) {
            $transaction->rollBack();
            throw $e;
        }
    }

    /**
     * @param array $ids Item's ids to delete.
     * @return int
     * @throws SortableTreeException
     * @throws \yii\db\Exception
     */
    protected static function deleteItems(array $ids) {
        $cntAffected = \Yii::$app->db->createCommand()
            ->delete(
                static::tableName(),
                ['id' => $ids]
            )
            ->execute();

        if ($cntAffected != count($ids)) {
            throw new SortableTreeException('Some items failed to process. Deletion was canceled.');
        }

        RecordTreeStructure::deleteRecursive($ids[0]);

        return $cntAffected;
    }

    /**
     * Returns flattened descending tree, starting from specified id.
     * If id not passed, then an array of all trees will be returned.
     *
     * @param int|null $id
     * @param int|null $depth Number of nested levels to include. NULL - to include all.
     * @param bool $asArray
     * @return array
     * @throws SortableTreeException
     */
    public static function getDescendingFlatTree(int $id = null, int $depth = null, bool $asArray = true)
    {
        $model = static::instantiate(null);

        if ($id !== null) {
            return $model->flatTree($id, 'descending', $depth, false, $asArray);
        }
        else {
            $trees = [];
            $roots = $model->getRoots();
            foreach ($roots as $id) {
                $trees[] = $model->flatTree($id, 'descending', $depth, false, $asArray);
            }

            return $trees;
        }
    }

    /**
     * Returns a tree as multidimensional array.
     * If id not passed, then an array of all trees will be returned.
     *
     * @param int|null $id
     * @param int|null $depth Number of nested levels to include. NULL - to include all.
     * @param bool $asArray
     * @return array One tree or an array of trees.
     * @throws SortableTreeException
     */
    public static function getDescendingTree(int $id = null, int $depth = null, bool $asArray = true)
    {
        $model = static::instantiate(null);
        if ($id !== null) {
            $flatTreeItems = $model->flatTree($id, 'descending', $depth, false, $asArray);
            return static::buildTreeRecursive($flatTreeItems);
        }
        else {
            $trees = [];
            $roots = $model->getRoots();
            foreach ($roots as $id) {
                $flatTreeItems = $model->flatTree($id, 'descending', $depth, false, $asArray);
                $trees[] = static::buildTreeRecursive($flatTreeItems);
            }

            return $trees;
        }
    }

    /**
     * Returns flattened ascending tree, starting from specified id up to the tree root.
     *
     * @param int $id
     * @param bool $asArray
     * @return array
     * @throws SortableTreeException
     */
    public static function getAscendingFlatTree(int $id, bool $asArray = true)
    {
        $model = static::instantiate(null);
        $items = $model->flatTree($id, 'ascending', null, false, $asArray);

        return $items;
    }

    /**
     * Returns a tree as a multidimensional array starting from specified id up to the root.
     *
     * @param int $id
     * @param bool $asArray
     * @return array
     * @throws SortableTreeException
     */
    public static function getAscendingTree(int $id, $asArray = true)
    {
        $model = static::instantiate(null);
        $flatTreeItems = $model->flatTree($id, 'ascending', null, false, $asArray);

        return $model->buildTreeRecursive($flatTreeItems);
    }

    /**
     * Checks if an item has at least one child.
     *
     * @param int $id
     * @return bool
     */
    public function hasChildren(int $id)
    {
        return (bool)$this->countFirstChildren($id);
    }


    /**
     * Counts immediate children
     *
     * @param int $id Category id
     * @return int
     */
    public function countFirstChildren(int $id)
    {
        $query = self::find()
            ->select('count(*)')
            ->from(static::tableName())
            ->where([
                'parent_id' => $id,
            ]);

        if ($filer = $this->getFilter()) {
            $filer->applyFilter($query);
        }

        return (int)$query->scalar();
    }

    /**
     * Array of ids of the all root nodes.
     *
     * @return array
     */
    public static function getRoots()
    {
        $model = static::instantiate(null);
        $query = $model->find()
            ->select('id')
            ->where([
                'parent_id' => 0
            ]);

        if ($filer = $model->getFilter()) {
            $filer->applyFilter($query);
        }

        return $query->column();
    }

    /**
     * @param int $level
     * @param int|null $parent
     * @param bool $asArray
     * @return array|\yii\db\ActiveRecord[]
     */
    public static function getItemsByLevel(int $level, int $parent = null, $asArray = true)
    {
        $model = static::instantiate(null);
        $query = $model->find()
            ->where([
                'level' => $level
            ])
            ->indexBy('id');

        if ($parent) {
            $query->andWhere(['parent_id' => $parent]);
        }

        if ($filer = $model->getFilter()) {
            $filer->applyFilter($query);
        }

        if ($asArray) {
            $query->asArray();
        }

        return $query->all();
    }

    /**
     * @param int $id
     * @return false|null|string
     */
    public static function getLevelById(int $id) {
        return (new Query())
            ->select('level')
            ->from(static::tableName())
            ->where([
                'id' => $id
            ])
            ->scalar();
    }

    /**
     * Changes item's level, does its recursively for all children.
     *
     * @param RecordTreeData $record
     * @param int $desiredLevel
     * @return int Number of rows affected by the execution.
     * @throws SortableTreeException
     * @throws \yii\db\Exception
     */
    private static function updateLevelRecursive(RecordTreeData $record, int $desiredLevel)
    {
        $model = static::instantiate(null);
        $items = $model->flatTree($record->id, 'descending');
        $ids = ArrayHelper::getColumn($items, 'id');

        $levelDivergence = abs($record->level - $desiredLevel);

        $expression = $desiredLevel > $record->level ?
            new Expression("level + $levelDivergence") : new Expression("level - $levelDivergence");

        return \Yii::$app->db->createCommand()
            ->update(
                static::tableName(),
                ['level' => $expression],
                ['in', 'id', $ids]
            )
            ->execute();
    }


    /**
     * @param array $flatTreeItems
     * @return array
     */
    private function buildTreeRecursive(array $flatTreeItems) {
        if (!$flatTreeItems) return [];

        $flatTreeItems = new \ArrayIterator($flatTreeItems);

        $itemsCnt = count($flatTreeItems);
        $safetyCounter = pow($itemsCnt, 2) + $itemsCnt + 1;

        $foo = function(\ArrayIterator $flatTreeItems, int $parentId) use (&$foo, &$safetyCounter) {
            $branch = [];
            while ($flatTreeItems->valid() && $safetyCounter > 0) {
                $safetyCounter--;
                $item = $flatTreeItems->current();

                if (!($item['parent_id'] == $parentId && $item['id'] !== $parentId)) {
                    $flatTreeItems->next();
                    continue;
                }

                $iterator = new \ArrayIterator($flatTreeItems);
                $iterator->seek($flatTreeItems->key()); // no need to iterate over iterated items
                $children = $foo($iterator, $item['id']);

                if ($children) {
                    $item['children'] = $children;
                }

                $branch[] = $item;

                $flatTreeItems->next();
            }

            return $branch;
        };

        $parentId = $flatTreeItems[0]['parent_id'];
        $tree = $foo($flatTreeItems, $parentId);

        if ($safetyCounter == 0) {
            \Yii::warning(
               "Possible infinite loop while building a tree."
            );
        }

        return $tree[0] ?? [];
    }

    /**
     * Returns ascending or descending flat tree.
     *
     * @param int $id
     * @param string $type One of: ascending, descending.
     * @param null|int $depth
     * @param bool $indexById If true then array will be indexed with item's ids.
     * @return array
     * @return array|\yii\db\ActiveRecord[]
     * @throws SortableTreeException
     */
    private function flatTree(
        int $id,
        string $type,
        int $depth = null,
        bool $indexById = false,
        bool $asArray = true,
        bool $skipFilter = false
    )
    {
        $query = $this->flatTreeQuery($id, $type);

        if ($depth !== null) {
            $query->andWhere(['<=', 'level', $depth]);
        }

        if ($indexById) {
            $query->indexBy('id');
        }

        if ($asArray) {
            $query->asArray();
        }

        if (!$skipFilter && $filer = $this->getFilter()) {
            $filer->applyFilter($query);
        }

        $event = new EventTree([
            'sender' => $this,
            'senderData' => [
                'query' => $query
            ]
        ]);
        \Yii::$app->trigger(self::EVENT_BEFORE_TREE_QUERY, $event);

        $items = $query->all();

        if (!$items) {
            throw new SortableTreeException(sprintf('Tree item [ %d ] not found', $id));
        }

        return $items;
    }

    /**
     * Creates a query to fetch items of a tree.
     *
     * @param int $id Item id to start from.
     * @param string $type Direction: ascending or descending.
     * @return ActiveQuery
     */
    protected function flatTreeQuery(int $id, string $type)
    {
        $dataTable = static::tableName();
        $treeTable = RecordTreeStructure::tableName();
        $query = static::find()
            ->select(["{$dataTable}.*"])
            ->from($dataTable);

            if ($type == 'descending') {
                $query->innerJoin(
                    $treeTable,
                    "{$dataTable}.id = {$treeTable}.child and {$treeTable}.parent = :id",
                     ['id' => $id]
                );
            }
            else {
                $query->innerJoin(
                    $treeTable,
                    "{$dataTable}.id = {$treeTable}.parent and {$treeTable}.child = :id",
                    ['id' => $id]
                );
            }

        $query->orderBy("level ASC, sort ASC");

        return $query;
    }
}
