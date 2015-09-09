<?php

namespace App;

class Model
{
    static protected $db;
    static protected $cli;
    static protected $log;
    static protected $config;

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

    static function setConfig( $config )
    {
        static::$config = $config;
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

    /**
     * If a key came in, return the config value. The key is of
     * the form some.thing and will be exploded on the period.
     * @param string $key Optional key to lookup
     * @return mixed | array
     */
    function config( $key = '' )
    {
        if ( ! $key ) {
            return static::$config;
        }

        $lookup = static::$config;

        foreach ( explode( '.', $key ) as $part ) {
            if ( ! isset( $lookup[ $part ] ) ) {
                return NULL;
            }

            $lookup = $lookup[ $part ];
        }

        return $lookup;
    }

    function getErrorString( $validator, $message )
    {
        $return = [];
        $messages = $validator->getMessages();

        foreach ( $messages as $key => $messages ) {
            $return = array_merge( $return, array_values( $messages ) );
        }

        return trim(
            sprintf(
                "%s\n\n%s",
                $message,
                implode( "\n", $return )
            ));
    }
}