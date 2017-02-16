<?php

namespace App\Message;

use App\Message
  , App\Message\AbstractMessage;

class AccountMessage extends AbstractMessage
{
    public $email;
    public $updated;
    protected $type = Message::ACCOUNT;

    public function __construct( $updated, $email )
    {
        $this->email = $email;
        $this->updated = (bool) $updated;
    }
}