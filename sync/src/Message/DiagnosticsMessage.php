<?php

namespace App\Message;

use App\Message;

class DiagnosticsMessage extends AbstractMessage
{
    public $tests;

    protected $type = Message::DIAGNOSTICS;

    public function __construct(array $tests)
    {
        $this->tests = $tests;
    }
}
