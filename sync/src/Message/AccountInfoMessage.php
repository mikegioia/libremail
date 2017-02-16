<?php

namespace App\Message;

use App\Message
  , App\Message\AbstractMessage
  , App\Model\Account as AccountModel;

class AccountInfoMessage extends AbstractMessage
{
    public $host;
    public $port;
    public $email;
    public $password;
    protected $type = Message::ACCOUNT_INFO;

    public function __construct( AccountModel $account )
    {
        $this->email = $account->email;
        $this->host = $account->imap_host;
        $this->port = $account->imap_port;
        $this->password = $account->password;
    }
}