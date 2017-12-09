<?php

/**
 * Master class for handling all email inbox activities.
 */

namespace App;

use Fn;
use DateTime;
use Exception;
use App\Daemon;
use App\Message;
use Monolog\Logger;
use Pimple\Container;
use App\Inbox\Rollback;
use League\CLImate\CLImate;
use App\Console\InboxConsole;
use React\EventLoop\LoopInterface;
use Evenement\EventEmitter as Emitter;
use App\Exceptions\Terminate as TerminateException;
use App\Traits\GarbageCollection as GarbageCollectionTrait;

class Inbox
{
    private $cli;
    private $log;
    private $halt;
    private $daemon;
    private $emitter;
    private $rollback;
    private $interactive;

    // Events
    const EVENT_CHECK_HALT = 'check_halt';
    const EVENT_GARBAGE_COLLECT = 'garbage_collect';

    use GarbageCollectionTrait;

    public function __construct(
        Logger $log,
        CLImate $cli,
        LoopInterface $loop,
        InboxConsole $console,
        array $config )
    {
        $this->log = $log;
        $this->cli = $cli;
        $this->loop = $loop;
        $this->halt = FALSE;
        $this->config = $config;
        $this->console = $console;
        $this->rollback = $console->rollback;
    }

    public function run()
    {
        $this->setupEmitter();

        if ( $this->rollback ) {
            (new Rollback( $this->cli, $this->emitter ))->run();
            throw new TerminateException( "Finished rollback" );
        }

        $this->loop->addPeriodicTimer( 10, function ( $timer ) {
            echo "Timer hit\n";
        });
    }

    /**
     * Turns the halt flag on.
     * @throws TerminateException
     */
    public function halt()
    {
        $this->halt = TRUE;
    }

    /**
     * Attaches events to emitter for sub-classes.
     */
    private function setupEmitter()
    {
        if ( $this->emitter ) {
            return;
        }

        $this->emitter = new Emitter;

        $this->emitter->on( self::EVENT_CHECK_HALT, function () {
            $this->checkForHalt();
        });

        $this->emitter->on( self::EVENT_GARBAGE_COLLECT, function () {
            $this->gc();
        });
    }

    /**
     * Checks if a halt command has been issued. This is a command
     * to stop the process. We want to do is gracefull though so the
     * app checks in various places when it's save to halt.
     * @throws TerminateException
     */
    private function checkForHalt()
    {
        pcntl_signal_dispatch();

        if ( $this->halt === TRUE ) {
            //$this->disconnect();
            throw new TerminateException;
        }
    }
}