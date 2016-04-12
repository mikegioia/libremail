<?php

namespace App;

use App\Daemon
  , Pimple\Container
  , \App\Models\Account as AccountModel
  , \App\Exceptions\NoAccounts as NoAccountsException;

/**
 * Runs at the start of the application and performs various
 * initializing and testing before the app can run.
 */
class Startup
{
    private $log;
    private $console;

    public function __construct( Container $di )
    {
        $this->console = $di[ 'console' ];
        $this->log = $di[ 'log' ]->getLogger();
    }

    public function run()
    {
        $this->log->debug( "Starting sync engine" );
        $this->log->info( "Process ID: ". getmypid() );

        if ( $this->console->daemon ) {
            Daemon::writeJson([
                'pid' => getmypid(),
                'type' => Daemon::MESSAGE_PID
            ]);
        }

        $this->checkIfAccountsExist();

        if ( $this->console->sleep === TRUE ) {
            $this->log->warn(
                "Sleep mode enabled. I will only respond to signals." );
            $this->log->warn(
                "Run 'kill -SIGQUIT ". getmypid() . "' to exit!" );
        }
    }

    public function runServer()
    {
        $this->log->debug( "Starting socket server" );
        $this->log->info( "Process ID: ". getmypid() );

        if ( $this->console->daemon ) {
            Daemon::writeJson([
                'pid' => getmypid(),
                'type' => Daemon::MESSAGE_PID
            ]);
        }
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