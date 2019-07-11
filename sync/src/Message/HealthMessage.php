<?php

namespace App\Message;

use App\Message;

class HealthMessage extends AbstractMessage
{
    public $tests;
    public $procs;
    public $no_accounts;

    protected $type = Message::HEALTH;

    public function __construct(array $tests, array $procs, bool $noAccounts = null)
    {
        $this->tests = $tests ?: [];
        $this->procs = $procs ?: [];
        $this->no_accounts = $noAccounts;
    }
}
