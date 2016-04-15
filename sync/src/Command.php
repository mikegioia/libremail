<?php

namespace App;

use App\Exceptions\Error as ErrorException
  , App\Exceptions\BadCommand as BadCommandException
  , Symfony\Component\EventDispatcher\EventDispatcher as Emitter;

class Command
{
    private $emitter;
    // Command format: !COMMAND\n
    private $regexMatch = '/^(!{1}[A-Z]+\n{1})$/';
    private $regexExtract = '/^(?:!{1})([A-Z]+)(?:\n{1})$/';
    // Valid commands
    const STATS = 'STATS';
    const HEALTH = 'HEALTH';
    const RESTART = 'RESTART';

    public function __construct( Emitter $emitter = NULL )
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
     * a newline. As an example, restart would be: "!RESTART\n"
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
        if ( ! $this->emitter ) {
            throw new ErrorException(
                "A command was attempted to be run in a context ".
                "without an Event Emitter." );
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
                $this->emitter->dispatch( EV_POLL_STATS );
                break;
            case self::RESTART:
                $this->emitter->dispatch( EV_CONTINUE_SYNC );
                break;
            case self::HEALTH:
                $this->emitter->dispatch( EV_POLL_DAEMON );
            default:
                throw new BadCommandException( $message );
        }
    }

    public function 

    static public function getMessage( $command )
    {
        return sprintf( "!%s\n", $command );
    }
}