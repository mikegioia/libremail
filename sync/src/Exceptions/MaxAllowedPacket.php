<?php

namespace App\Exceptions;

class MaxAllowedPacket extends \Exception
{
    public $code = EXC_DB_MAX_PACKET;
    public $message =
        "The max_allowed_packet in MySQL (%s) is smaller than what's ".
        "safe for this sync (%s). You will more than likely experience ".
        "errors while saving large emails to the database. Please see ".
        "the documentation on updating this MySQL setting in your ".
        "configuration file.";

    public function __construct( $size, $safe = 16 )
    {
        $this->message = sprintf( $this->message, $size, $safe );
    }
}