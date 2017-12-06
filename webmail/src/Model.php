<?php

namespace App;

use Slim\PDO\Database;

class Model
{
    static private $db;
    static private $dsn;
    static private $username;
    static private $password;

    const ASC = 'asc';
    const DESC = 'desc';

    public function __construct( $data = NULL )
    {
        if ( ! $data ) {
            return;
        }

        foreach ( $data as $key => $value ) {
            $this->$key = $value;
        }
    }

    /**
     * Store the connection info statically.
     * @param string $dsn
     * @param string $username
     * @param string $password
     */
    static function initDb( $dsn, $username, $password )
    {
        self::$dsn = $dsn;
        self::$username = $username;
        self::$password = $password;
    }

    /**
     * Sets the internal database connection statically for all
     * models to use. The database connection is only loaded the
     * first time it's referenced.
     * @return Database
     */
    public function db()
    {
        if ( isset( self::$db ) ) {
            return self::$db;
        }

        self::$db = new Database(
            self::$dsn,
            self::$username,
            self::$password );

        return self::$db;
    }
}