<?php

namespace App;

/**
 * Runs at the start of the application and performs various
 * initializing and testing before the app can run.
 */

class Startup
{
    private $dbName;
    private $interactive;

    function __construct( $config, $console )
    {
        $this->dbName = $config[ 'sql' ][ 'database' ];
        $this->interactive = ( $console->interactive === TRUE );
    }

    function run( $di )
    {
        // Try writing to the log
        $di[ 'log' ]->debug( "Starting sync engine" );

        // Check if database exists. This will try accessing it and
        // throw an error if it's not found.
        $di[ 'db' ]->isReady();

        $this->checkIfAccountsExist();
    }

    /**
     * Check if any accounts exist in the database. If not, and
     * if we're in interactive mode, then prompt the user to add
     * one. Otherwise log and exit.
     */
    private function checkIfAccountsExist()
    {

    }
}