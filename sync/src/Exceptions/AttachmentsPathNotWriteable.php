<?php

namespace App\Exceptions;

class AttachmentsPathNotWriteable extends \Exception
{
    public $code = 1002;
    public $message = "The attachments path is not writeable by the current user: %s.";

    function __construct()
    {
        $this->message = sprintf( $this->message, get_current_user() );
    }
}