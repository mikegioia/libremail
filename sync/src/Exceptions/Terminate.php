<?php

namespace App\Exceptions;

class Terminate extends \Exception
{
    public $code = EXC_TERM;
    public $message = "System received SIGTERM, exiting.";
}