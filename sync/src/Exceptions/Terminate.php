<?php

namespace App\Exceptions;

use Exception;

class Terminate extends Exception
{
    public $code = EXC_TERM;
    public $message = 'System received SIGTERM, exiting.';
}
