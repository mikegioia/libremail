<?php

namespace App\Exceptions;

class DatabaseInsert extends \Exception
{
    public $code = 2002;
    public $message = "There was a problem creating this %s.";

    function __construct( $type )
    {
        $this->message = sprintf( $this->message, $type );
    }
}