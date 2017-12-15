<?php
namespace serj\sortableTree;

use common\models\misc\ApiActiveModel;
use yii\base\InvalidParamException;
use yii\db\ActiveRecord;


/**
 * @property integer $id
 * @property integer $parent_id
 * @property integer $level
 * @property integer $sort
 *
 */
class RecordTreeData extends ActiveRecord
{
    const SCENARIO_ADD_ITEM = 'addItem';
    const SCENARIO_MOVE_ITEM = 'moveItem';

    /**
     * @var FilterInterface
     */
    private $filter;


    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return '{{%tree_data}}';
    }

    /**
     * @param FilterInterface $f
     * @return $this
     */
    public function setFilter(FilterInterface $f) {
        $this->filter = $f;

        return $this;
    }

    /**
     * @return FilterInterface
     */
    public function getFilter() {
        return $this->filter;
    }

    /**
     * @inheritdoc
     */
    public function scenarios()
    {
        $scenarios = parent::scenarios();
        $default = $scenarios[self::SCENARIO_DEFAULT];

        $scenarios[self::SCENARIO_DEFAULT] = array_diff($default, ['parent_id', 'level', 'sort']);
        $scenarios[self::SCENARIO_ADD_ITEM] = $default;
        $scenarios[self::SCENARIO_MOVE_ITEM] = $default;

        return $scenarios;
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['parent_id', 'level', 'sort'], 'required'],
            [['level', 'sort'], 'integer'],
            ['level', function($attribute) {
                $parentRecord = $this->parent_id !== 0 ? $this->getRecord($this->parent_id) : null;
                if ($parentRecord && ($parentRecord->level + 1) !== $this->level) {
                    self::addError(
                        $attribute,
                        \Yii::t('app', "Invalid item level.")
                    );
                }
            }, 'when' => function() {
                return array_key_exists('level', $this->getDirtyAttributes());
            }],
            ['parent_id', function($attribute) {
                if ($this->parent_id != 0) {
                    $this->getRecord($this->parent_id); // make sure parent exists
                }
            }, 'when' => function() {
                return array_key_exists('parent_id', $this->getDirtyAttributes());
            }],
        ];
    }


    /**
     * @param int $id
     * @param bool $silent Whether to throw an exception when item is not found.
     * @return null|RecordTreeData|ActiveRecord
     * @throws NotFoundHttpException
     */
    public static function getRecord(int $id, $silent = false)
    {
        $model = static::instantiate(null);
        $query = $model::find()
            ->andWhere([
                'id' => $id
            ]);

        if ($model->filter) {
            $model->filter->applyFilter($query);
        }

        $item = $query->one();

        if (!$item && !$silent) {
            throw new SortableTreeException(sprintf('Tree item [ %d ] not found', $id));
        }

        return $item;
    }
}
