<?php

namespace App\Exceptions;

class DatabaseUpdate extends \Exception
{
    public $code = EXC_DB_UPDATE;
    public $message = "There was a problem updating this %s.";

    public function __construct( $type, $errors = [] )
    {
        $this->message = sprintf( $this->message, $type );

        if ( is_string( $errors ) ) {
            $this->message .= " $errors";
        }
        elseif ( count( $errors ) ) {
            $this->message .= " ". implode( PHP_EOL, $errors );
        }
    }
}