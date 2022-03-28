<?php

namespace App;

use App\Exceptions\Validation as ValidationException;
use App\Message\AbstractMessage;
use App\Message\AccountMessage;
use App\Message\DiagnosticsMessage;
use App\Message\ErrorMessage;
use App\Message\HealthMessage;
use App\Message\NoAccountsMessage;
use App\Message\NotificationMessage;
use App\Message\PidMessage;
use App\Message\StatsMessage;
use App\Message\TaskMessage;
use App\Server\StatsServer;
use Exception;
use Monolog\Logger;

class Message
{
    // Valid messages
    public const PID = 'pid';
    public const TASK = 'task';
    public const STATS = 'stats';
    public const ERROR = 'error';
    public const HEALTH = 'health';
    public const ACCOUNT = 'account';
    public const NO_ACCOUNTS = 'no_accounts';
    public const DIAGNOSTICS = 'diagnostics';
    public const ACCOUNT_INFO = 'account_info';
    public const NOTIFICATION = 'notification';

    // Injected during service registration
    protected static $log;

    private static $validTypes = [
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

    public static function setLog(Logger $log)
    {
        static::$log = $log;
    }

    /**
     * Determines if a message is valid. This just means it's a JSON
     * string, that it has a "type" property, and that the type is one
     * of the internal message constants.
     *
     * @return bool
     */
    public static function isValid(string $json)
    {
        $message = @json_decode($json);

        if (! $message
            || ! is_object($message)
            || ! isset($message->type)
            || ! in_array($message->type, self::$validTypes)
        ) {
            return false;
        }

        if (@json_encode($message) !== $json) {
            return false;
        }

        return true;
    }

    /**
     * If a server is provided, broadcast the message directly.
     */
    public static function send(AbstractMessage $message, StatsServer $server = null)
    {
        if ($server) {
            $server->broadcast(json_encode($message->toArray()));
        } else {
            self::writeJson($message->toArray());
        }
    }

    public static function writeJson(array $json)
    {
        fwrite(STDOUT, self::packJson($json));
        flush();
    }

    public static function packJson(array $json)
    {
        $encoded = json_encode($json);

        return sprintf(
            '%s%s%s',
            JSON_HEADER_CHAR,
            pack('i', strlen($encoded)),
            $encoded
        );
    }

    /**
     * Takes in a JSON object (should be validated first!) and
     * creates a new Message based off the type.
     *
     * @throws Exception
     *
     * @return AbstractMessage
     */
    public static function make(string $json)
    {
        if (! self::isValid($json)) {
            $error = 'Invalid message object passed to Message::make';
            self::$log->error($error);

            throw new Exception($error);
        }

        $m = @json_decode($json);

        // Check that message contains the required fields.
        try {
            Util::expects($m)->toHave(['type']);

            switch ($m->type) {
                case self::PID:
                    Util::expects($m)->toHave(['pid']);

                    return new PidMessage((int) $m->pid);
                case self::TASK:
                    Util::expects($m)->toHave(['task', 'data']);

                    return new TaskMessage($m->task, (array) $m->data);
                case self::STATS:
                    Util::expects($m)->toHave([
                        'active', 'asleep', 'account', 'running',
                        'uptime', 'accounts'
                    ]);

                    return new StatsMessage(
                        (string) $m->active,
                        (bool) $m->asleep,
                        (string) $m->account,
                        (bool) $m->running,
                        (int) $m->uptime,
                        (array) $m->accounts
                    );
                case self::ERROR:
                    Util::expects($m)->toHave([
                        'error_type', 'message', 'suggestion'
                    ]);

                    return new ErrorMessage(
                        $m->error_type,
                        $m->message,
                        $m->suggestion
                    );
                case self::HEALTH:
                    Util::expects($m)->toHave([
                        'tests', 'procs', 'no_accounts'
                    ]);

                    return new HealthMessage(
                        (array) $m->tests,
                        (array) $m->procs,
                        (bool) $m->no_accounts
                    );
                case self::ACCOUNT:
                    Util::expects($m)->toHave(['updated', 'email']);

                    return new AccountMessage((bool) $m->updated, $m->email);
                case self::NO_ACCOUNTS:
                    return new NoAccountsMessage;
                case self::ACCOUNT_INFO:
                    Util::expects($m)->toHave(['account']);

                    return new AccountInfoMessage($m->account);
                case self::NOTIFICATION:
                    Util::expects($m)->toHave(['status', 'message']);

                    return new NotificationMessage($m->status, $m->message);
                case self::DIAGNOSTICS:
                    Util::expects($m)->toHave(['tests']);

                    return new DiagnosticsMessage((array) $m->tests);
            }
        } catch (ValidationException $e) {
            self::$log->error($e->getMessage());

            throw new Exception($e->getMessage());
        }

        $error = 'Invalid message type passed to Message::make';

        self::$log->error($error);

        throw new Exception($error);
    }

    /**
     * Generic helper for writing to the server log file.
     */
    public static function debug(string $message)
    {
        self::$log->debug($message);
    }
}
