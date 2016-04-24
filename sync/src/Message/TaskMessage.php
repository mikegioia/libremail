<?php

namespace App\Message;

use App\Message
  , App\Message\AbstractMessage;

class TaskMessage extends AbstractMessage
{
    public $data;
    protected $type = Message::MESSAGE_TASK;

    public function __construct( $data )
    {
        $this->data = $data;
    }
}