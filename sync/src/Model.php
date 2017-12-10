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

    const ASC = 'asc';
    const DESC = 'desc';

    /**
     * @var bool Mode for returning new DB instance.
     */
    static protected $factoryMode = FALSE;

    /**
     * @var Database Local reference if in factory mode.
     */
    static protected $localDb;

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
        self::$db = $db;
    }

    static function setCLI( CLImate $cli )
    {
        self::$cli = $cli;
    }

    static function setLog( Logger $log )
    {
        self::$log = $log;
    }

    static function setConfig( array $config )
    {
        self::$config = $config;
    }

    static function setDbFactory( Container $di )
    {
        self::$di = $di;
        self::$factoryMode = TRUE;
    }

    public function db()
    {
        return self::getDb();
    }

    static public function getDb()
    {
        if ( self::$factoryMode === TRUE ) {
            if ( isset( self::$localDb ) ) {
                return self::$localDb;
            }

            self::$localDb = self::$di[ 'db_factory' ];

            return self::$localDb;
        }

        return self::$db;
    }

    public function ping()
    {
        $this->db()->query( 'SELECT 1;' );
    }

    public function cli()
    {
        return self::$cli;
    }

    public function log()
    {
        return self::$log;
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
            return self::$config;
        }

        $lookup = self::$config;

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