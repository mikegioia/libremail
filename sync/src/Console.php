<?php

namespace App;

use League\CLImate\CLImate as CLI;

abstract class Console
{
    // CLImate instance
    protected $cli;
    // Used in child classes
    public $diagnostics;

    public function __construct()
    {
        $this->cli = new CLI;
        $this->cli->description( "LibreMail IMAP to SQL sync engine" );
        $this->setupArgs();
        $this->parseArgs();
    }

    public function init()
    {
        $this->processArgs();
    }

    public function getCLI()
    {
        return $this->cli;
    }

    /**
     * Initializes the accepted arguments and saves them as class
     * properties accessible publicly.
     */
    abstract protected function setupArgs();

    /**
     * Store CLI arguments into class variables.
     */
    abstract protected function parseArgs();

    /**
     * Reads input values and saves to class variables.
     */
    abstract protected function processArgs();
}