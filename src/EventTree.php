<?php

namespace serj\sortableTree;

use yii\base\Event;

class EventTree extends Event
{
    /**
     * Optional data added by an object triggered the event.
     *
     * @var mixed
     */
    public $senderData;
}