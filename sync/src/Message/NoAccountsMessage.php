<?php

namespace App\Message;

use App\Message
  , App\Message\AbstractMessage;

class NoAccountsMessage extends AbstractMessage
{
    protected $type = Message::NO_ACCOUNTS;
}