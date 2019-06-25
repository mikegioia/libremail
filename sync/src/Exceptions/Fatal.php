<?php

namespace App\Exceptions;

use Exception;

class Fatal extends Exception
{
    public $code = EXC_FATAL;
}
