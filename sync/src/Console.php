<?php

namespace App;

use League\CLImate\CLImate as CLI;
use League\CLImate\Argument\Parser;
use League\CLImate\Argument\Summary;

abstract class Console
{
    // Used in child classes
    public $diagnostics;

    // CLImate instance
    protected $cli;

    // Internal state
    private $parser;
    private $command;
    private $summary;
    private $arguments;

    const DESCRIPTION = 'LibreMail IMAP to SQL sync engine';

    public function __construct()
    {
        $this->cli = new CLI;
        $this->parser = new Parser;
        $this->summary = new Summary;
        $this->command = $this->parser->command();
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

    protected function addArguments(array $arguments)
    {
        $this->arguments = $arguments;
        $this->cli->arguments->add($arguments);
    }

    /**
     * Displays help information about the application.
     */
    protected function usage()
    {
        $this->banner();
        $this->cli->yellow('Usage:');
        $this->cli->out("  {$this->command} [options]")->br();
        $this->cli->yellow('Options:');

        list($options, $maxLen) = $this->getUsageOptions();

        foreach ($options as $args => $desc) {
            $this->cli
                ->green()->inline('  '.str_pad($args, $maxLen + 2, ' '))
                ->white($desc);
        }
    }

    private function banner()
    {
        $this->cli->out('+–––––––––––––––––––––––––––––––––––––––––––––––––––––––––––––+');
        $this->cli->out('   ,           ,      _     _ _              __  __       _ _  ');
        $this->cli->out('  /             \    | |   (_) |__  _ __ ___|  \/  | __ _(_) | ');
        $this->cli->out(" ((__-^^-,-^^-__))   | |   | | '_ \| '__/ _ \ |\/| |/ _` | | | ");
        $this->cli->out("  `-_---' `---_-'    | |___| | |_) | | |  __/ |  | | (_| | | | ");
        $this->cli->out("   `--|o` 'o|--'     |_____|_|_.__/|_|  \___|_|  |_|\__,_|_|_| ");
        $this->cli->out("      \  `  /                                                  ");
        $this->cli->out('       ): :(            The #1 GPL Email Application Suite     ');
        $this->cli->out('       :o_o:                 Version 1.0 – Mike Gioia          ');
        $this->cli->out('        "-"                                                    ');
        $this->cli->out('+–––––––––––––––––––––––––––––––––––––––––––––––––––––––––––––+');
        $this->cli->br();
    }

    private function getUsageOptions()
    {
        $maxLen = 0;
        $options = [];

        foreach ($this->arguments as $arg) {
            $string = '';

            if (isset($arg['prefix']) && $arg['prefix']) {
                $string .= '-'.$arg['prefix'].', ';
            }

            if (isset($arg['longPrefix']) && $arg['longPrefix']) {
                $string .= '--'.$arg['longPrefix'];
            } else {
                $string = rtrim(', ', $string);
            }

            $maxLen = max($maxLen, strlen($string));
            $options[$string] = $arg['description'] ?? '';
        }

        return [$options, $maxLen];
    }
}
