<?php

namespace App;

use League\CLImate\CLImate as CLI;

abstract class Console
{
    // Used in child classes
    public $diagnostics;

    // CLImate instance
    protected $cli;

    const DESCRIPTION = 'LibreMail IMAP to SQL sync engine';

    public function __construct()
    {
        $this->cli = new CLI;
        $this->cli->description(self::DESCRIPTION);

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
