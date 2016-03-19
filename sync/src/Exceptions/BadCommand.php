<?php

namespace App\Exceptions;

class BadCommand extends \Exception
{
    public $code = EXC_BAD_COMMAND;
    public $message = "An invalid command was attempted to be run: %s";

    public function __construct( $command )
    {
        $this->message = sprintf( $this->message, $command );
    }
}