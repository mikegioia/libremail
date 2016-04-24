<?php

namespace App;

use Exception
  , App\Message\PidMessage
  , App\Message\TaskMessage
  , App\Message\ErrorMessage
  , App\Message\StatsMessage
  , App\Message\HealthMessage
  , App\Message\AbstractMessage
  , App\Message\NoAccountsMessage
  , App\Message\DiagnosticsMessage;

class Message
{
    // Valid messages
    const MESSAGE_PID = 'pid';
    const MESSAGE_TASK = 'task';
    const MESSAGE_STATS = 'stats';
    const MESSAGE_ERROR = 'error';
    const MESSAGE_HEALTH = 'health';
    const MESSAGE_NO_ACCOUNTS = 'no_accounts';
    const MESSAGE_DIAGNOSTICS = 'diagnostics';

    static private $validTypes = [
        self::MESSAGE_PID,
        self::MESSAGE_TASK,
        self::MESSAGE_STATS,
        self::MESSAGE_ERROR,
        self::MESSAGE_HEALTH,
        self::MESSAGE_NO_ACCOUNTS,
        self::MESSAGE_DIAGNOSTICS
    ];

    /**
     * Determines if a message is valid. This just means it's a JSON
     * string, that it has a "type" property, and that the type is one
     * of the internal message constants.
     * @param string $json
     * @return bool
     */
    static public function isValid( $json )
    {
        $message = @json_decode( $json );

        if ( ! $message
            || ! is_object( $message )
            || ! isset( $message->type )
            || ! in_array( $message->type, self::$validTypes ) )
        {
            return FALSE;
        }

        if ( @json_encode( $message ) !== $json ) {
            return FALSE;
        }

        return TRUE;
    }

    static public function send( AbstractMessage $message )
    {
        return self::writeJson( $message->toArray() );
    }

    static public function writeJson( $json )
    {
        fwrite( STDOUT, self::packJson( $json ) );
    }

    static public function packJson( $json )
    {
        $encoded = json_encode( $json );

        return sprintf(
            "%s%s%s",
            JSON_HEADER_CHAR,
            pack( "i", strlen( $encoded ) ),
            $encoded );
    }

    /**
     * Takes in a JSON object (should be validated first!) and
     * creates a new Message based off the type.
     * @param string $json
     * @return AbstractMessage
     */
    static public function make( $json )
    {
        if ( ! self::isValid( $json ) ) {
            throw new Exception(
                "Invalid message object passed to Message::make" );
        }

        $m = @json_decode( $json );

        switch ( $m->type ) {
            case self::MESSAGE_PID:
                return new PidMessage( $m->pid );
            case self::MESSAGE_TASK:
                return new TaskMessage( $m->data );
            case self::MESSAGE_STATS:
                return new StatsMessage(
                    $m->active,
                    $m->asleep,
                    $m->account,
                    $m->running,
                    $m->uptime,
                    $m->accounts );
            case self::MESSAGE_ERROR:
                return new ErrorMessage(
                    $m->error_type,
                    $m->message,
                    $m->suggestion );
            case self::MESSAGE_HEALTH:
                return new HealthMessage(
                    $m->tests,
                    $m->procs,
                    $m->no_accounts );
            case self::MESSAGE_NO_ACCOUNTS:
                return new NoAccountsMessage;
            case self::MESSAGE_DIAGNOSTICS:
                return new DiagnosticsMessage( $m->tests );
        }
    }
}