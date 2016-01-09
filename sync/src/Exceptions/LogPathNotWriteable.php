<?php

namespace App\Exceptions;

class LogPathNotWriteable extends \Exception
{
    public $code = EXC_LOG_PATH;
    public $message =
        "The log path %s is not writeable by the current user: %s";

    public function __construct( $directory )
    {
        $this->message = sprintf(
            $this->message,
            $directory,
            get_current_user() );
    }
}