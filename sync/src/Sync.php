<?php

/**
 * Master class for handling all email sync activities.
 */

namespace App;

use PhpImap\Mailbox
  , Monolog\Logger
  , Pimple\Container
  , League\CLImate\CLImate
  , App\Models\Folder as FolderModel
  , App\Models\Account as AccountModel
  , App\Models\Messages as MessagesModel
  , App\Exceptions\Validation as ValidationException
  , App\Exceptions\FolderSync as FolderSyncException
  , App\Exceptions\MessagesSync as MessagesSyncException
  , App\Exceptions\MissingIMAPConfig as MissingIMAPConfigException
  , App\Exceptions\AttachmentsPathNotWriteable as AttachmentsPathNotWriteableException;

class Sync
{
    private $cli;
    private $log;
    private $config;
    private $mailbox;
    private $retries;
    private $interactive;
    private $maxRetries = 5;
    private $retriesFolders;
    private $retriesMessages;
    private $messageBatchSize = 50;

    /**
     * Constructor can optionally take a dependency container or
     * have the dependencies loaded individually. The di method is
     * used when the sync app is run from a bootstrap file and the
     * ad hoc method is when this class is used separately within
     * other classes like Console.
     * @param array $di Service container
     */
    function __construct( Container $di = NULL )
    {
        $this->retries = [];
        $this->retriesFolders = [];
        $this->retriesMessages = [];

        if ( $di ) {
            $this->cli = $di[ 'cli' ];
            $this->log = $di[ 'log' ];
            $this->config = $di[ 'config' ];
            $this->interactive = $di[ 'console' ]->interactive;
        }
    }

    /**
     * @param CLImate $cli
     */
    function setCLI( CLImate $cli )
    {
        $this->cli = $cli;
    }

    /**
     * @param Logger $log
     */
    function setLog( Logger $log )
    {
        $this->log = $log;
    }

    /**
     * @param array $config
     */
    function setConfig( array $config )
    {
        $this->config = $config;
    }

    /**
     * For each account:
     *  1. Get the folders
     *  2. Save all message IDs for each folder
     *  3. For each folder, add/remove messages based off IDs
     *  4. Set up threading for messages based off UIDs
     * @param AccountModel $account Optional account to run
     */
    function run( AccountModel $account = NULL )
    {
        if ( $account ) {
            $accounts = [ $account ];
        }
        else {
            $accountModel = new AccountModel;
            $accounts = $accountModel->getActive();
        }

        // Loop through the active accounts and perform the sync
        // sequentially. The IMAP methods throw exceptions so want
        // to wrap this is a try/catch block.
        foreach ( $accounts as $account ) {
            $this->retries[ $account->email ] = 1;
            $this->runAccount( $account );
        }
    }

    /**
     * Runs the sync script for an account. Each action (i.e. connecting
     * to the server, syncing folders, syncing messages, etc) should be
     * allowed to fail a certain number of times before the account is
     * considered offline.
     * @param AccountModel $account
     * @return boolean
     */
    private function runAccount( AccountModel $account )
    {
        if ( $this->retries[ $account->email ] > $this->maxRetries ) {
            $this->log->notice(
                "The account '{$account->email}' has exceeded the max ".
                "amount of retries after failure ({$this->maxRetries}) ".
                "and is no longer being attempted to sync again." );
            return FALSE;
        }

        $this->log->info( "Starting sync for {$account->email}" );

        try {
            // Open a connection to the mailbox
            $this->connect(
                $account->service,
                $account->email,
                $account->password );
            // Fetch folders and sync them to database
            $this->retriesFolders[ $account->email ] = 1;
            $folders = $this->syncFolders( $account );
            $this->syncMessages( $account, $folders );
        }
        catch ( \Exception $e ) {
            $this->log->error( $e->getMessage() );
            $waitSeconds = $this->config[ 'app' ][ 'sync' ][ 'wait_seconds' ];
            $this->log->info(
                "Re-trying sync ({$this->retries[ $account->email ]}/".
                "{$this->maxRetries}) in $waitSeconds seconds..." );
            sleep( $waitSeconds );
            $this->retries[ $account->email ]++;

            return $this->runAccount( $account );
        }

        $this->log->info( "Sync complete for {$account->email}" );

        return TRUE;
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

        // Check the attachment directory is writeable
        $attachmentsPath = $this->checkAttachmentsPath();
        $imapPath = $this->config[ 'email' ][ $type ][ 'path' ];

        $this->mailbox = new Mailbox(
            "{". $imapPath ."}". $folder,
            $email,
            $password,
            $attachmentsPath );
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
        $attachmentsDir = ( substr( $configPath, 0, 1 ) !== "/" )
            ? BASEPATH
            : $configPath;

        if ( ! is_writeable( $attachmentsDir ) ) {
            throw new AttachmentsPathNotWriteableException;
        }

        $attachmentsPath = ( substr( $configPath, 0, 1 ) !== "/" )
            ? BASEPATH ."/". $configPath
            : $configPath;
        @mkdir( $attachmentsPath, 0755, TRUE );

        return $attachmentsPath;
    }

