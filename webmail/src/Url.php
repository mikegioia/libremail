<?php

namespace App;

class Url
{
    private static $base;

    public static function setBase(string $base)
    {
        self::$base = $base;
    }

    public static function get(string $path, array $params = [])
    {
        return self::$base.$path
            .($params ? '?'.http_build_query($params) : '');
    }

    public static function make(string $path, ...$parts)
    {
        return self::$base.vsprintf($path, $parts);
    }

    public static function starred(string $page)
    {
        return self::make('/starred/%s', $page);
    }

    public static function folder(int $folderId, string $page = null)
    {
        return $page
            ? self::make('/folder/%s/%s', $folderId, $page)
            : self::make('/folder/%s', $folderId);
    }

    public static function thread(int $folderId, int $threadId)
    {
        return self::make('/thread/%s/%s', $folderId, $threadId);
    }

    public static function redirect(string $path, array $params = [], int $code = 303)
    {
        header('Location: '.self::get($path, $params), $code);
        die();
    }

    public static function redirectRaw(string $url, int $code = 303)
    {
        header('Location: '.$url, $code);
        die();
    }

    public static function postParam(string $key, $default = null)
    {
        return $_POST[$key] ?? $default;
    }

    public static function getParam(string $key, $default = null)
    {
        return $_GET[$key] ?? $default;
    }

    /**
     * @param string|null $urlId If set, needs to be a constant
     * @param int|string $page Additional URL argument
     */
    public static function actionRedirect($urlId, int $folderId, $page, string $action)
    {
        if (INBOX === $urlId) {
            self::redirect('/');
        }

        if (STARRED === $urlId) {
            self::redirectRaw(self::starred($page ?: 1));
        }

        if (THREAD === $urlId) {
            if (Actions::MARK_UNREAD !== $action
                && Actions::DELETE !== $action
                && Actions::SPAM !== $action)
            {
                self::redirectRaw(self::thread($folderId, $page));
            } else {
                self::redirectRaw(self::folder($folderId));
            }
        }

        self::redirectRaw(self::folder($folderId, $page));
    }

    public static function getCurrentUrl()
    {
        return self::$base.($_SERVER['REQUEST_URI'] ?? '/');
    }

    public static function getRefUrl(string $default = '/')
    {
        $ref = $_SERVER['HTTP_REFERER'] ?? '';
        // Only use this if we're on the same domain
        $len = strlen(self::$base);

        if (0 !== strncmp($ref, self::$base, $len)) {
            return self::get($default);
        }

        $path = htmlspecialchars(substr($ref, $len));

        return self::get($path);
    }

    public static function getBackUrl(string $default = '/')
    {
        $refUrl = self::getRefUrl($default);
        $currentUrl = self::getCurrentUrl();

        return $refUrl === $currentUrl
            ? self::get($default)
            : $refUrl;
    }
}
