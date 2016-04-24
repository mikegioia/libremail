<?php

namespace App\Message;

use App\Message
  , App\Message\AbstractMessage;

class StatsMessage extends AbstractMessage
{
    public $active;
    public $asleep;
    public $uptime;
    public $account;
    public $running;
    public $accounts;
    protected $type = Message::MESSAGE_STATS;

    public function __construct( $active, $asleep, $account, $running, $uptime, $accounts )
    {
        $this->active = $active;
        $this->uptime = $uptime;
        $this->account = $account;
        $this->accounts = $accounts;
        $this->asleep = (bool) $asleep;
        $this->running = (bool) $running;
    }
}