<?php

namespace App;

class Constants
{
    /**
     * Iterates over an array and defines constants.
     * @param array $constants
     */
    static public function process( $constants )
    {
        foreach ( $constants as $constant => $value ) {
            define( $constant, $value );
        }
    }
}