<?php

namespace App\Message;

use App\Message;

class NoAccountsMessage extends AbstractMessage
{
    protected $type = Message::NO_ACCOUNTS;
}
