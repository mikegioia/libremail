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

    public static function setBase(string $base): void
    {
        self::$base = $base;
    }

    /**
     * @return string
     */
    public static function get(string $path, array $params = [])
    {
        return self::$base.$path
            .($params ? '?'.http_build_query($params) : '');
    }

    /**
     * @return string
     */
    public static function make(string $path, ...$parts)
    {
        return self::$base.vsprintf($path, $parts);
    }

    /**
     * @return string
     */
    public static function makeGet(string $path, array $params = [])
    {
        return self::get($path, array_merge($_GET, $params));
    }

    /**
     * @return string
     */
    public static function makeToken(string $path, array $params = [])
    {
        $params['token'] = Session::getToken();

        return self::get($path, $params);
    }

    /**
     * @return string
     */
    public static function starred(int $page)
    {
        return self::make('/starred/%s', $page);
    }

    /**
     * @return string
     */
    public static function folder(int $folderId, int $page = null)
    {
        return $page
            ? self::make('/folder/%s/%s', $folderId, $page)
            : self::make('/folder/%s', $folderId);
    }

    /**
     * @return string
     */
    public static function compose()
    {
        return self::make('/compose');
    }

    /**
     * @return string
     */
    public static function edit(int $outboxId)
    {
        return self::make('/compose/%s', $outboxId);
    }

    /**
     * @return string
     */
    public static function preview(int $outboxId)
    {
        return self::make('/preview/%s', $outboxId);
    }

    /**
     * @return string
     */
    public static function send()
    {
        return self::make('/send');
    }

    /**
     * @return string
     */
    public static function update()
    {
        return self::make('/update');
    }

    /**
     * @return string
     */
    public static function outbox()
    {
        return self::make('/outbox');
    }

    /**
     * @return string
     */
    public static function deleteOutbox()
    {
        return self::make('/outbox/delete');
    }

    /**
     * @return string
     */
    public static function reply(int $parentId)
    {
        return self::make('/reply/%s', $parentId);
    }

    /**
     * @return string
     */
    public static function replyAll(int $parentId)
    {
        return self::make('/replyall/%s', $parentId);
    }

    /**
     * @return string
     */
    public static function thread(int $folderId, int $threadId)
    {
        return self::make('/thread/%s/%s', $folderId, $threadId);
    }

    public static function redirect(string $path = '/', array $params = [], int $code = 303): void
    {
        header('Location: '.self::get($path, $params), true, $code);

        exit();
    }

    public static function redirectRaw(string $url, array $params = [], int $code = 303): void
    {
        $url .= $params ? '?'.http_build_query($params) : '';

        header('Location: '.$url, true, $code);

        exit();
    }

    public static function redirectBack(string $default = '/', int $code = 303): void
    {
        self::redirectRaw(self::getBackUrl($default), [], $code);
    }

    /**
     * @return mixed
     */
    public static function postParam(string $key, $default = null)
    {
        return isset($_POST[$key])
            ? ($_POST[$key] ?: $default)
            : $default;
    }

    /**
     * @return mixed
     */
    public static function getParam(string $key, $default = null)
    {
        return isset($_GET[$key])
            ? ($_GET[$key] ?: $default)
            : $default;
    }

    /**
     * @return string
     */
    public static function getRedirectUrl()
    {
        return self::$redirectUrl;
    }

    public static function setRedirectUrl(string $url): void
    {
        self::$redirectUrl = $url;
    }

    /**
     * @param string $urlId If set, needs to be a constant
     * @param int $folderId Container folder of the message
     * @param int $page Additional URL argument
     * @param string $action Action (constant) performed on the message
     */
    public static function actionRedirect(
        string $urlId,
        int $folderId,
        int $page,
        string $action
    ): void {
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

    /**
     * @return string
     */
    public static function getCurrentUrl()
    {
        return self::$base.($_SERVER['REQUEST_URI'] ?? '/');
    }

    /**
     * @return string
     */
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

    /**
     * @return string
     */
    public static function getBackUrl(string $default = '/')
    {
        $refUrl = self::getRefUrl($default);
        $currentUrl = self::getCurrentUrl();

        return $refUrl === $currentUrl
            ? self::get($default)
            : $refUrl;
    }
}
