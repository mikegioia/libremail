<?php

namespace App\Message;

use App\Message;

class StatsMessage extends AbstractMessage
{
    public $active;
    public $asleep;
    public $uptime;
    public $account;
    public $running;
    public $accounts;

    protected $type = Message::STATS;

    public function __construct(
        string $active,
        bool $asleep,
        string $account,
        bool $running,
        int $uptime,
        array $accounts
    ) {
        $this->active = $active;
        $this->asleep = $asleep;
        $this->uptime = $uptime;
        $this->running = $running;
        $this->account = $account;
        $this->accounts = $accounts;
    }
}
