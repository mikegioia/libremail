<?php

namespace App\Message;

use App\Message
  , App\Message\AbstractMessage;

class AccountMessage extends AbstractMessage
{
    public $locked;
    protected $type = Message::ACCOUNT;

    public function __construct( $locked )
    {
        $this->locked = $locked;
    }
}