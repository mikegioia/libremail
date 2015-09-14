<?php

namespace App\Exceptions;

class MessageSync extends \Exception
{
    public $code = 3002;
    public $message = "Failed to sync IMAP messages for folder '%s'.";

    function __construct( $folder )
    {
        $this->message = sprintf( $this->message, $folder );
    }
}