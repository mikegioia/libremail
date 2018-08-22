<?php

namespace App;

use App\Exceptions\ClientException;

class Session
{
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
     * Add a notification message to the session.
     */
    public static function notify(string $message, int $batchId = null)
    {
        $_SESSION['alert'] = [
            'message' => $message,
            'batch_id' => $batchId
        ];
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
