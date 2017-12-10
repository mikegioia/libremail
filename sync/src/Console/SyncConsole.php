<?php

namespace App\Console;

use Exception;
use App\Console;
use App\Diagnostics;
use App\Sync\Rollback;
use App\Model\Account as AccountModel;
use App\Model\Migration as MigrationModel;

class SyncConsole extends Console
{
    // Dependencies
    private $config;

    // Command line arguments
    public $help;
    public $quick;
    public $sleep;
    public $create;
    public $folder;
    public $daemon;
    public $verbose;
    public $updatedb;
    public $rollback;
    public $threading;
    public $background;
    public $diagnostics;
    public $interactive;

    public function __construct( Array $config )
    {
        $this->config = $config;

        parent::__construct();
    }

    /**
     * Initializes the accepted arguments and saves them as class
     * properties accessible publicly.
     */
    protected function setupArgs()
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
            'diagnostics' => [
                'prefix' => 'd',
                'longPrefix' => 'diagnostics',
                'description' => 'Runs a series of diagnostic tests',
                'noValue' => TRUE
            ],
            'daemon' => [
                'prefix' => 'e',
                'longPrefix' => 'daemon',
                'description' => 'Runs sync in daemon mode',
                'noValue' => TRUE
            ],
            'folder' => [
                'prefix' => 'f',
                'longPrefix' => 'folder',
                'description' => 'Sync the selected folder'
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
            'quick' => [
                'prefix' => 'q',
                'longPrefix' => 'quick',
                'description' => 'Skips downloading attachments and message content',
                'noValue' => TRUE
            ],
            'rollback' => [
                'prefix' => 'r',
                'longPrefix' => 'rollback',
                'description' => 'Reverts all local changes that were made',
                'noValue' => TRUE
            ],
            'sleep' => [
                'prefix' => 's',
                'longPrefix' => 'sleep',
                'description' => 'Runs with sync disabled; useful for signal testing',
                'noValue' => TRUE
            ],
            'threading' => [
                'prefix' => 't',
                'longPrefix' => 'threading',
                'description' => 'Runs only the threading operation, sync is disabled',
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
     * Store CLI arguments into class variables.
     */
    protected function parseArgs()
    {
        $this->cli->arguments->parse();
        $this->help = $this->cli->arguments->get( 'help' );
        $this->quick = $this->cli->arguments->get( 'quick' );
        $this->sleep = $this->cli->arguments->get( 'sleep' );
        $this->create = $this->cli->arguments->get( 'create' );
        $this->folder = $this->cli->arguments->get( 'folder' );
        $this->daemon = $this->cli->arguments->get( 'daemon' );
        $this->verbose = $this->cli->arguments->get( 'verbose' );
        $this->updatedb = $this->cli->arguments->get( 'updatedb' );
        $this->rollback = $this->cli->arguments->get( 'rollback' );
        $this->threading = $this->cli->arguments->get( 'threading' );
        $this->background = $this->cli->arguments->get( 'background' );
        $this->diagnostics = $this->cli->arguments->get( 'diagnostics' );
        $this->interactive = $this->cli->arguments->get( 'interactive' );

        // If background is set, turn off interactive
        if ( $this->background === TRUE ) {
            $this->interactive = FALSE;
        }

        // If create or update DB is set turn interactive on
        if ( $this->sleep === TRUE
            || $this->create === TRUE
            || $this->updatedb === TRUE
            || $this->rollback === TRUE
            || $this->threading === TRUE
            || $this->diagnostics === TRUE )
        {
            $this->interactive = TRUE;
        }
    }

    /**
     * Reads input values and saves to class variables.
     */
    protected function processArgs()
    {
        // If help is set, show the usage and exit
        if ( $this->help === TRUE ) {
            $this->cli->usage();
            exit( 0 );
        }

        // If create is set, skip right to the account creation
        if ( $this->create === TRUE ) {
            $this->cli->info( "Creating a new IMAP account" );
            $this->promptAccountInfo();
            exit( 0 );
        }

        // If updatedb is set, just run the migration script
        if ( $this->updatedb === TRUE ) {
            $migrate = new MigrationModel;
            $migrate->run();
            exit( 0 );
        }

        // If we're in rolling back changes, run it now
        if ( $this->rollback === TRUE ) {
            $this->cli->warning(
                "Rollback mode enabled. This script will halt when finished." );
            $rollback = new Rollback( $this->cli );
            $rollback->run();
            exit( 0 );
        }

        // If we're in interactive mode, send the sync message
        if ( $this->interactive === TRUE ) {
            $this->cli->info( "Starting IMAP sync in interactive mode" );
        }

        // Send other info messages
        if ( $this->threading === TRUE ) {
            $this->cli->info( "Message sync skipped, only threading activated" );
        }
        elseif ( $this->quick === TRUE ) {
            $this->cli->info(
                "Attachments and message contents will not be downloaded" );
        }
    }

    /**
     * Asks the user for account information to set up a new account.
     */
    public function createNewAccount()
    {
        if ( ! $this->interactive || $this->sleep ) {
            return NULL;
        }

        $this->cli->info( "No active email accounts exist in the database." );
        $input = $this->cli->confirm( "Do you want to add one now?" );

        if ( ! $input->confirmed() ) {
            return FALSE;
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
        list(
            $newAccount[ 'service' ],
            $newAccount[ 'imap_host' ],
            $newAccount[ 'imap_port' ] ) = $this->promptAccountType();
        $newAccount[ 'email' ] = $this->promptEmail();
        $newAccount[ 'password' ] = $this->promptPassword();

        // Connection settings worked, save to SQL
        try {
            // Test connection before adding
            $this->testConnection( $newAccount );
            $accountModel = new AccountModel( $newAccount );
            $accountModel->save( [], TRUE );
        }
        catch ( Exception $e ) {
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

        $service = strtolower( $service );
        $port = $this->config[ 'email' ][ $service ][ 'port' ];

        // If the service was 'other' we need to ask them for the host.
        if ( $service === 'other' ) {
            $host = $this->cli->input( 'Host address (like imap.host.com):' )->prompt();
        }
        else {
            $host = $this->config[ 'email' ][ $service ][ 'host' ];
        }

        return [ $service, $host, $port ];
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
     */
    private function testConnection( $account )
    {
        try {
            Diagnostics::testImapConnection( $account );
        }
        catch ( Exception $e ) {
            $this->cli->error(
                sprintf(
                    "Unable to connect as '%s' to %s:%s IMAP server: %s.",
                    $account[ 'email' ],
                    $account[ 'imap_host' ],
                    $account[ 'imap_port' ],
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