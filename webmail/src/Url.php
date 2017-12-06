<?php

namespace App;

class Url
{
    static private $base;

    static public function get( $path )
    {
        return self::$base . $path;
    }

    static public function redirect( $path, $code = 303 )
    {
        header( 'Location: '. self::get( $path ), $code );
        die();
    }

    static public function setBase( $base )
    {
        self::$base = $base;
    }
}