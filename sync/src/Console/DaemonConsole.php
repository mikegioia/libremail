<?php

namespace App\Console;

use App\Console;

class DaemonConsole extends Console
{
    // Command line arguments
    public $help;
    public $sync;
    public $webServer;

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
            'disable-sync' => [
                'longPrefix' => 'disable-sync',
                'description' => 'Start without the sync enabled',
                'noValue' => TRUE
            ],
            'disable-webserver' => [
                'longPrefix' => 'disable-webserver',
                'description' => 'Start without the webserver enabled',
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
     * Store CLI arguments into class variables.
     */
    protected function parseArgs()
    {
        $this->cli->arguments->parse();
        $this->help = $this->cli->arguments->get( 'help' );
        $this->sync = ! $this->cli->arguments->get( 'disable-sync' );
        $this->webServer = ! $this->cli->arguments->get( 'disable-webserver' );
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
    }
}