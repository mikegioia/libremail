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

        $this->checkIfAccountsExist( $di[ 'db' ], $di[ 'cli' ] );
    }

    /**
     * Check if any accounts exist in the database. If not, and
     * if we're in interactive mode, then prompt the user to add
     * one. Otherwise log and exit.
     */
    private function checkIfAccountsExist( $db, $cli )
    {
        $accounts = $db->select(
            'accounts', [
                'is_active =' => 1
            ]);

        if ( ! $accounts ) {
            if ( $this->interactive ) {
                $cli->info( "No active email accounts exist in the database." );
                $input = $cli->confirm( "Do you want to add one now?" );

                if ( $input->confirmed() ) {
                    // First prompt to choose a type of account
                    $cli->br();
                    $input = $cli->radio(
                        'Please choose from the supported email providers:',
                        [ 'GMail', 'Outlook' ] );
                    $response = $input->prompt();
                    var_dump( $response );
                }
            }
            else {
                throw new NoAccountsException(
                    "No active email accounts exist in the database." );
            }
        }
    }
}

class NoAccountsException extends \Exception {}