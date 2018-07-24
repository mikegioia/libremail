<?php

namespace App;

class Url
{
    private static $base;

    public static function setBase($base)
    {
        self::$base = $base;
    }

    public static function get($path, $params = [])
    {
        return self::$base.$path
            .($params ? '?'.http_build_query($params) : '');
    }

    public static function make($path, ...$parts)
    {
        return self::$base.vsprintf($path, $parts);
    }

    public static function starred($page)
    {
        return self::make('/starred/%s', $page);
    }

    public static function folder($folderId, $page = null)
    {
        return ($page)
            ? self::make('/folder/%s/%s', $folderId, $page)
            : self::make('/folder/%s', $folderId);
    }

    public static function redirect($path, $params = [], $code = 303)
    {
        header('Location: '.self::get($path, $params), $code);
        die();
    }

    public static function redirectRaw($url, $code = 303)
    {
        header('Location: '.$url, $code);
        die();
    }

    public static function postParam($key, $default = null)
    {
        return (isset($_POST[$key]))
            ? $_POST[$key]
            : $default;
    }

    public static function getParam($key, $default = null)
    {
        return (isset($_GET[$key]))
            ? $_GET[$key]
            : $default;
    }

    public static function actionRedirect($urlId, $folderId, $page)
    {
        if (INBOX === $urlId) {
            self::redirect('/');
        }

        if (STARRED === $urlId) {
            self::redirectRaw(self::starred($page ?: 1));
        }

        self::redirectRaw(self::folder($folderId, $page));
    }

    public static function getRefUrl($default = '/')
    {
        $ref = (isset($_SERVER['HTTP_REFERER']))
            ? $_SERVER['HTTP_REFERER']
            : '';

        // Only use this if we're on the same domain
        $len = strlen(self::$base);

        if (0 !== strncmp($ref, self::$base, $len)) {
            return self::get($default);
        }

        $path = htmlspecialchars(substr($ref, $len));

        return self::get($path);
    }
}
