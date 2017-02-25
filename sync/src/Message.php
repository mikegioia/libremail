<?php

namespace App;

use Fn
  , Exception
  , Monolog\Logger
  , App\Server\StatsServer
  , App\Message\PidMessage
  , App\Message\TaskMessage
  , App\Message\ErrorMessage
  , App\Message\StatsMessage
  , App\Message\HealthMessage
  , App\Message\AccountMessage
  , App\Message\AbstractMessage
  , App\Message\NoAccountsMessage
  , App\Message\DiagnosticsMessage
  , App\Message\NotificationMessage
  , App\Exceptions\Validation as ValidationException;

class Message
{
    // Valid messages
    const PID = 'pid';
    const TASK = 'task';
    const STATS = 'stats';
    const ERROR = 'error';
    const HEALTH = 'health';
    const ACCOUNT = 'account';
    const NO_ACCOUNTS = 'no_accounts';
    const DIAGNOSTICS = 'diagnostics';
    const ACCOUNT_INFO = 'account_info';
    const NOTIFICATION = 'notification';

    // Injected during service registration
    static protected $log;

    static private $validTypes = [
        self::PID,
        self::TASK,
        self::STATS,
        self::ERROR,
        self::HEALTH,
        self::ACCOUNT,
        self::NO_ACCOUNTS,
        self::DIAGNOSTICS,
        self::ACCOUNT_INFO,
        self::NOTIFICATION
    ];

    static function setLog( Logger $log )
    {
        static::$log = $log;
    }

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

    /**
     * If a server is provided, broadcast the message directly.
     */
    static public function send( AbstractMessage $message, StatsServer $server = NULL )
    {
        if ( $server ) {
            $server->broadcast( json_encode( $message->toArray() ) );
            return;
        }

        return self::writeJson( $message->toArray() );
    }

    static public function writeJson( $json )
    {
        fwrite( STDOUT, self::packJson( $json ) );
        flush();
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
     * @throws Exception
     * @return AbstractMessage
     */
    static public function make( $json )
    {
        if ( ! self::isValid( $json ) ) {
            $error = "Invalid message object passed to Message::make";
            self::$log->error( $error );
            throw new Exception( $error );
        }

        $m = @json_decode( $json );

        // Check that message contains the required fields.
        try {
            Fn\expects( $m )->toHave([ 'type' ]);

            switch ( $m->type ) {
                case self::PID:
                    Fn\expects( $m )->toHave([ 'pid' ]);
                    return new PidMessage( $m->pid );
                case self::TASK:
                    Fn\expects( $m )->toHave([ 'task', 'data' ]);
                    return new TaskMessage( $m->task, $m->data );
                case self::STATS:
                    Fn\expects( $m )->toHave([
                        'active', 'asleep', 'account', 'running',
                        'uptime', 'accounts'
                    ]);
                    return new StatsMessage(
                        $m->active,
                        $m->asleep,
                        $m->account,
                        $m->running,
                        $m->uptime,
                        $m->accounts );
                case self::ERROR:
                    Fn\expects( $m )->toHave([
                        'error_type', 'message', 'suggestion'
                    ]);
                    return new ErrorMessage(
                        $m->error_type,
                        $m->message,
                        $m->suggestion );
                case self::HEALTH:
                    Fn\expects( $m )->toHave([
                        'tests', 'procs', 'no_accounts'
                    ]);
                    return new HealthMessage(
                        $m->tests,
                        $m->procs,
                        $m->no_accounts );
                case self::ACCOUNT:
                    Fn\expects( $m )->toHave([ 'updated', 'email' ]);
                    return new AccountMessage( $m->updated, $m->email );
                case self::NO_ACCOUNTS:
                    return new NoAccountsMessage;
                case self::ACCOUNT_INFO:
                    Fn\expects( $m )->toHave([ 'account' ]);
                    return new AccountInfoMessage( $m->account );
                case self::NOTIFICATION:
                    Fn\expects( $m )->toHave([ 'status', 'message' ]);
                    return new NotificationMessage( $m->status, $m->message );
                case self::DIAGNOSTICS:
                    Fn\expects( $m )->toHave([ 'tests' ]);
                    return new DiagnosticsMessage( $m->tests );
            }
        }
        catch ( ValidationException $e ) {
            self::$log->error( $e->getMessage() );
            throw new Exception( $e->getMessage() );
        }

        $error = "Invalid message type passed to Message::make";
        self::$log->error( $error );
        throw new Exception( $error );
    }

    /**
     * Generic helper for writing to the server log file.
     * @param string $message
     */
    static public function debug( $message )
    {
        self::$log->debug( $message );
    }
}