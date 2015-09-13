<?php

namespace App\Exceptions;

class LogPathNotWriteable extends \Exception
{
    public $code = 1001;
    public $message = "The log path is not writeable by the current user: %s.";

    function __construct()
    {
        $this->message = sprintf( $this->message, get_current_user() );
    }
}