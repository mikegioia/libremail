<?php

/**
 * Master class for handling all email sync activities.
 */

namespace App;

use PhpImap\Mailbox as Mailbox
  , App\Models\Account as AccountModel
  , App\Exceptions\MissingIMAPConfig as MissingIMAPConfigException
  , App\Exceptions\AttachmentsPathNotWriteable as AttachmentsPathNotWriteableException;

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

    function run()
    {
        $accountModel = new AccountModel;

        foreach ( $accountModel->getActive() as $account ) {
            $this->connect(
                $account->service,
                $account->email,
                $account->password );
        }
    }

    /**
     * Connects to an IMAP mailbox using the supplied credentials.
     * @param string $type Account type, like "GMail"
     * @param string $email
     * @param string $password
     * @param string $folder Optional, like "INBOX"
     * @throws MissingIMAPConfigException
     */
    function connect( $type, $email, $password, $folder = "" )
    {
        $type = strtolower( $type );

        if ( ! isset( $this->config[ 'email' ][ $type ] ) ) {
            throw new MissingIMAPConfigException( $type );
        }

        $imapPath = $this->config[ 'email' ][ $type ][ 'path' ];
        $attachmentsDir = $this->config[ 'email' ][ 'attachments' ][ 'path' ];

        // Check the attachment directory is writeable
        $this->checkAttachmentsPath();

        $this->mailbox = new Mailbox(
            "{". $imapPath ."}". $folder,
            $email,
            $password,
            $attachmentsDir );
        $this->mailbox->checkMailbox();
    }

    /**
     * Checks if the attachments path is writeable by the user.
     * @throws AttachmentsPathNotWriteableException
     * @return boolean
     */
    private function checkAttachmentsPath()
    {
        $configPath = $this->config[ 'email' ][ 'attachments' ][ 'path' ];
        $attachmentPath = ( substr( $configPath, 0, 1 ) !== "/" )
            ? __DIR__
            : $configPath;

        if ( ! is_writeable( $attachmentPath ) ) {
            throw new AttachmentsPathNotWriteableException;
        }
    }
}