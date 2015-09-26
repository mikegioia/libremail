<?php

namespace App\Exceptions;

class MessagesSync extends \Exception
{
    public $code = EXC_MESSAGES_SYNC;
    public $message = "Failed to sync IMAP messages for folder '%s'.";

    function __construct( $folder )
    {
        $this->message = sprintf( $this->message, $folder );
    }
}