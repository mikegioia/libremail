<?php

namespace App;

class Database
{
    private $dbname;

    function __construct( $config )
    {
        $this->dbname = $config[ 'db' ][ 'database' ];
    }

    function checkExists( $db )
    {

    }
}