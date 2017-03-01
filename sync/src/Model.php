<?php

namespace App;

use Monolog\Logger
  , Pimple\Container
  , Slim\PDO\Database
  , League\CLImate\CLImate
  , Particle\Validator\Validator;

class Model
{
    static protected $db;
    static protected $di;
    static protected $cli;
    static protected $log;
    static protected $config;

    /**
     * @var bool Mode for returning new DB instance.
     */
    static protected $factoryMode = FALSE;

    /**
     * @var Database Local reference if in factory mode.
     */
    protected $localDb;

    public function __construct( $data = NULL )
    {
        if ( ! $data ) {
            return;
        }

        $this->setData( $data );
    }

    /**
     * Implemented in model.
     */
    public function getData()
    {
        return [];
    }

    public function setData( $data )
    {
        foreach ( $data as $key => $value ) {
            $this->$key = $value;
        }
    }

    /**
     * Sets the internal database connection statically for all
     * models to use.
     */
    static function setDb( Database $db )
    {
        static::$db = $db;
    }

    static function setCLI( CLImate $cli )
    {
        static::$cli = $cli;
    }

    static function setLog( Logger $log )
    {
        static::$log = $log;
    }

    static function setConfig( array $config )
    {
        static::$config = $config;
    }

    static function setDbFactory( Container $di )
    {
        static::$di = $di;
        static::$factoryMode = TRUE;
    }

    public function db()
    {
        if ( static::$factoryMode === TRUE ) {
            if ( isset( $this->localDb ) ) {
                return $this->localDb;
            }

            $this->localDb = static::$di[ 'db_factory' ];

            return $this->localDb;
        }

        return static::$db;
    }

    public function cli()
    {
        return static::$cli;
    }

    public function log()
    {
        return static::$log;
    }

    /**
     * If a key came in, return the config value. The key is of
     * the form some.thing and will be exploded on the period.
     * @param string $key Optional key to lookup
     * @return mixed | array
     */
    public function config( $key = '' )
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

    public function getErrorString( Validator $validator, $message )
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
            ),
            ". \t\n\r\0\x0B" ) .".";
    }
}