    /**
     * Syncs a collection of IMAP folders to the database.
     * @param AccountModel $account Account to sync
     * @throws FolderSyncException
     * @return array $folders List of IMAP folders
     */
    private function syncFolders( AccountModel $account )
    {
        if ( $this->retriesFolders[ $account->email ] > $this->maxRetries ) {
            $this->log->notice(
                "The account '{$account->email}' has exceeded the max ".
                "amount of retries after folder sync failure ".
                "({$this->maxRetries})." );
            throw new FolderSyncException;
        }

        $i = 1;
        $folders = [];
        $progress = NULL;
        $this->log->debug( "Syncing IMAP folders for {$account->email}" );

        try {
            $folderList = $this->mailbox->getListingFolders();
            $count = count( $folderList );

            if ( $this->interactive ) {
                $this->cli->whisper( "Syncing $count folders:" );
                $progress = $this->cli->progress()->total( 100 );
            }

            // Add each folder to SQL, mark old folders as deleted
            foreach ( $folderList as $folderName ) {
                $folder = new FolderModel([
                    'name' => $folderName,
                    'account_id' => $account->getId()
                ]);
                $folder->save();
                $folders[ $folder->getId() ] = $folder;

                if ( $this->interactive ) {
                    $progress->current( ( $i++ / $count ) * 100 );
                }
            }
        }
        catch ( \Exception $e ) {
            $this->log->error( $e->getMessage() );
            $waitSeconds = $this->config[ 'app' ][ 'sync' ][ 'wait_seconds' ];
            $this->log->info(
                "Re-trying folder sync ({$this->retriesFolders[ $account->email ]}/".
                "{$this->maxRetries}) in $waitSeconds seconds..." );
            sleep( $waitSeconds );
            $this->retriesFolders[ $account->email ]++;

            return $this->syncFolders( $account );
        }

        return $folders;
    }

    /**
     * Syncs all of the messages for an account. This is set up
     * to try each folder some amount of times before moving on
     * to the next folder.
     * @param AccountModel $account
     * @param array FolderModel $folders
     */
    private function syncMessages( AccountModel $account, $folders )
    {
        foreach ( $folders as $folder ) {
            $this->retriesMessages[ $account->email ] = 1;

            try {
                $this->syncFolderMessages( $account, $folder );
            }
            catch ( MessagesSyncException $e ) {
                $this->log->error( $e->getMessage() );
            }

            exit( 'done first folder' );
        }
    }

