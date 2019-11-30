<?php

namespace App;

use App\Exceptions\ClientException;

class Session
{
    const ERROR = 'error';
    const SUCCESS = 'success';

    const ALERT = 'alert';
    const FORM_DATA = 'form_data';
    const FORM_ERRORS = 'form_errors';
    const NOTIFICATIONS = 'notifications';

    const FLAG_HIDE_JS_ALERT = 'hide_js_alert';

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
        array $params = []
    ) {
        $notifications = self::get(self::NOTIFICATIONS, [], false);
        $newNotification = array_merge($params, [
            'type' => $type,
            'message' => $message
        ]);

        // Add to the stack
        $notifications[] = $newNotification;

        $_SESSION[self::NOTIFICATIONS] = $notifications;
    }

    public static function hasErrors()
    {
        $notifications = self::get(self::NOTIFICATIONS, [], false);

        foreach ($notifications as $notification) {
            if (self::ERROR === $notification['type']) {
                return true;
            }
        }

        return false;
    }

    /**
     * Stores a flag in the session.
     *
     * @throws ClientException
     */
    public static function flag(string $flag, $value)
    {
        $allowed = [
            self::FLAG_HIDE_JS_ALERT
        ];

        if (! in_array($flag, $allowed)) {
            throw new ClientException('Invalid flag specified');
        }

        $_SESSION[$flag] = $value;
    }

    /**
     * Returns the value for a flag, if set.
     *
     * @return mixed
     */
    public static function getFlag(string $flag, $default = null)
    {
        return $_SESSION[$flag] ?? $default;
    }

    /**
     * Stores a form's data for re-population in a view.
     */
    public static function formData(array $data)
    {
        $_SESSION[self::FORM_DATA] = $data;
    }

    /**
     * Stores validation errors for a form.
     */
    public static function formErrors(array $errors)
    {
        $_SESSION[self::FORM_ERRORS] = $errors;
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
                'Missing token! Did you click the right link?'
            );
        }

        if ($token !== $_SESSION['token']) {
            throw new ClientException(
                'Invalid token! Did your session expire?'
            );
        }
    }
}
