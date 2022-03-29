<?php

namespace App;

class Config
{
    public const IMAP_SETTINGS = [
        'gmail' => ['imap.gmail.com', 993],
        'yahoo' => ['imap.mail.yahoo.com', 993]
    ];

    public const SMTP_SETTINGS = [
        'gmail' => ['smtp.gmail.com', 587],
        'yahoo' => ['smtp.mail.yahoo.com', 587]
    ];

    public const SERVICE_GMAIL = 'gmail';
    public const SERVICE_YAHOO = 'yahoo';
    public const SERVICE_OTHER = 'other';

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

    /**
     * Returns a random message ID for a new message.
     * The UUID is 36 characters with dashes, 32 without.
     *
     * @return string Format: <GUID@libr.email>
     */
    public static function newMessageId()
    {
        $uuid = sprintf('%s-%s-%04x-%04x-%s',
            // 8 hex characters
            bin2hex(openssl_random_pseudo_bytes(4)),
            // 4 hex characters
            bin2hex(openssl_random_pseudo_bytes(2)),
            // "4" for the UUID version + 3 hex characters
            mt_rand(0, 0x0FFF) | 0x4000,
            // (8, 9, a, or b) for the UUID variant + 3 hex characters
            mt_rand(0, 0x3FFF) | 0x8000,
            // 12 hex characters
            bin2hex(openssl_random_pseudo_bytes(6))
        );

        return '<'.$uuid.'@libr.email>';
    }
}
