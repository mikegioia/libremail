<?php

namespace App;

class Url
{
    /**
     * @var string Base url stem
     */
    private static $base;

    /**
     * @var string URL to redirect to
     */
    private static $redirectUrl;

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

    public static function makeGet(string $path, array $params = [])
    {
        return self::get($path, array_merge($_GET, $params));
    }

    public static function makeToken(string $path, array $params = [])
    {
        $params['token'] = Session::getToken();

        return self::get($path, $params);
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

    public static function compose()
    {
        return self::make('/compose');
    }

    public static function edit(int $outboxId)
    {
        return self::make('/compose/%s', $outboxId);
    }

    public static function preview(int $outboxId)
    {
        return self::make('/preview/%s', $outboxId);
    }

    public static function send()
    {
        return self::make('/send');
    }

    public static function outbox()
    {
        return self::make('/outbox');
    }

    public static function thread(int $folderId, int $threadId)
    {
        return self::make('/thread/%s/%s', $folderId, $threadId);
    }

    public static function redirect(string $path = '/', array $params = [], int $code = 303)
    {
        header('Location: '.self::get($path, $params), $code);

        die();
    }

    public static function redirectRaw(string $url, array $params = [], int $code = 303)
    {
        $url .= $params ? '?'.http_build_query($params) : '';

        header('Location: '.$url, $code);

        die();
    }

    public static function redirectBack($default = '/', int $code = 303)
    {
        return self::redirectRaw(self::getBackUrl($default), [], $code);
    }

    public static function postParam(string $key, $default = null)
    {
        return $_POST[$key] ?? $default;
    }

    public static function getParam(string $key, $default = null)
    {
        return $_GET[$key] ?? $default;
    }

    public static function getRedirectUrl()
    {
        return self::$redirectUrl;
    }

    public static function setRedirectUrl(string $url)
    {
        self::$redirectUrl = $url;
    }

    /**
     * @param string $urlId If set, needs to be a constant
     * @param int $folderId Container folder of the message
     * @param int $page Additional URL argument
     * @param string $action Action (constant) performed on the message
     */
    public static function actionRedirect(string $urlId, int $folderId, int $page, string $action)
    {
        if (self::getRedirectUrl()) {
            self::redirectRaw(self::getRedirectUrl());
        } elseif (INBOX === $urlId) {
            self::redirect();
        } elseif (STARRED === $urlId) {
            self::redirectRaw(self::starred($page ?: 1));
        } elseif (SEARCH === $urlId) {
            self::redirectRaw(self::getBackUrl('/search'));
        } elseif (THREAD === $urlId) {
            if (Actions::ARCHIVE === $action) {
                self::redirect();
            } elseif (Actions::MARK_UNREAD_FROM_HERE !== $action
                && Actions::MARK_UNREAD !== $action
                && Actions::ARCHIVE !== $action
                && Actions::DELETE !== $action
                && Actions::TRASH !== $action
                && Actions::SPAM !== $action
            ) {
                self::redirectRaw(self::thread($folderId, $page));
            } else {
                self::redirectRaw(self::folder($folderId));
            }
        } else {
            self::redirectRaw(self::folder($folderId, $page));
        }
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
