<?php

namespace App\Message;

use App\Message;

class ErrorMessage extends AbstractMessage
{
    public $message;
    public $error_type;
    public $suggestion;

    protected $type = Message::ERROR;

    public function __construct(string $errorType, string $message, string $suggestion = '')
    {
        $this->message = $message;
        $this->error_type = $errorType;
        $this->suggestion = $suggestion;
    }
}
