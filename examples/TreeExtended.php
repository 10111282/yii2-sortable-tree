<?php

use yii\db\Expression;

class TreeExtended extends \serj\sortableTree\Tree
{

    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return '{{%tree_data_ext}}';
    }

    /**
     * @inheritdoc
     */
    public static function instantiate($row)
    {
        $model = new self();
        $model->setFilter(new Filter());

        return $model;
    }


    /**
     * @inheritdoc
     */
    public function rules()
    {
        return array_merge(
            parent::rules(),
            [
                ['title', 'string'],
                ['deleted', 'boolean'],
                [['created_at', 'updated_at'], 'safe']
            ]
        );
    }

    /**
     * @param int $id
     * @return null|RecordTreeData|\yii\db\ActiveRecord
     * @throws NotFoundHttpException
     */
    public function editTitle(int $id, $title)
    {
        $model =self::getRecord($id);
        $model->setAttributes([
            'title' => $title,
            'updated_at' => new Expression('NOW()')
        ]);

        if ($model->save()) {
            $model->refresh();

            return $model;
        }

        return $model;
    }

    /**
     * @param array $ids
     * @return int
     * @throws \yii\db\Exception
     */
    protected static function deleteItems(array $ids) {
        return \Yii::$app->db->createCommand()
            ->update(
                self::tableName(),
                ['deleted' => true, 'deleted_at' => new Expression('NOW()')],
                ['id' => $ids]
            )
            ->execute();
    }
}
