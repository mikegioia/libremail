<?php

namespace App;

/**
 * Runs at the start of the application and performs various
 * initializing and testing before the app can run.
 */

class Startup
{
    private $db;
    private $cli;
    private $log;
    private $dbName;
    private $console;

    function __construct( $di )
    {
        $this->db = $di[ 'db' ];
        $this->cli = $di[ 'cli' ];
        $this->log = $di[ 'log' ];
        $this->console = $di[ 'console' ];
        $this->dbName = $di[ 'config' ][ 'sql' ][ 'database' ];
    }

    function run()
    {
        // Try writing to the log
        $this->log->debug( "Starting sync engine" );

        // Check if database exists. This will try accessing it and
        // throw an error if it's not found.
        $this->db->isReady();

        $this->checkIfAccountsExist();
    }

    /**
     * Check if any accounts exist in the database. If not, and
     * if we're in interactive mode, then prompt the user to add
     * one. Otherwise log and exit.
     */
    private function checkIfAccountsExist()
    {
        $accounts = $this->db->select(
            'accounts', [
                'is_active =' => 1
            ]);

        if ( ! $accounts ) {
            if ( $this->console->interactive ) {
                $this->console->createNewAccount();
            }
            else {
                throw new NoAccountsException(
                    "No active email accounts exist in the database." );
            }
        }
    }
}

class NoAccountsException extends \Exception {}