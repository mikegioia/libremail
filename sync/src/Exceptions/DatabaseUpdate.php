<?php

namespace App\Exceptions;

class DatabaseUpdate extends \Exception
{
    public $code = 2003;
    public $message = "There was a problem updating this %s.";

    function __construct( $type )
    {
        $this->message = sprintf( $this->message, $type );
    }
}