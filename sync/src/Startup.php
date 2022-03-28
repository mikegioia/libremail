<?php

namespace App;

use App\Message\PidMessage;
use App\Model\Account as AccountModel;
use Pimple\Container;

/**
 * Runs at the start of the application and performs various
 * initializing and testing before the app can run.
 */
class Startup
{
    private $pid;
    private $log;
    private $console;

    public function __construct(Container $di)
    {
        $this->pid = getmypid();
        $this->console = $di['console'];
        $this->log = $di['log']->getLogger();
    }

    public function run()
    {
        $this->log->debug('Starting sync engine');
        $this->log->notice('Process ID: '.$this->pid);

        if ($this->console->daemon) {
            Message::send(new PidMessage($this->pid));
        }

        $this->checkIfAccountsExist();

        if (true === $this->console->sleep) {
            $this->log->warn(
                'Sleep mode enabled. I will only respond to signals.');
            $this->log->warn(
                "Run 'kill -SIGQUIT ".$this->pid."' to exit!");
        }
    }

    public function runServer()
    {
        $this->log->debug('Starting socket server');
        $this->log->notice('Process ID: '.$this->pid);

        if ($this->console->daemon) {
            Message::send(new PidMessage($this->pid));
        }
    }

    public function runLibreMail()
    {
        $this->log->debug('Starting LibreMail');
        $this->log->notice('Process ID: '.$this->pid);
    }

    /**
     * Check if any accounts exist in the database. If not, and
     * if we're in interactive mode, then prompt the user to add
     * one.
     */
    private function checkIfAccountsExist()
    {
        $accountModel = new AccountModel;
        $accounts = $accountModel->getActive();

        if (! $accounts) {
            if ($this->console->interactive) {
                $this->console->createNewAccount();
            }
        }
    }
}
