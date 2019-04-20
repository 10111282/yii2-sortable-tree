# yii2-sortable-tree
A set of classes for Yii2 to create and maintain a tree-like structure.
## Installation
To import the component to your project, put the following line to the require section of your composer.json file:
```js
"serj/sortable-tree": "~1.0.0"
```
or run the command
```bash
$ composer require serj/sortable-tree "~1.0.0"
```
To create database tables apply a migration.
```bash
./yii migrate --migrationPath=@app/vendor/serj/sortable-tree/migrations
```
## Usage
### Adding a root
```php
Tree::addItem();
```
 ### Adding a nested item
 Assume that root item has an id = 1. To add an item under the root:
```php
Tree::addItem(1);
```
 Let's add another item, but insert it before previous one. We assume that last inserted item has id = 2. 
```php
$parent = 1;
$target = 2;
$position = 'before';
Tree::addItem($parent, $target, $position);
```
 For the moment, our tree looks like this:
```
├── 1
│   ├── 3
│   └── 2
 ```
 ### Moving items
 Let's swap items 2 and 3
```php
$parent = 1; // we move items under the same parent
$id = 3 // move this item
$target = 2; // we want to locate the item after this one
$position = 'after';
Tree::moveTo($id, $parent, $target, $position)
```
Now it should be like this:
```
├── 1
│   ├── 2
│   └── 3
```
Let's nest the item 3 into the item 2
```php
$parent = 2;
$id = 3
$position = 'after';
Tree::moveTo($id, $parent)
```
The result is:
```
├── 1
│   ├── 2
│   	├── 3
```
### Deletion
```php
Tree::deleteRecursive(2);
```
We deleted item 2 and its child 3. The root item left.
```
├── 1
```
### Getting the tree
```
Tree::getDescendingTree();
```
There are many other methods to work with the tree. For more information you can explore public methods of the *Tree* class and unit tests.
## I want to store more data
Lets suppose you want to store *title*, *created_at*, *updated_at* fields. And you no longer want to remove items from the tree, but mark them as *deleted*. 
To achieve this, we can extend the *Tree* calss. But first, let's modify a migration.
```php
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
            'sort' => $this->integer(),
            'title' => $this->string(),
            'deleted' => $this->boolean()->defaultValue(false),
            'created_at' => $this->timestamp()->defaultValue('NOW()'),
            'updated_at' => $this->timestamp()->defaultValue('NOW()'),
            'deleted_at' => $this->timestamp(),
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
```
Do not forget to apply a new migration.

Extend the tree class. Add and overwrite some methods.
```php
<?php

use yii\db\Expression;

class TreeExtended extends \serj\sortableTree\Tree
{
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
                [['created_at', 'updated_at', 'deleted_at'], 'safe']
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
        $model = self::getRecord($id);
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
```
To skip deleted items we've added a filter in the class constructor. Let's implement it.
```php
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
```

Now to add an item, pass an array of extra attributes that was added to migration. In or case it's title.
```php
TreeExtended::addItem(0, null, null , ['title' => 'Root']);
```
To edit a title of the existing item:
```php
TreeExtended::editTitle(1, 'Root modified');
```
For deletion use the same method as above.
```php
Tree::deleteRecursive(1);
```
Tree class triggers a set of events, which might be useful.
```php
    const EVENT_AFTER_ADD = 'tree.after_add';
    const EVENT_BEFORE_MOVE = 'tree.before_move';
    const EVENT_AFTER_MOVE = 'tree.after_move';
    const EVENT_BEFORE_DELETE = 'tree.before_delete';
    const EVENT_AFTER_DELETE = 'tree.after_delete';
    const EVENT_BEFORE_TREE_QUERY = 'tree.before_tree_query';
```
For example to get ids of the items before they are deleted:
```php
\Yii::$app->on(Tree::EVENT_BEFORE_DELETE, function (\serj\sortableTree\EventTree $event) {
    print_r($event->senderData['ids']);
});
```


## Using with MySql database (by default it's Postgres)

```php
Tree::setSortManager(
    new Sortable([
        'targetTable' => Tree::tableName(),
        'pkColumn' => 'id',
        'srtColumn' => 'sort',
        'grpColumn' => 'parent_id',
        'databaseDriver' => Sortable::DB_DRIVER_MYSQL
    ])
); 
```