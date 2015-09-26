<?php

namespace App\Exceptions;

class Validation extends \Exception
{
    public $code = 2001;

    function __construct( $message )
    {
        $this->message = $message;
    }
}