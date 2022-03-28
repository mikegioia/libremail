<?php

namespace App;

class Constants
{
    /**
     * Iterates over an array and defines constants.
     */
    public static function process(array $constants)
    {
        foreach ($constants as $constant => $value) {
            define($constant, $value);
        }
    }
}
