<?php

namespace App\Message;

use App\Message
  , App\Message\AbstractMessage;

class ErrorMessage extends AbstractMessage
{
    public $message;
    public $error_type;
    public $suggestion;
    protected $type = Message::ERROR;

    public function __construct( $errorType, $message, $suggestion = "" )
    {
        $this->message = $message;
        $this->error_type = $errorType;
        $this->suggestion = $suggestion;
    }
}