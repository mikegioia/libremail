<?php

namespace App;

use League\CLImate\CLImate as CLI;

class Console
{
    // CLImate instance
    private $cli;

    // Dependencies
    private $log;
    private $config;

    // Command line arguments
    public $help;
    public $verbose;
    public $background;
    public $interactive;

    function __construct( $config )
    {
        $this->config = $config;

        $this->cli = new CLI();
        $this->cli->description( "LibreMail IMAP to SQL sync engine" );
        $this->setupArgs();
        $this->processArgs();
    }

    function getCLI()
    {
        return $this->cli;
    }

    /**
     * Log depends on command line args so this needs to be set
     * after the dependency is loaded.
     */
    function setLog( $log )
    {
        $this->log = $log;
    }

    /**
     * Initializes the accepted arguments and saves them as class
     * properties accessible publicly.
     */
    private function setupArgs()
    {
        $this->cli->arguments->add([
            'background' => [
                'prefix' => 'b',
                'longPrefix' => 'background',
                'description' => 'Run as a background service',
                'noValue' => TRUE
            ],
            'interactive' => [
                'prefix' => 'i',
                'longPrefix' => 'interactive',
                'description' => 'Interact with the CLI; ignored if background set',
                'defaultValue' => TRUE,
                'noValue' => TRUE
            ],
            'help' => [
                'prefix' => 'h',
                'longPrefix' => 'help',
                'description' => 'Prints a usage statement',
                'noValue' => TRUE
            ]
        ]);
    }

    /**
     * Reads input values and saves to class variables.
     */
    private function processArgs()
    {
        $this->cli->arguments->parse();
        $this->help = $this->cli->arguments->get( 'help' );
        $this->verbose = $this->cli->arguments->get( 'verbose' );
        $this->background = $this->cli->arguments->get( 'background' );
        $this->interactive = $this->cli->arguments->get( 'interactive' );

        // If help is set, show the usage and exit
        if ( $this->help === TRUE ) {
            $this->cli->usage();
            exit( 0 );
        }

        // If background is set, turn off interactive
        if ( $this->background === TRUE ) {
            $this->interactive = FALSE;
        }
    }

    /**
     * Asks the user for account information to set up a new account.
     */
    function createNewAccount()
    {
        if ( ! $this->interactive ) {
            return;
        }

        $this->cli->info( "No active email accounts exist in the database." );
        $input = $this->cli->confirm( "Do you want to add one now?" );

        if ( ! $input->confirmed() ) {
            return;
        }

        $this->promptAccountInfo();
    }

    /**
     * Get the new account info from the user via CLI prompts. If
     * successful this will create a new record in the SQL database.
     */
    private function promptAccountInfo()
    {
        $newAccount = [];
        $newAccount[ 'type' ] = $this->promptAccountType();
        $newAccount[ 'email' ] = $this->promptEmail();
        $newAccount[ 'password' ] = $this->promptPassword();

        // Test connection before adding
        $this->testConnection( $newAccount );

        // Connection settings worked, save to SQL
        // @TODO
        print_r( $newAccount );
    }

    /**
     * Prompts the user to select an account type. Returns the type
     * on success or an empty string on error.
     * @return string
     */
    private function promptAccountType()
    {
        $validTypes = [ 'GMail', 'Outlook' ];
        $this->cli->br();
        $input = $this->cli->radio(
            'Please choose from the supported email providers:',
            $validTypes );
        $type = $input->prompt();

        if ( ! in_array( $type, $validTypes ) ) {
            $this->cli->comment( "You didn't select an account type!" );
            $input = $this->cli->confirm( "Do you want to try again?" );

            if ( $input->confirmed() ) {
                return $this->promptAccountType();
            }

            $this->cli->comment( 'Account setup canceled.' );
            exit( 0 );
        }

        return $type;
    }

    private function promptEmail()
    {
        $input = $this->cli->input( 'Email address:' );
        return $input->prompt();
    }

    private function promptPassword()
    {
        $input = $this->cli->password( 'Password:' );
        return $input->prompt();
    }

    /**
     * Attempts to connect to the mail server using the new account
     * settings from the prompt.
     * @param array $account Account credentials
     * @throws EmailConnectionException
     */
    private function testConnection( $account )
    {
        $sync = new \App\Sync();
        $sync->setConfig( $this->config );

        try {
            $sync->connect(
                $account[ 'type' ],
                $account[ 'email' ],
                $account[ 'password' ] );
        }
        catch ( \Exception $e ) {
            $this->cli->error(
                sprintf(
                    "Unable to connect as '%s' to %s IMAP server: %s.",
                    $account[ 'email' ],
                    $account[ 'type' ],
                    $e->getMessage()
                ));
            $this->cli->comment(
                "There was a problem connecting using the account info ".
                "you provided." );
            $input = $this->cli->confirm( "Do you want to try again?" );

            if ( $input->confirmed() ) {
                $this->promptAccountInfo();
            }
            else {
                $this->cli->comment( "Account setup canceled." );
                exit( 0 );
            }
        }
    }
}