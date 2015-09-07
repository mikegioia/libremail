<?php

/**
 * Master class for handling all email sync activities.
 */

namespace App;

use PhpImap\Mailbox as Mailbox;

class Sync
{
    private $config;

    function __construct( $config )
    {
        $this->config = $config;
    }

    function connect( $type, $email, $password )
    {
        $type = strtolower( $type );

        if ( ! isset( $this->config[ $type ] ) ) {
            throw new \Exception( "IMAP config not found for ". $type );
        }

        $imapPath = $this->config[ $type ][ 'path' ];
        $mailbox = new PhpImap\Mailbox(
            "{$imapPath}",
            $email,
            $password,
            $this->config[ 'attachments_dir' ] );
    }
}