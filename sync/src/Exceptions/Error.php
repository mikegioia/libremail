<?php

namespace App\Exceptions;

use Exception;

class Error extends Exception
{
    public $code = EXC_ERROR;
}
