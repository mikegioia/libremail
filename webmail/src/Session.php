<?php

namespace App;

use App\Exceptions\ClientException;

class Session
{
    const ERROR = 'error';
    const SUCCESS = 'success';

    const ALERT = 'alert';
    const NOTIFICATIONS = 'notifications';

    /**
     * Retrieve and optionally remove a session value.
     */
    public static function get(string $key, $default = null, $remove = true)
    {
        $value = $_SESSION[$key] ?? $default;

        if (true === $remove) {
            unset($_SESSION[$key]);
        }

        return $value;
    }

    /**
     * Add a notification message to the session. This is the
     * small black alert in the bottom left of a mailbox.
     */
    public static function alert(string $message, int $batchId = null)
    {
        $_SESSION[self::ALERT] = [
            'message' => $message,
            'batch_id' => $batchId
        ];
    }

    /**
     * Add a generic notification to the session. This will add
     * the message to a stack of messages, or create the new stack
     * if it doesn't exist.
     */
    public static function notify(
        string $message,
        string $type = self::SUCCESS,
        array $params = [])
    {
        $notifications = self::get(self::NOTIFICATIONS, [], false);
        $newNotification = array_merge($params, [
            'type' => $type,
            'message' => $message
        ]);

        // Add to the stack
        $notifications[] = $newNotification;

        $_SESSION[self::NOTIFICATIONS] = $notifications;
    }

    public static function getToken()
    {
        if (! isset($_SESSION['token'])) {
            $_SESSION['token'] = bin2hex(random_bytes(32));
        }

        return $_SESSION['token'];
    }

    /**
     * @throws ClientException
     */
    public static function validateToken()
    {
        $token = $_GET['token'] ?? null;

        if (! $token || ! isset($_SESSION['token'])) {
            throw new ClientException(
                'Missing token! Did you click the right link?');
        }

        if ($token !== $_SESSION['token']) {
            throw new ClientException(
                'Invalid token! Did your session expire?');
        }
    }
}
