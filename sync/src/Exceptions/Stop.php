<?php

namespace App\Exceptions;

use Exception;

class Stop extends Exception
{
    public $code = EXC_STOP;
    public $message = 'System received SIGURG, stopping current sync';
}
