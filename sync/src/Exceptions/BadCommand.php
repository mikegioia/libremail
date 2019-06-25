<?php

namespace App\Exceptions;

use Exception;

class BadCommand extends Exception
{
    public $code = EXC_BAD_COMMAND;
    public $message = 'An invalid command was attempted to be run: %s';

    public function __construct(string $command)
    {
        $this->message = sprintf($this->message, $command);
    }
}
