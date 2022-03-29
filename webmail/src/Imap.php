<?php

namespace App;

use App\Exceptions\ServerException;
use Exception;
use Zend\Mail\Storage\Imap as ZendImap;

class Imap
{
    /**
     * Tests a connection the IMAP server. This is used
     * during the account configuration to check if the
     * new credentials are correct.
     *
     * @throws ServerException
     */
    public function connect(string $email, string $password, string $host, int $port)
    {
        try {
            $imapStream = new ZendImap([
                'ssl' => 'SSL',
                'host' => $host,
                'user' => $email,
                'folder' => 'INBOX',
                'password' => $password
            ]);
        } catch (Exception $e) {
            throw new ServerException(rtrim(ucfirst($e->getMessage()), '.').'.');
        }

        if (! $imapStream) {
            throw new ServerException('Failed to connect to IMAP mailbox.');
        }
    }
}
