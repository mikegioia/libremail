<?php

namespace App\Message;

use App\Message
  , App\Message\AbstractMessage;

class PidMessage extends AbstractMessage
{
    public $pid;
    protected $type = Message::MESSAGE_PID;

    public function __construct( $pid )
    {
        $this->pid = $pid;
    }
}