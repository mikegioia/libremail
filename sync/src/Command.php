<?php

namespace App;

use App\Exceptions\Error as ErrorException
  , App\Exceptions\BadCommand as BadCommandException
  , Symfony\Component\EventDispatcher\EventDispatcher as Emitter;

class Command
{
    private $log;
    private $emitter;
    private $regexMatch = '/^(!{1}[A-Z]+\n{1})$/';
    private $regexExtract = '/^(?:!{1})([A-Z]+)(?:\n{1})$/';

    const STATS = 'STATS';
    const RESTART = 'RESTART';

    public function __construct( Emitter $emitter = NULL, Log $log = NULL )
    {
        $this->emitter = $emitter;

        if ( $log ) {
            $this->log = $log->getLogger();
        }
    }

    /**
     * Determines if a message is a valid command.
     * A command has a special format. It needs a header that
     * conforms with our spec: !COMMAND\n
     * Each command starts with a !, be all caps, and end with
     * a newline. As an example, restart would be: !RESTART.
     * @param string $message
     * @return boolean
     */
    public function isValid( $message )
    {
        return preg_match( $this->regexMatch, $message );
    }

    /**
     * Runs the given command. If the message is not a valid command
     * this will throw an exception.
     * @param string $message
     * @throws ErrorException
     * @throws BadCommandException
     */
    public function run( $message )
    {
        if ( ! $this->emitter || ! $this->log ) {
            throw new ErrorException(
                "A command was attempted to be run in a context ".
                "without an Event Emitter or Logger." );
        }

        $matched = preg_match_all( $this->regexExtract, $message, $results );

        if ( ! $matched
            || count( $results ) !== 2
            || count( $results[ 1 ] ) !== 1
            || ! strlen( $results[ 1 ][ 0 ] ) )
        {
            throw new BadCommandException( $message );
        }

        switch ( $results[ 1 ][ 0 ] ) {
            case self::STATS:
                $this->log->info( "This would emit an event to get stats (SIGUSR2)" );
                break;
            case self::RESTART:
                $this->log->info( "This would emit an event to restart (SIGCONT)" );
                break;
            default:
                throw new BadCommandException( $message );
        }
    }
}