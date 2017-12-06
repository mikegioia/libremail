<?php

namespace App;

class Url
{
    static private $base;

    static public function setBase( $base )
    {
        self::$base = $base;
    }

    static public function get( $path )
    {
        return self::$base . $path;
    }

    static public function make( $path, ...$parts )
    {
        return self::$base . vsprintf( $path, $parts );
    }

    static public function redirect( $path, $code = 303 )
    {
        header( 'Location: '. self::get( $path ), $code );
        die();
    }

    static public function postParam( $key, $default = NULL )
    {
        return ( isset( $_POST[ $key ] ) )
            ? $_POST[ $key ]
            : $default;
    }

    static public function getParam( $key, $default = NULL )
    {
        return ( isset( $_GET[ $key ] ) )
            ? $_POST[ $key ]
            : $default;
    }
}