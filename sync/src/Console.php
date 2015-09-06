<?php

namespace App;

use League\CLImate\CLImate as CLI;

class Console
{
    // CLImate instance
    private $cli;

    // Command line arguments
    public $help;
    public $verbose;
    public $background;
    public $interactive;

    function __construct()
    {
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
            'verbose' => [
                'prefix' => 'v',
                'longPrefix' => 'verbose',
                'description' => 'Verbose output',
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
}