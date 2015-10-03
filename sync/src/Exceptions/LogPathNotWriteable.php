<?php

namespace App\Exceptions;

class LogPathNotWriteable extends \Exception
{
    public $code = EXC_LOG_PATH;
    public $message = "The log path is not writeable by the current user: %s.";

    public function __construct()
    {
        $this->message = sprintf( $this->message, get_current_user() );
    }
}