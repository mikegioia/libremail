<?php

namespace App;

class Config
{
    const SMTP_SETTINGS = [
        'gmail' => ['smtp.gmail.com', 587],
        'yahoo' => ['smtp.mail.yahoo.com', 587]
    ];

    public static function getSmtpSettings(string $imapHost)
    {
        $pieces = explode('.', $imapHost);
        $domain = $pieces[1] ?? '';

        if (isset(self::SMTP_SETTINGS[$domain])) {
            return self::SMTP_SETTINGS[$domain];
        }

        return [
            str_replace('imap', 'smtp', $imapHost),
            587
        ];
    }
}