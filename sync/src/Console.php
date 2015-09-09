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
    public $create;
    public $verbose;
    public $updatedb;
    public $background;
    public $interactive;

    function __construct( $config )
    {
        $this->config = $config;

        $this->cli = new CLI();
        $this->cli->description( "LibreMail IMAP to SQL sync engine" );
        $this->setupArgs();
    }

    function init()
    {
        $this->processArgs();
    }

    function getCLI()
    {
        return $this->cli;
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
            'create' => [
                'prefix' => 'c',
                'longPrefix' => 'create',
                'description' => 'Create a new IMAP account',
                'noValue' => TRUE
            ],
            'help' => [
                'prefix' => 'h',
                'longPrefix' => 'help',
                'description' => 'Prints a usage statement',
                'noValue' => TRUE
            ],
            'interactive' => [
                'prefix' => 'i',
                'longPrefix' => 'interactive',
                'description' => 'Interact with the CLI; ignored if background set',
                'defaultValue' => TRUE,
                'noValue' => TRUE
            ],
            'updatedb' => [
                'prefix' => 'u',
                'longPrefix' => 'updatedb',
                'description' => 'Run the database migration scripts to update the schema',
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
        $this->create = $this->cli->arguments->get( 'create' );
        $this->verbose = $this->cli->arguments->get( 'verbose' );
        $this->updatedb = $this->cli->arguments->get( 'updatedb' );
        $this->background = $this->cli->arguments->get( 'background' );
        $this->interactive = $this->cli->arguments->get( 'interactive' );

        // If help is set, show the usage and exit
        if ( $this->help === TRUE ) {
            $this->cli->usage();
            exit( 0 );
        }

        // If create is set, skip right to the account creation
        if ( $this->create === TRUE ) {
            $this->interactive = TRUE;
            $this->cli->info( "Creating a new IMAP account" );
            $this->promptAccountInfo();
            exit( 0 );
        }

        // If updatedb is set, just run the migration script
        if ( $this->updatedb === TRUE ) {
            $this->interactive = TRUE;
            $migrate = new \App\Models\Migration();
            $migrate->run();
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

        $this->cli->br();
        $this->promptAccountInfo();
    }

    /**
     * Get the new account info from the user via CLI prompts. If
     * successful this will create a new record in the SQL database.
     */
    private function promptAccountInfo()
    {
        $newAccount = [];
        $newAccount[ 'service' ] = $this->promptAccountType();
        $newAccount[ 'email' ] = $this->promptEmail();
        $newAccount[ 'password' ] = $this->promptPassword();

        // Test connection before adding
        $this->testConnection( $newAccount );

        // Connection settings worked, save to SQL
        try {
            \App\Models\Account::create( $newAccount );
        }
        catch ( \Exception $e ) {
            $this->cli->boldRedBackgroundBlack( $e->getMessage() );
            $input = $this->cli->confirm( "Do you want to try again?" );

            if ( $input->confirmed() ) {
                return $this->promptAccountInfo();
            }

            $this->cli->comment( "Account setup canceled." );
            return;
        }

        $this->cli->info( "Your account has been saved!" );
    }

    /**
     * Prompts the user to select an account type. Returns the service
     * on success or an empty string on error.
     * @return string
     */
    private function promptAccountType()
    {
        $validServices = $this->config[ 'email' ][ 'services' ];
        $input = $this->cli->radio(
            'Please choose from the supported email providers:',
            $validServices );
        $service = $input->prompt();

        if ( ! in_array( $service, $validServices ) ) {
            $this->cli->comment( "You didn't select an account type!" );
            $input = $this->cli->confirm( "Do you want to try again?" );

            if ( $input->confirmed() ) {
                return $this->promptAccountType();
            }

            $this->cli->comment( 'Account setup canceled.' );
            exit( 0 );
        }

        return $service;
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
                $account[ 'service' ],
                $account[ 'email' ],
                $account[ 'password' ] );
        }
        catch ( \Exception $e ) {
            $this->cli->error(
                sprintf(
                    "Unable to connect as '%s' to %s IMAP server: %s.",
                    $account[ 'email' ],
                    $account[ 'service' ],
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