<?php

namespace App\Events;

use Symfony\Component\EventDispatcher\Event;

class MessageEvent extends Event
{
    protected $message;

    public function __construct( $message )
    {
        $this->message = $message;
    }

    public function getMessage()
    {
        return $this->message;
    }
}