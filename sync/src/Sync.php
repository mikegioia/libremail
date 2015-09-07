<?php

/**
 * Master class for handling all email sync activities.
 */

namespace App;

use PhpImap\Mailbox as Mailbox;

class Sync
{
    private $config;
    private $mailbox;

    /**
     * Constructor can optionally take a dependency container or
     * have the dependencies loaded individually. The di method is
     * used when the sync app is run from a bootstrap file and the
     * ad hoc method is when this class is used separately within
     * other classes like Console.
     * @param array $di Service container
     */
    function __construct( $di = [] )
    {
        if ( $di ) {
            $this->config = $di[ 'config' ];
        }
    }

    /**
     * @param array $config
     */
    function setConfig( $config )
    {
        $this->config = $config;
    }

    /**
     * Connects to an IMAP mailbox using the supplied credentials.
     * @param string $type Account type, like "GMail"
     * @param string $email
     * @param string $password
     * @param string $folder Optional, like "INBOX"
     * @throws Exception
     */
    function connect( $type, $email, $password, $folder = "" )
    {
        $type = strtolower( $type );

        if ( ! isset( $this->config[ 'email' ][ $type ] ) ) {
            throw new \Exception( "IMAP config not found for ". $type );
        }

        $imapPath = $this->config[ 'email' ][ $type ][ 'path' ];
        $attachmentsDir = $this->config[ 'email' ][ 'attachments_dir' ];

        // Check the attachment directory is writeable
        

        $this->mailbox = new Mailbox(
            "{". $imapPath ."}". $folder,
            $email,
            $password,
            $attachmentsDir );
        $this->mailbox->checkMailbox();
    }
}