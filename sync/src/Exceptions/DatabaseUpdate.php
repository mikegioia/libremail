<?php

namespace App\Exceptions;

class DatabaseUpdate extends \Exception
{
    public $code = EXC_DB_UPDATE;
    public $message = "There was a problem updating this %s.";

    function __construct( $type )
    {
        $this->message = sprintf( $this->message, $type );
    }
}