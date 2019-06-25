<?php

namespace App\Events;

use App\Message\AbstractMessage;
use Symfony\Component\EventDispatcher\Event;

class MessageEvent extends Event
{
    protected $message;

    public function __construct(AbstractMessage $message)
    {
        $this->message = $message;
    }

    public function getMessage()
    {
        return $this->message;
    }
}
