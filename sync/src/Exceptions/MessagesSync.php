<?php

namespace App\Exceptions;

use Exception;

class MessagesSync extends Exception
{
    public $code = EXC_MESSAGES_SYNC;
    public $message = "Failed to sync IMAP messages for folder '%s'.";

    public function __construct(string $folder)
    {
        $this->message = sprintf($this->message, $folder);
    }
}
