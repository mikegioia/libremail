<?php

namespace App\Message;

use App\Message;

class AccountMessage extends AbstractMessage
{
    public $email;
    public $updated;

    protected $type = Message::ACCOUNT;

    public function __construct(bool $updated, string $email)
    {
        $this->email = $email;
        $this->updated = $updated;
    }
}
