<?php

namespace App\Message;

use App\Message;

class TaskMessage extends AbstractMessage
{
    public $data;
    public $task;

    protected $type = Message::TASK;

    public function __construct(string $task, array $data)
    {
        $this->data = $data;
        $this->task = $task;
    }
}
