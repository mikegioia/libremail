<?php

namespace App\Message;

use App\Message
  , App\Message\AbstractMessage;

class HealthMessage extends AbstractMessage
{
    public $tests;
    public $procs;
    public $no_accounts;
    protected $type = Message::HEALTH;

    public function __construct( $tests, $procs, $noAccounts )
    {
        $this->tests = $tests ?: [];
        $this->procs = $procs ?: [];
        $this->no_accounts = $noAccounts;
    }
}