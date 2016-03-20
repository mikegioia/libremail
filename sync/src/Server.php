<?php

namespace App;

use App\Log
  , React\EventLoop\LoopInterface;

abstract class Server
{
    protected $log;

    public function __construct( Log $log )
    {
        $this->log = $log->getLogger();
    }
}