<?php

namespace App\Exceptions;

class MissingIMAPConfig extends \Exception
{
    public $code = 3001;
    public $message = "IMAP config not found for %s.";

    function __construct( $type )
    {
        $this->message = sprintf( $this->message, $type );
    }
}