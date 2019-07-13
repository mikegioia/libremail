<?php

namespace App\Console;

use App\Console;

class DaemonConsole extends Console
{
    // Command line arguments
    public $help;
    public $sync;
    public $webServer;
    public $diagnostics;

    // These cannot be overwritten from the CLI
    public $daemon = false;
    public $interactive = false;
    public $databaseExists = false;

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
        $this->addArguments([
            'disable-sync' => [
                'longPrefix' => 'disable-sync',
                'description' => 'Start without the sync enabled',
                'noValue' => true
            ],
            'disable-webserver' => [
                'longPrefix' => 'disable-webserver',
                'description' => 'Start without the webserver enabled',
                'noValue' => true
            ],
            'help' => [
                'prefix' => 'h',
                'longPrefix' => 'help',
                'description' => 'Prints a usage statement',
                'noValue' => true
            ],
            'diagnostics' => [
                'prefix' => 'd',
                'longPrefix' => 'diagnostics',
                'description' => 'Runs a series of diagnostic tests',
                'noValue' => true
            ],
        ]);
    }

    /**
     * Store CLI arguments into class variables.
     */
    protected function parseArgs()
    {
        $this->cli->arguments->parse();

        $this->help = $this->cli->arguments->get('help');
        $this->sync = ! $this->cli->arguments->get('disable-sync');
        $this->diagnostics = $this->cli->arguments->get('diagnostics');
        $this->webServer = ! $this->cli->arguments->get('disable-webserver');

        if ($this->diagnostics) {
            $this->interactive = true;
        }
    }

    /**
     * Reads input values and saves to class variables.
     */
    protected function processArgs()
    {
        // If help is set, show the usage and exit
        if (true === $this->help) {
            $this->usage();
            exit(0);
        }
    }
}
