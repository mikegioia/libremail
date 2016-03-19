<?php

namespace App\Console;

use App\Console;

class ServerConsole extends Console
{
    // Command line arguments
    public $daemon;
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
            'interactive' => [
                'prefix' => 'i',
                'longPrefix' => 'interactive',
                'description' => 'Interact with the CLI; ignored if background set',
                'defaultValue' => TRUE,
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
        $this->background = $this->cli->arguments->get( 'background' );
        $this->interactive = $this->cli->arguments->get( 'interactive' );

        // If background is set, turn off interactive
        if ( $this->background === TRUE ) {
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
    }
}