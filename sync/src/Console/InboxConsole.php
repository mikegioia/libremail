<?php

namespace App\Console;

use App\Console;

class InboxConsole extends Console
{
    // Command line arguments
    public $help;
    public $daemon;
    public $rollback;
    public $background;
    public $interactive;

    public function __construct()
    {
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
            'daemon' => [
                'prefix' => 'e',
                'longPrefix' => 'daemon',
                'description' => 'Runs server in daemon mode',
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
            'rollback' => [
                'prefix' => 'r',
                'longPrefix' => 'rollback',
                'description' => 'Reverts all local changes that were made',
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
        $this->daemon = $this->cli->arguments->get( 'daemon' );
        $this->rollback = $this->cli->arguments->get( 'rollback' );
        $this->background = $this->cli->arguments->get( 'background' );
        $this->interactive = $this->cli->arguments->get( 'interactive' );

        // If background is set, turn off interactive
        if ( $this->background === TRUE || $this->rollback === TRUE ) {
            $this->interactive = FALSE;
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

        // If we're in interactive mode, sent the sync message
        if ( $this->interactive === TRUE ) {
            $this->cli->info( "Starting socket server in interactive mode" );
        }

        // If we're in rolling back changes
        if ( $this->rollback === TRUE ) {
            $this->cli->warning(
                "Rollback mode enabled. This script will halt when finished." );
        }
    }
}