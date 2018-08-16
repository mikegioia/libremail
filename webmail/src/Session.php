<?php

namespace App;

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
}
