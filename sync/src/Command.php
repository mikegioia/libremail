<?php

namespace App;

use App\Exceptions\Error as ErrorException;
use App\Exceptions\BadCommand as BadCommandException;
use Symfony\Component\EventDispatcher\EventDispatcher as Emitter;

class Command
{
    private $emitter;
    // Command format: !COMMAND\n
    private $regexMatch = '/^(!{1}[A-Z]+\n{1})$/';
    private $regexExtract = '/^(?:!{1})([A-Z]+)(?:\n{1})$/';
    // Valid commands
    const STOP = 'STOP';
    const STATS = 'STATS';
    const START = 'START';
    const HEALTH = 'HEALTH';
    const RESTART = 'RESTART';

    public function __construct(Emitter $emitter = null)
    {
        $this->emitter = $emitter;
    }

    /**
     * Determines if a message is a valid command.
     * A command has a special format. It needs a header that
     * conforms with our spec: !COMMAND\n
     * Each command starts with a !, be all caps, and end with
     * a newline. As an example, restart would be: "!RESTART\n".
     *
     * @param string $message
     *
     * @return bool
     */
    public function isValid($message)
    {
        return preg_match($this->regexMatch, $message);
    }

    /**
     * Makes a new command string from a constant.
     *
     * @param string $command One of the command constants
     *
     * @return string
     */
    public function make($command)
    {
        return "!$command\n";
    }

    /**
     * Runs the given command. If the message is not a valid command
     * this will throw an exception.
     *
     * @param string $message
     *
     * @throws ErrorException
     * @throws BadCommandException
     */
    public function run($message)
    {
        if (! $this->emitter) {
            throw new ErrorException('Event emitter required for commands');
        }

        $matched = preg_match_all($this->regexExtract, $message, $results);

        if (! $matched
            || 2 !== count($results)
            || 1 !== count($results[1])
            || ! strlen($results[1][0])
        ) {
            throw new BadCommandException($message);
        }

        switch ($results[1][0]) {
            case self::STOP:
                $this->emitter->dispatch(EV_STOP_SYNC);
                break;
            case self::STATS:
                $this->emitter->dispatch(EV_POLL_STATS);
                break;
            case self::START:
                $this->emitter->dispatch(EV_START_SYNC);
                break;
            case self::RESTART:
                $this->emitter->dispatch(EV_CONTINUE_SYNC);
                break;
            case self::HEALTH:
                $this->emitter->dispatch(EV_POLL_DAEMON);
                break;
            default:
                throw new BadCommandException($message);
        }
    }

    public static function getMessage($command)
    {
        return sprintf("!%s\n", $command);
    }

    /**
     * Sends the command to the Daemon.
     */
    public static function send($command)
    {
        fwrite(STDOUT, $command);
        flush();
    }
}
