<?php

namespace App;

class Config
{
    const IMAP_SETTINGS = [
        'gmail' => ['imap.gmail.com', 993],
        'yahoo' => ['imap.mail.yahoo.com', 993]
    ];

    const SMTP_SETTINGS = [
        'gmail' => ['smtp.gmail.com', 587],
        'yahoo' => ['smtp.mail.yahoo.com', 587]
    ];

    const SERVICE_GMAIL = 'gmail';
    const SERVICE_YAHOO = 'yahoo';
    const SERVICE_OTHER = 'other';

    /**
     * Loads SMTP settings based on an imap host.
     *
     * @return [$host, $port]
     */
    public static function getSmtpSettings(string $imapHost)
    {
        $pieces = explode('.', $imapHost);
        $service = $pieces[1] ?? '';

        if (isset(self::SMTP_SETTINGS[$service])) {
            return self::SMTP_SETTINGS[$service];
        }

        return [
            str_replace('imap', 'smtp', $imapHost),
            587
        ];
    }

    /**
     * Loads IMAP settings based on an email address.
     *
     * @return [$host, $port]
     */
    public static function getImapSettings(string $address)
    {
        $service = self::getEmailService($address);

        if (isset(self::IMAP_SETTINGS[$service])) {
            return self::IMAP_SETTINGS[$service];
        }

        return ['', 993];
    }

    /**
     * Returns an account service constant based on the email
     * address. These services are supported natively by the app.
     *
     * @return string
     */
    public static function getEmailService(string $address)
    {
        $pieces = explode('@', $address, 2);

        if (2 !== count($pieces)) {
            return self::SERVICE_OTHER;
        }

        if ('gmail.com' === $pieces[1]) {
            return self::SERVICE_GMAIL;
        } elseif ('googlemail.com' === $pieces[1]) {
            return self::SERVICE_GMAIL;
        } elseif ('yahoo.com' === $pieces[1]) {
            return self::SERVICE_YAHOO;
        } else {
            return self::SERVICE_OTHER;
        }
    }
}
