<?php

namespace App\Message;

use App\Message;

class PidMessage extends AbstractMessage
{
    public $pid;
    protected $type = Message::PID;

    public function __construct($pid)
    {
        $this->pid = $pid;
    }
}
