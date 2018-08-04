<?php

/**
 * Simple view rendering class. Uses output buffer and native
 * PHP templates.
 */

namespace App;

use DateTime;
use Exception;
use DateTimeZone;

class View
{
    private $data = [];

    private static $timezone;

    const UTC = 'UTC';
    const TIME = 'g:i a';
    const DATE_SHORT = 'M j';
    const DATE_FULL = 'Y-m-d';

    public static function setTimezone($timezone)
    {
        self::$timezone = $timezone;
    }

    /**
     * Add data to the internal variables. This is chainable,
     * and it will permanently store this data across renders.
     *
     * @param array $data
     *
     * @return self
     */
    public function setData(array $data)
    {
        $this->data = array_merge($this->data, $data);

        return $this;
    }

    /**
     * Render the requested view via echo. This will clear the data
     * array unless told not to.
     *
     * @param string $view
     * @param array $data View data
     * @param bool $return Whether to return the string
     *
     * @throws Exception
     */
    public function render($view, array $data = [], $return = false)
    {
        $viewPath = VIEWDIR.DIR.$view.VIEWEXT;

        if (! file_exists($viewPath)) {
            throw new Exception('View not found! '.$viewPath);
        }

        ob_start();
        extract(array_merge($this->data, $data));

        include $viewPath;

        if ($return) {
            return ob_get_clean();
        } else {
            echo ob_get_clean();
        }
    }

    /**
     * Sanitizes and prints a value for a view.
     *
     * @param string $value
     */
    public function clean($value, $return = false)
    {
        if ($return) {
            return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
        }

        echo htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }

    /**
     * Renders a date, formatted for the timezone.
     *
     * @param string $dateString
     * @param string $format
     */
    public function date($dateString, $format)
    {
        echo self::getDate($date, $format);
    }

    /**
     * Formats a date according to the timezone and format.
     *
     * @param string $dateString
     * @param string $format
     *
     * @return string
     */
    public static function getDate($dateString, $format)
    {
        $utc = new DateTimeZone(self::UTC);
        $tz = new DateTimeZone(self::$timezone);

        $date = $dateString
            ? new DateTime($dateString, $utc)
            : new DateTime;
        $date->setTimezone($tz);

        return $date->format($format);
    }

    /**
     * Prepares a data URI attribute for an element. Escapes the
     * HTML to comply with a data:TYPE attribute.
     *
     * @param string $view
     *
     * @throws Exception
     *
     * @return string
     */
    public function dataUri($view, array $data = [])
    {
        $html = $this->render($view, $data, true);
        $search = ['%', '&', '#', '"', "'"];
        $replace = ['%25', '%26', '%23', '%22', '%27'];
        $html = preg_replace('/\s+/', ' ', $html);

        return str_replace($search, $replace, $html);
    }

    /**
     * Returns a readable version of a file size.
     *
     * @param int $size
     *
     * @return string
     */
    public function humanFileSize(int $size)
    {
        if ($size >= 1073741824) {
            $fileSize = round($size / 1024 / 1024 / 1024, 1).' GB';
        } elseif ($size >= 1048576) {
            $fileSize = round($size / 1024 / 1024, 1).' MB';
        } elseif($size >= 1024) {
            $fileSize = round($size / 1024, 1).' KB';
        } else {
            $fileSize = $size.' bytes';
        }

        return $fileSize;
    }

    /**
     * Returns the best string representation of a time span.
     * i.e. "20 minutes ago", "1 day ago", or the date time.
     *
     * @param int $timestamp
     *
     * @return string
     */
    function timeSpan(int $timestamp)
    {
        $diff = time() - $timestamp;

        if ($diff < 60) {
            $noun = 'second';
            $count = $diff;
        } elseif ($diff < 3600) {
            $noun = 'minute';
            $count = round($diff / 60);
        } elseif ($diff < 86400) {
            $noun = 'hour';
            $count = round($diff / 60 / 60);
        } else {
            return date('j F Y H:i', $timestamp);
        }

        return $count.' '.$noun.($count == 1 ? '' : 's').' ago';
    }
}
