<?php

namespace serj\sortableTree;

use yii\helpers\ArrayHelper;


/**
 *
 * @property integer $id
 * @property integer $parent
 * @property integer $child
 */
class RecordTreeStructure extends \yii\db\ActiveRecord
{
    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return '{{%tree_structure}}';
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['parent', 'child'], 'required'],
            [['parent', 'child'], 'integer'],
        ];
    }


    /**
     * Creates parent-child record. Does it only if the same combination was not found.
     *
     * @param int $parent
     * @param int $child
     * @throws SortableTreeException
     */
    public static function add(int $parent, int $child)
    {
        self::addItselfAsParent($child);

        if ($parent != 0) {
            $parentsOfParent = self::getParents($parent);

            foreach ($parentsOfParent as $parentId) {
                self::addChildToParent($parentId, $child);
            }
        }
    }

    /**
     * @param int $childId
     * @param int $newParent
     * @throws SortableTreeException
     * @throws \yii\db\Exception
     */
    public static function resetParent(int $childId, int $newParent)
    {
        $currentChildren = self::getChildren($childId);
        $isNewParentAmongChildren = in_array($newParent, $currentChildren);
        if ($isNewParentAmongChildren) {
            throw new SortableTreeException('New parent is among current children!');
        }

        $currentParents = self::getParents($childId);
        $newParents = array_unique(array_merge(self::getParents($newParent), [$childId]));

        $obsoleteParents = array_diff($currentParents, $newParents);
        if (count($obsoleteParents)) {
            \Yii::$app->db->createCommand()
                ->delete(
                    self::tableName(),
                    ['and', ['parent' => $obsoleteParents, 'child' => $currentChildren]]
                )
                ->execute();
        }


        foreach ($newParents as $pId) {
            foreach ($currentChildren as $cId)
            self::addRecordIfNotPresent($pId, $cId);
        }
    }


    /**
     * Ads parent-child record if it's not present yet.
     *
     * @param int $parent
     * @param int $child
     * @return array|RecordTreeData|null|\yii\db\ActiveRecord
     * @throws SortableTreeException
     */
    private static function addRecordIfNotPresent(int $parent, int $child)
    {
        $existingRecord = self::find()
            ->where([
                'parent' => $parent,
                'child' => $child
            ])
            ->one();

        if (!$existingRecord) {
            $model = self::instantiate(null);
            $model->parent = $parent;
            $model->child = $child;
            if (!$model->save() || $model->hasErrors()) {
                throw new SortableTreeException('Unexpected error happened while adding tree structure record.');
            }

            return $model;
        }
        else {
            return $existingRecord;
        }
    }

    /**
     * @param int $id
     * @return int Number of records deleted.
     * @throws \yii\db\Exception
     */
    public static function deleteRecursive($id)
    {
        $records = self::find()->select('child')->where(['parent' => $id])->asArray()->all();
        $childrenIds = ArrayHelper::getColumn($records, 'child');

        $recordsToDelete = self::find()->select('id')->where(['child' => $childrenIds])->asArray()->all();
        $ids = ArrayHelper::getColumn($recordsToDelete, 'id');

        return \Yii::$app->db->createCommand()
            ->delete(
                RecordTreeStructure::tableName(),
                ['in', 'id', $ids]
            )
            ->execute();
    }

    /**
     * @param int $childId
     * @return mixed
     * @throws SortableTreeException
     */
    public static function deriveLevel(int $childId)
    {
        $parents = self::getParents($childId);

        if (!count($parents)) throw new SortableTreeException(sprintf("Record [ %d ] not found", $childId));
        else return count($parents) - 1;
    }

    /**
     * Returns array of ascendant ids.
     *
     * @param int $childId
     * @return array
     */
    public function getParents(int $childId)
    {
        $items = self::find()
            ->select('parent')
            ->where([
                'child' => $childId
            ])
            ->asArray()
            ->all();

        return ArrayHelper::getColumn($items, 'parent');
    }

    /**
     * Returns array of descendant ids;
     *
     * @param int $id
     * @return array
     */
    public function getChildren(int $id)
    {
        $items = self::find()
            ->select('child')
            ->where([
                'parent' => $id
            ])
            ->asArray()
            ->all();

        return ArrayHelper::getColumn($items, 'child');
    }

    /**
     * @param int $parent
     * @param int $child
     * @return array|RecordTreeData|null|\yii\db\ActiveRecord
     * @throws SortableTreeException
     */
    private static function addChildToParent(int $parent, int $child)
    {
        $existingRecord = self::find()
            ->where([
                'parent' => $parent,
                'child' => $child
            ])
            ->one();

        if (!$existingRecord) {
            $model = self::instantiate(null);
            $model->parent = $parent;
            $model->child = $child;
            if (!$model->save() || $model->hasErrors()) {
                throw new SortableTreeException('Unexpected error happened.');
            }

            return $model;
        }
        else {
            return $existingRecord;
        }
    }

    /**
     * @param int $id
     * @return array|RecordTreeData|null|\yii\db\ActiveRecord
     * @throws SortableTreeException
     */
    private static function addItselfAsParent(int $id)
    {
        $existingRecord = self::find()
            ->where([
                'parent' => $id,
                'child' => $id
            ])
            ->one();

        if (!$existingRecord) {
            $model = self::instantiate(null);
            $model->parent = $id;
            $model->child = $id;
            if (!$model->save() || $model->hasErrors()) {
                throw new SortableTreeException('Unexpected error happened.');
            }

            return $model;
        }
        else {
            return $existingRecord;
        }
    }

}