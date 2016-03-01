<?php

namespace App\Events;

use Symfony\Component\EventDispatcher\Event;

class StatsEvent extends Event
{
    protected $stats;

    public function __construct( $stats )
    {
        $this->stats = $stats;
    }

    public function getStats()
    {
        return $this->stats;
    }
}