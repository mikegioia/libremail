<?php

namespace App;

use Pimple\Container
  , \App\Models\Account as AccountModel
  , \App\Exceptions\NoAccounts as NoAccountsException;

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

    public function __construct( Container $di )
    {
        $this->db = $di[ 'db' ];
        $this->cli = $di[ 'cli' ];
        $this->log = $di[ 'log' ];
        $this->console = $di[ 'console' ];
        $this->dbName = $di[ 'config' ][ 'sql' ][ 'database' ];
    }

    public function run()
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
     * @throws NoAccountsException
     */
    private function checkIfAccountsExist()
    {
        $accountModel = new AccountModel;
        $accounts = $accountModel->getActive();

        if ( ! $accounts ) {
            if ( $this->console->interactive ) {
                $this->console->createNewAccount();
            }
            else {
                throw new NoAccountsException;
            }
        }
    }
}