<?php

namespace App\Exceptions;

use Exception;

class Restart extends Exception
{
    public $code = EXC_RESTART;
    public $message = 'Sync restart requested';
}