    /**
     * Syncs all of the messages for a given IMAP folder.
     * @param AccountModel $account
     * @param FolderModel $folder
     * @throws MessagesSyncException
     * @return bool
     */
    private function syncFolderMessages( AccountModel $account, FolderModel $folder )
    {
        if ( $this->retriesMessages[ $account->email ] > $this->maxRetries ) {
            $this->log->notice(
                "The account '{$account->email}' has exceeded the max ".
                "amount of retries ({$this->maxRetries}) after trying ".
                "to sync the folder '{$folder->name}'. Skipping to the ".
                "next folder." );
            throw new MessagesSyncException( $folder );
        }

        $this->log->debug(
            "Syncing messages in {$folder->name} for {$account->email}" );

        // Syncing a folder of messages is done using the following
        // algorithm:
        //  1. Get all message IDs
        //  2. Get all message IDs saved in SQL
        //  3. For anything in 1 and not 2, download messages and save
        //     to SQL database
        //  4. Mark deleted in SQL anything in 2 and not 1
        try {
            $messagesModel = new MessagesModel;
            // Connect to the folder's mailbox, this is sent to the
            // messages sync library to perform operations on
            $this->connect(
                $account->service,
                $account->email,
                $account->password,
                $folder->name );
            $newIds = $this->mailbox->searchMailBox( 'ALL' );
            $savedIds = $messagesModel->getSyncedIdsByFolder(
                $account->getId(),
                $folder->getId() );
            $this->downloadMessages( $newIds, $savedIds, $folder );
            $this->markDeleted( $newIds, $savedIds, $folder );
        }
        catch ( \Exception $e ) {
            $this->log->error( $e->getMessage() );
            $retryCount = $this->retriesMessages[ $account->email ];
            $waitSeconds = $this->config[ 'app' ][ 'sync' ][ 'wait_seconds' ];
            $this->log->info(
                "Re-trying message sync ($retryCount/{$this->maxRetries}) ".
                "for folder '{$folder->name}' in $waitSeconds seconds..." );
            sleep( $waitSeconds );
            $this->retriesMessages[ $account->email ]++;

            return $this->syncFolderMessages( $account, $folder );
        }
    }

    /**
     * This is the engine that downloads and saves messages for a
     * given mailbox and folder. Given a list of current message IDs
     * retrieved from IMAP, and a list of the IDs we have in the
     * database, copy all the new messages down and mark the removed
     * ones as such in the database.
     * @param array $newIds
     * @param array $savedIds
     * @param FolderModel $folder
     */
    private function downloadMessages( $newIds, $savedIds, FolderModel $folder )
    {
        // First get the list of messages to download by taking
        // a diff of the arrays. Download all these messages.
        $i = 1;
        $progress = NULL;
        $total = count( $newIds );
        $toDownload = array_diff( $newIds, $savedIds );
        $count = count( $toDownload );
        $this->log->debug( "Downloading messages in {$folder->name}" );
        $this->log->debug( "$total messages, $count new" );

        if ( ! $count ) {
            $this->log->debug( "No new messages, skipping {$folder->name}" );
            return;
        }

        if ( $this->interactive ) {
            $this->cli->whisper(
                "Syncing $count of $total messages in {$folder->name}:" );
            $progress = $this->cli->progress()->total( 100 );
        }

        // Pull the meta info for a batch of messages at a time.
        for ( $j = 0; $j <= $count; $j += $this->messageBatchSize ) {
            $mailIds = array_slice( $toDownload, $j, $this->messageBatchSize );
            $mailMeta = $this->mailbox->getMailsInfo( $mailIds );

            foreach ( $mailMeta as $meta ) {
                $message = new MessagesModel([
                    'folder_id' => $folder->getId(),
                    'account_id' => $folder->getAccountId(),
                ]);
                $message->setMailMeta( $meta );

                // Save the meta info that comes back from the server
                // regardless if the record exists.
                try {
                    $message->save();
                }
                catch ( ValidationException $e ) {
                    $this->log->notice(
                        "Failed validation for message meta ".
                        "{$message->getUniqueId()}: {$e->getMessage()}" );
                    goto endOfLoop;
                }

                // If the message hasn't been synced yet, then pull the entire
                // contents and save those to the database.
                if ( ! $message->isSynced() ) {
                    $mailData = $this->mailbox->getMail(
                        $message->getUniqueId(),
                        FALSE );
                    $message->setMailData( $mailData );

                    try {
                        $message->save();
                    }
                    catch ( ValidationException $e ) {
                        $this->log->notice(
                            "Failed validation for message data ".
                            "{$message->getUniqueId()}: {$e->getMessage()}" );
                    }
                }

                endOfLoop: {
                    if ( $this->interactive ) {
                        $progress->current( ( $i++ / $count ) * 100 );
                    }
                }
            }
        }
        exit;
    }

    private function markDeleted( $newIds, $savedIds, FolderModel $folder )
    {
        $this->log->debug( "Marking any deletions in {$folder->name}" );
    }
}