<?php

namespace App\Message;

use App\Message;

class NotificationMessage extends AbstractMessage
{
    public $status;
    public $message;
    protected $type = Message::NOTIFICATION;

    public function __construct($status, $message)
    {
        $this->status = $status;
        $this->message = $message;
    }
}
