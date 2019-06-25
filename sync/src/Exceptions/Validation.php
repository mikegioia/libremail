<?php

namespace App\Exceptions;

use Exception;

class Validation extends Exception
{
    public $code = 2001;

    public function __construct(string $message)
    {
        $this->message = $message;
    }
}
