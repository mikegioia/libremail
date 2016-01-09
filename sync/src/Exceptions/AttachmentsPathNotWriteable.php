<?php

namespace App\Exceptions;

class AttachmentsPathNotWriteable extends \Exception
{
    public $code = EXC_ATTACH_PATH;
    public $message =
        "The attachments path %s is not writeable by the current user: %s";

    public function __construct( $directory )
    {
        $this->message = sprintf(
            $this->message,
            $directory,
            get_current_user() );
    }
}