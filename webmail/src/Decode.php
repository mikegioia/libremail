<?php

namespace App;

class Decode
{
    const UTF8 = 'UTF-8';
    const REPLACEMENTS = [
        'utf8' => 'UTF-8',
        'utf-8' => 'UTF-8',
        'us-ascii' => 'ASCII',
        'iso-8859-15' => 'ISO-8859-15',
        'iso-8859-1' => 'ISO-8859-1',
        '=utf-8' => 'UTF-8',
        '8859_1' => 'ISO-8859-1',
        'ascii' => 'ASCII',
        'windows-1252' => 'Windows-1252',
        'windows-1255' => 'Windows-1255',
        'windows-1256' => 'Windows-1256',
        'windows-1251' => 'Windows-1251',
        'ansi_x3.4-1968' => 'ASCII',
        'cp1252' => 'Windows-1252',
        'latin1' => 'DOS-Latin-1',
        'iso646-US' => 'ASCII'
    ];

    public static function decode($string, $charset)
    {
        // If we have one, try to match it
        if ($charset) {
            foreach (self::REPLACEMENTS as $find => $replace) {
                if (0 === strncasecmp($find, $charset, strlen($find))) {
                    $charset = $replace;
                    break;
                }
            }

            return mb_convert_encoding($string, self::UTF8, $charset);
        }

        return mb_convert_encoding($string, self::UTF8);
    }
}
