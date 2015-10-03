<?php

namespace App\Exceptions;

class AttachmentsPathNotWriteable extends \Exception
{
    public $code = EXC_ATTACH_PATH;
    public $message = "The attachments path is not writeable by the current user: %s.";

    public function __construct()
    {
        $this->message = sprintf( $this->message, get_current_user() );
    }
}