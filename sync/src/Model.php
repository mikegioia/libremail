<?php

namespace App;

class Model
{
    static protected $db;
    static protected $cli;
    static protected $log;

    /**
     * Sets the internal database connection statically for all
     * models to use.
     */
    static function setDb( $db )
    {
        static::$db = $db;
    }

    static function setCLI( $cli )
    {
        static::$cli = $cli;
    }

    static function setLog( $log )
    {
        static::$log = $log;
    }

    function db()
    {
        return static::$db;
    }

    function cli()
    {
        return static::$cli;
    }

    function log()
    {
        return static::$log;
    }
}