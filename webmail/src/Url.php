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
     */
    public static function actionRedirect($urlId, int $folderId, string $page)
    {
        if (INBOX === $urlId) {
            self::redirect('/');
        }

        if (STARRED === $urlId) {
            self::redirectRaw(self::starred($page ?: 1));
        }

        self::redirectRaw(self::folder($folderId, $page));
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
}
