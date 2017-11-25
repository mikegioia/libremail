<?php

namespace App;

use Slim\PDO\Database;

class Model
{
    static protected $db;

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
     * Sets the internal database connection statically for all
     * models to use.
     */
    static function setDb( Database $db )
    {
        static::$db = $db;
    }

    public function db()
    {
        return static::$db;
    }
}