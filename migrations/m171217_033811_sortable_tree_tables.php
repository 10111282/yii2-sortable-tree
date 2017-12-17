<?php

use yii\db\Migration;

/**
 * Class m171217_033811_sortable_tree_tables
 */
class m171217_033811_sortable_tree_tables extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        $this->createTable('{{%tree_data}}', [
            'id' => $this->primaryKey(),
            'parent_id' => $this->integer(),
            'level' => $this->integer(),
            'sort' => $this->integer()
        ]);

        $this->createTable('{{%tree_structure}}', [
            'id' => $this->primaryKey(),
            'parent' => $this->integer(),
            'child' => $this->integer()
        ]);
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        $this->dropTable('{{%tree_data}}');
        $this->dropTable('{{%tree_structure}}');
    }
}
