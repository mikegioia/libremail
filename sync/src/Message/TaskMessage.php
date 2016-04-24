<?php

namespace App\Message;

use App\Message
  , App\Message\AbstractMessage;

class TaskMessage extends AbstractMessage
{
    public $data;
    public $task;
    protected $type = Message::TASK;

    public function __construct( $task, $data )
    {
        $this->data = $data;
        $this->task = $task;
    }
}