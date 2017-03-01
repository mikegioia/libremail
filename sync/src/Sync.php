<?php

/**
 * Master class for handling all email sync activities.
 */

namespace App;

use Fn
  , DateTime
  , Exception
  , App\Daemon
  , App\Message
  , PDOException
  , Monolog\Logger
  , Pb\Imap\Mailbox
  , Pimple\Container
  , League\CLImate\CLImate
  , App\Message\NoAccountsMessage
  , App\Message\NotificationMessage
  , App\Model\Folder as FolderModel
  , App\Model\Account as AccountModel
  , App\Model\Message as MessageModel
  , App\Exceptions\Stop as StopException
  , App\Model\Migration as MigrationModel
  , App\Exceptions\Error as ErrorException
  , App\Exceptions\Fatal as FatalException
  , App\Exceptions\Terminate as TerminateException
  , App\Exceptions\Validation as ValidationException
  , App\Exceptions\FolderSync as FolderSyncException
  , App\Exceptions\MessagesSync as MessagesSyncException
  , App\Exceptions\MissingIMAPConfig as MissingIMAPConfigException
  , Pb\Imap\Exceptions\MessageSizeLimit as MessageSizeLimitException
  , App\Exceptions\AttachmentsPathNotWriteable as AttachmentsPathNotWriteableException;

class Sync
{
    private $cli;
    private $log;
    private $halt;
    private $stop;
    private $wake;
    private $sleep;
    private $config;
    private $folder;
    private $daemon;
    private $asleep;
    private $running;
    private $mailbox;
    private $retries;
    private $interactive;
    private $activeAccount;
    private $maxRetries = 5;
    private $retriesFolders;
    private $retriesMessages;
    private $gcEnabled = FALSE;
    private $gcMemEnabled = FALSE;

    // Options
    const OPT_SKIP_DOWNLOAD = 'skip_download';

    /**
     * Constructor can either take a dependency container or have
     * the dependencies loaded individually. The di method is
     * used when the sync app is run from a bootstrap file and the
     * ad hoc method is when this class is used separately within
     * other classes like Console.
     * @param array $di Service container
     */
    public function __construct( Container $di = NULL )
    {
        $this->halt = FALSE;
        $this->retries = [];
        $this->retriesFolders = [];
        $this->retriesMessages = [];

        if ( $di ) {
            $this->cli = $di[ 'cli' ];
            $this->stats = $di[ 'stats' ];
            $this->config = $di[ 'config' ];
            $this->log = $di[ 'log' ]->getLogger();
            $this->sleep = $di[ 'console' ]->sleep;
            $this->folder = $di[ 'console' ]->folder;
            $this->daemon = $di[ 'console' ]->daemon;
            $this->interactive = $di[ 'console' ]->interactive;
        }

        // Enable garbage collection
        gc_enable();
        $this->gcEnabled = gc_enabled();
        $this->gcMemEnabled = function_exists( 'gc_mem_caches' );
    }

    /**
     * @param CLImate $cli
     */
    public function setCLI( CLImate $cli )
    {
        $this->cli = $cli;
    }

    /**
     * @param Logger $log
     */
    public function setLog( Logger $log )
    {
        $this->log = $log;
    }

    /**
     * @param array $config
     */
    public function setConfig( array $config )
    {
        $this->config = $config;
    }

    /**
     * Runs sync forever. This is a while loop that runs a sync
     * for all accounts, then sleeps for a designated period of
     * time.
     */
    public function loop()
    {
        $wakeUnix = 0;
        $sleepMinutes = $this->config[ 'app' ][ 'sync' ][ 'sleep_minutes' ];

        while ( TRUE ) {
            $this->gc();
            $this->checkForHalt();
            $this->setRunning();

            if ( $this->wake === TRUE ) {
                $wakeUnix = 0;
                $this->wake = FALSE;
            }

            if ( (new DateTime)->getTimestamp() < $wakeUnix ) {
                $this->setAsleep();
                sleep( 60 );
                continue;
            }

            $this->setAsleep( FALSE );

            if ( ! $this->run() ) {
                throw new TerminateException( "Sync was prevented from running" );
            }

            $wakeTime = Fn\timeFromNow( $sleepMinutes );
            $wakeUnix = Fn\unixFromNow( $sleepMinutes );
            $this->log->addInfo(
                "Going to sleep for $sleepMinutes minutes. Sync will ".
                "re-run at $wakeTime." );
        }
    }

    /**
     * For each account:
     *  1. Get the folders
     *  2. Save all message IDs for each folder
     *  3. For each folder, add/remove messages based off IDs
     *  4. Save attachments
     * @param AccountModel $account Optional account to run
     */
    public function run( AccountModel $account = NULL )
    {
        if ( $this->sleep ) {
            return TRUE;
        }

        if ( $account ) {
            $accounts = [ $account ];
        }
        else {
            $accountModel = new AccountModel;
            $accounts = $accountModel->getActive();
        }

        if ( ! $accounts ) {
            $this->stats->setActiveAccount( NULL );

            // If we're in daemon mode, just go to sleep. The script
            // will pick up once the user creates an account and a
            // SIGCONT is sent to this process.
            if ( $this->daemon ) {
                Message::send( new NoAccountsMessage );
                return TRUE;
            }

        	$this->log->notice( "No accounts to run, exiting." );
        	return FALSE;
        }

        // Try to set max allowed packet size in SQL
        $migration = new MigrationModel;

        if ( ! $migration->setMaxAllowedPacket( 16 ) ) {
            $this->log->notice(
                "The max_allowed_packet in MySQL is smaller than what's ".
                "safe for this sync. I've attempted to change it to 16 MB ".
                "but you should re-run this script to re-test. Please see ".
                "the documentation on updating this MySQL setting in your ".
                "configuration file." );
            throw new Exception( "Halting script" );
        }

        // Loop through the active accounts and perform the sync
        // sequentially. The IMAP methods throw exceptions so want
        // to wrap this is a try/catch block.
        foreach ( $accounts as $account ) {
            $this->retries[ $account->email ] = 1;
            $this->runAccount( $account );
        }

        return TRUE;
    }

    /**
     * Runs the sync script for an account. Each action (i.e. connecting
     * to the server, syncing folders, syncing messages, etc) should be
     * allowed to fail a certain number of times before the account is
     * considered offline.
     * @param AccountModel $account
     * @param array $options Valid options include:
     *   only_update_stats (false) If true, only stats about the
     *     folders will be logged. Messages won't be downloaded.
     * @return boolean
     */
    public function runAccount( AccountModel $account, $options = [] )
    {
        if ( $this->retries[ $account->email ] > $this->maxRetries ) {
            $message =
                "The account '{$account->email}' has exceeded the max ".
                "amount of retries after failure ({$this->maxRetries}) ".
                "and is no longer being attempted to sync again.";
            $this->log->notice( $message );
            $this->sendMessage( $message );

            return FALSE;
        }

        $this->checkForHalt();
        $this->stats->setActiveAccount( $account->email );
        $this->log->info( "Starting sync for {$account->email}" );

        try {
            // Open a connection to the mailbox
            $this->disconnect();
            $this->connect(
                $account->imap_host,
                $account->imap_port,
                $account->email,
                $account->password );

            // Check if we're only syncing one folder
            if ( $this->folder ) {
                try {
                    $folderModel = new FolderModel;
                    $folder = $folderModel->getByName(
                        $account->getId(),
                        $this->folder,
                        $failOnNotFound = TRUE );
                }
                catch ( PDOException $e ) {
                    throw $e;
                }
                catch ( Exception $e ) {
                    throw new FatalException(
                        "Syncing that folder failed: ". $e->getMessage() );
                }

                $this->syncMessages( $account, [ $folder ] );
            }
            // Fetch folders and sync them to database
            else {
                $folderModel = new FolderModel;
                $this->retriesFolders[ $account->email ] = 1;
                $this->syncFolders( $account );
                $folders = $folderModel->getByAccount( $account->getId() );
                // First pass, just log the message stats
                $this->syncMessages( $account, $folders, [
                    self::OPT_SKIP_DOWNLOAD => TRUE
                ]);

                // If the all that's requested is to update the folder stats,
                // then we can exit here.
                if ( Fn\get( $options, 'only_update_stats' ) === TRUE ) {
                    return;
                }

                // Second pass, download the messages. Yes, we could have stored
                // an array of folders with 0 messages (to skip) but if we're
                // going to run again in 15 minutes, why not just do two passes
                // and download any extra messages while we can?
                $this->syncMessages( $account, $folders );
            }
        }
        catch ( PDOException $e ) {
            throw $e;
        }
        catch ( FatalException $e ) {
            $this->log->critical( $e->getMessage() );
            exit( 1 );
        }
        catch ( StopException $e ) {
            throw $e;
        }
        catch ( TerminateException $e ) {
            throw $e;
        }
        catch ( Exception $e ) {
            $this->log->error( $e->getMessage() );
            $this->checkForClosedConnection( $e );
            $waitSeconds = $this->config[ 'app' ][ 'sync' ][ 'wait_seconds' ];
            $this->log->info(
                "Re-trying sync ({$this->retries[ $account->email ]}/".
                "{$this->maxRetries}) in $waitSeconds seconds..." );
            sleep( $waitSeconds );
            $this->retries[ $account->email ]++;

            return $this->runAccount( $account );
        }

        $this->log->info( "Sync complete for {$account->email}" );
        $this->disconnect();

        return TRUE;
    }

    /**
     * Connects to an IMAP mailbox using the supplied credentials.
     * @param string $host IMAP hostname, like 'imap.host.com'
     * @param string $port IMAP port, usually 993 (not used)
     * @param string $email
     * @param string $password
     * @param string $folder Optional, like "INBOX"
     * @param bool $setRunning Optional
     * @throws MissingIMAPConfigException
     */
    public function connect( $host, $port, $email, $password, $folder = NULL, $setRunning = TRUE )
    {
        // Check the attachment directory is writeable
        $attachmentsPath = $this->checkAttachmentsPath( $email );

        // If the connection is active, then just select the folder
        if ( $this->mailbox ) {
            $this->mailbox->select( $folder );
            return;
        }

        // Add connection settings and attempt the connection
        $this->mailbox = new Mailbox(
            $host,
            $email,
            $password,
            $folder,
            $attachmentsPath );
        $this->mailbox->getImapStream();

        if ( $setRunning === TRUE ) {
            $this->setRunning( TRUE );
        }
    }

    public function disconnect()
    {
        if ( $this->mailbox ) {
            try {
                $this->mailbox->disconnect();
            }
            catch ( Exception $e ) {
                $this->checkForClosedConnection( $e );
                throw $e;
            }
        }

        $this->mailbox = NULL;
        $this->setAsleep( FALSE );
        $this->setRunning( FALSE );
    }

    public function setAsleep( $asleep = TRUE )
    {
        $this->asleep = $asleep;
        $this->stats->setAsleep( $asleep );
    }

    public function setRunning( $running = TRUE )
    {
        $this->running = $running;
        $this->stats->setRunning( $running );
    }

    /**
     * Turns the halt flag on. Message sync operations check for this
     * and throw a TerminateException if true.
     */
    public function halt()
    {
        $this->halt = TRUE;

        // If we're sleeping forever, throw the exception now
        if ( $this->sleep === TRUE ) {
            throw new TerminateException;
        }
    }

    public function stop()
    {
        $this->halt = TRUE;
        $this->stop = TRUE;
    }

    public function wake()
    {
        $this->wake = TRUE;
        $this->halt = FALSE;
    }

    /**
     * Checks if the attachments path is writeable by the user.
     * @param string $email
     * @throws AttachmentsPathNotWriteableException
     * @return boolean
     */
    public function checkAttachmentsPath( $email, $createEmailDir = TRUE )
    {
        $slash = DIRECTORY_SEPARATOR;
        $configPath = $this->config[ 'email' ][ 'attachments' ][ 'path' ];
        $attachmentsDir = ( substr( $configPath, 0, 1 ) !== $slash )
            ? BASEPATH
            : $configPath;

        if ( ! is_writeable( $attachmentsDir ) ) {
            throw new AttachmentsPathNotWriteableException( $attachmentsDir );
        }

        if ( ! $createEmailDir ) {
            return TRUE;
        }

        $attachmentsPath = ( substr( $configPath, 0, 1 ) !== $slash )
            ? BASEPATH ."$slash$configPath"
            : $configPath;
        $attachmentsPath .= "$slash$email";

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
            // @TODO increment a counter on the folder record
            // if its >=3 then mark folder as inactive
            throw new FolderSyncException;
        }

        $this->log->debug( "Syncing IMAP folders for {$account->email}" );

        try {
            $folderModel = new FolderModel;
            $folderList = $this->mailbox->getFolders();
            $savedFolders = $folderModel->getByAccount( $account->getId() );
            $count = iterator_count( $folderList );
            $this->log->debug( "Found $count ". Fn\plural( 'folder', $count ) );
            $this->addNewFolders( $folderList, $savedFolders, $account );
            $this->removeOldFolders( $folderList, $savedFolders, $account );
        }
        catch ( PDOException $e ) {
            throw $e;
        }
        catch ( StopException $e ) {
            throw $e;
        }
        catch ( TerminateException $e ) {
            throw $e;
        }
        catch ( Exception $e ) {
            $this->log->error( $e->getMessage() );
            $this->checkForClosedConnection( $e );
            $waitSeconds = $this->config[ 'app' ][ 'sync' ][ 'wait_seconds' ];
            $this->log->info(
                "Re-trying folder sync ({$this->retriesFolders[ $account->email ]}/".
                "{$this->maxRetries}) in $waitSeconds seconds..." );
            sleep( $waitSeconds );
            $this->retriesFolders[ $account->email ]++;
            $this->checkForHalt();
            $this->syncFolders( $account );
        }
    }

    /**
     * Adds new folders from IMAP to the database.
     * @param array $folderList
     * @param FolderModel array $savedFolders
     * @param AccountModel $account
     */
    private function addNewFolders( $folderList, $savedFolders, AccountModel $account )
    {
        $i = 1;
        $toAdd = [];

        foreach ( $folderList as $folderName ) {
            if ( ! array_key_exists( (string) $folderName, $savedFolders ) ) {
                $toAdd[] = $folderName;
            }
        }

        if ( ! ( $count = count( $toAdd ) ) ) {
            $this->log->debug( "No new folders to save" );
            return;
        }

        if ( $this->interactive ) {
            $this->cli->whisper(
                "Adding $count new ". Fn\plural( 'folder', $count ) .":" );
            $progress = $this->cli->progress()->total( $count );
        }
        else {
            $this->log->info(
                "Adding $count new ". Fn\plural( 'folder', $count ) );
        }

        foreach ( $toAdd as $folderName ) {
            $folder = new FolderModel([
                'name' => $folderName,
                'account_id' => $account->getId()
            ]);
            $folder->save();
            $folders[ $folder->getId() ] = $folder;

            if ( $this->interactive ) {
                $progress->current( $i++ );
            }

            $this->checkForHalt();
        }
    }

    /**
     * Removes purged folders no longer in the mailbox from the database.
     * @param array $folderList
     * @param FolderModel array $savedFolders
     * @param AccountModel $account
     */
    private function removeOldFolders( $folderList, $savedFolders, AccountModel $account )
    {
        $lookup = [];
        $toRemove = [];

        foreach ( $folderList as $folderName ) {
            $lookup[] = $folderName;
        }

        foreach ( $savedFolders as $savedFolder ) {
            if ( ! in_array( $savedFolder->getName(), $lookup ) ) {
                $toRemove[] = $savedFolder;
            }
        }

        if ( ! ( $count = count( $toRemove ) ) ) {
            $this->log->debug( "No folders to remove" );
            return;
        }

        $this->log->info( "Removing $count ". Fn\plural( 'folder', $count ) );

        foreach ( $toRemove as $folder ) {
            $folder->delete();
        }
    }

    /**
     * Syncs all of the messages for an account. This is set up
     * to try each folder some amount of times before moving on
     * to the next folder.
     * @param AccountModel $account
     * @param array FolderModel $folders
     * @param array $options Valid options include:
     *   skip_download (false) If true, only stats about the
     *     folder will be logged. The messages won't be downloaded.
     */
    private function syncMessages( AccountModel $account, $folders, $options = [] )
    {
        if ( Fn\get( $options, self::OPT_SKIP_DOWNLOAD ) === TRUE ) {
            $this->log->debug( "Updating folder counts" );
        }
        else {
            $this->log->debug( "Syncing messages in each folder" );
        }

        foreach ( $folders as $folder ) {
            $this->retriesMessages[ $account->email ] = 1;

            try {
//                $this->syncFolderMessages( $account, $folder, $options );
                $this->updateFolderThreads( $account, $folder, $options );
            }
            catch ( MessagesSyncException $e ) {
                $this->log->error( $e->getMessage() );
            }

            $this->checkForHalt();
        }
    }

    /**
     * Syncs all of the messages for a given IMAP folder.
     * @param AccountModel $account
     * @param FolderModel $folder
     * @param array $options (see syncMessages)
     * @throws MessagesSyncException
     * @return bool
     */
    private function syncFolderMessages( AccountModel $account, FolderModel $folder, $options )
    {
        if ( $folder->isIgnored() ) {
            $this->log->debug( 'Skipping ignored folder' );
            return;
        }

        if ( $this->retriesMessages[ $account->email ] > $this->maxRetries ) {
            $this->log->notice(
                "The account '{$account->email}' has exceeded the max ".
                "amount of retries ({$this->maxRetries}) after trying ".
                "to sync the folder '{$folder->name}'. Skipping to the ".
                "next folder." );
            throw new MessagesSyncException( $folder->name );
        }

        $this->log->debug(
            "Syncing messages in {$folder->name} for {$account->email}" );
        $this->log->debug(
            "Memory usage: ". Fn\formatBytes( memory_get_usage() ) .
            ", real usage: ". Fn\formatBytes( memory_get_usage( TRUE ) ) .
            ", peak usage: ". Fn\formatBytes( memory_get_peak_usage() ) );

        // Syncing a folder of messages is done using the following
        // algorithm:
        //  1. Get all message IDs
        //  2. Get all message IDs saved in SQL
        //  3. For anything in 1 and not 2, download messages and save
        //     to SQL database
        //  4. Mark deleted in SQL anything in 2 and not 1
        try {
            $messageModel = new MessageModel;
            // Select the folder's mailbox, this is sent to the
            // messages sync library to perform operations on
            $this->mailbox->select( $folder->name );
            $this->stats->setActiveFolder( $folder->name );
            $newIds = $this->mailbox->getUniqueIds();
            $savedIds = $messageModel->getSyncedIdsByFolder(
                $account->getId(),
                $folder->getId() );
            $this->downloadMessages( $newIds, $savedIds, $folder, $options );
            $this->markDeleted( $newIds, $savedIds, $folder, $options );
            $this->stats->unsetActiveFolder();
            $this->checkForHalt();
        }
        catch ( PDOException $e ) {
            throw $e;
        }
        catch ( StopException $e ) {
            throw $e;
        }
        catch ( TerminateException $e ) {
            throw $e;
        }
        catch ( Exception $e ) {
            $this->stats->unsetActiveFolder();
            $this->log->error( substr( $e->getMessage(), 0, 500 ) );
            $this->checkForClosedConnection( $e );
            $retryCount = $this->retriesMessages[ $account->email ];
            $waitSeconds = $this->config[ 'app' ][ 'sync' ][ 'wait_seconds' ];
            $this->log->info(
                "Re-trying message sync ($retryCount/{$this->maxRetries}) ".
                "for folder '{$folder->name}' in $waitSeconds seconds..." );
            sleep( $waitSeconds );
            $this->retriesMessages[ $account->email ]++;
            $this->checkForHalt();

            return $this->syncFolderMessages( $account, $folder, $options );
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
     * @param array $options (see syncMessages)
     */
    private function downloadMessages( $newIds, $savedIds, FolderModel $folder, $options )
    {
        // First get the list of messages to download by taking
        // a diff of the arrays. Download all these messages.
        $i = 1;
        $progress = NULL;
        $total = count( $newIds );
        $toDownload = array_diff( $newIds, $savedIds );
        $count = count( $toDownload );
        $syncedCount = $total - $count;
        $noun = Fn\plural( 'message', $total );
        $this->log->debug( "Downloading messages in {$folder->name}" );

        if ( $count ) {
            $this->log->info( "{$folder->name}: found $total $noun, $count new" );
        }
        else {
            $this->log->debug( "Found $total $noun, none are new" );
        }

        // Update folder stats with count
        $folder->saveStats( $total, $syncedCount );

        if ( Fn\get( $options, self::OPT_SKIP_DOWNLOAD ) === TRUE ) {
            return;
        }

        if ( ! $count ) {
            $this->log->debug( "No new messages, skipping {$folder->name}" );
            return;
        }

        if ( $this->interactive ) {
            $noun = Fn\plural( 'message', $count );
            $this->cli->whisper(
                "Syncing $count new $noun in {$folder->name}:" );
            $progress = $this->cli->progress()->total( 100 );
        }

        foreach ( $toDownload as $messageId => $uniqueId ) {
            $this->downloadMessage( $messageId, $uniqueId, $folder );

            if ( $this->interactive ) {
                $progress->current( ( $i++ / $count ) * 100 );
            }

            $message = NULL;
            $imapMessage = NULL;

            // Save stats about the folder
            $folder->saveStats( $total, ++$syncedCount );

            // After each download, try to reclaim memory.
            $this->gc();
            $this->checkForHalt();
        }

        $this->gc();
    }

    /**
     * Download a specified message by message ID.
     * @param integer $messageId
     * @param integer $uniqueId
     * @param FolderModel $folder
     */
    private function downloadMessage( $messageId, $uniqueId, FolderModel $folder )
    {
        try {
            $imapMessage = $this->mailbox->getMessage( $messageId );
            $message = new MessageModel([
                'synced' => 1,
                'folder_id' => $folder->getId(),
                'account_id' => $folder->getAccountId(),
            ]);
            $message->setMessageData( $imapMessage, [
                // This will trim subjects to the max size
                MessageModel::OPT_TRUNCATE_FIELDS => TRUE
            ]);
        }
        catch ( PDOException $e ) {
            throw $e;
        }
        catch ( MessageSizeLimitException $e ) {
            $this->log->notice(
                "Size exceeded during download of message $messageId: ".
                $e->getMessage() );
            return;
        }
        catch ( Exception $e ) {
            $this->log->warning(
                "Failed download for message {$messageId}: ".
                $e->getMessage() );
            $this->checkForClosedConnection( $e );
            return;
        }

        // Save the meta info that comes back from the server
        // regardless if the record exists.
        try {
            $message->save();
        }
        catch ( ValidationException $e ) {
            $this->log->notice(
                "Failed validation for message $messageId: ".
                $e->getMessage() );
        }
    }

    /**
     * For any messages we have saved that didn't come back from the
     * mailbox, mark them as deleted in the database.
     * @param array $newIds
     * @param array $savedIds
     * @param FolderModel $folder
     */
    private function markDeleted( $newIds, $savedIds, FolderModel $folder )
    {
        $toDelete = array_diff( $savedIds, $newIds );
        $count = count( $toDelete );

        if ( ! $count ) {
            $this->log->debug( "No messages to delete in {$folder->name}" );
            return;
        }

        $this->log->info(
            "Marking $count deletion". ( $count == 1 ? '' : 's' ) .
            " in {$folder->name}" );

        try {
            $messageModel = new MessageModel;
            $messageModel->markDeleted(
                $toDelete,
                $folder->getAccountId(),
                $folder->getId() );
        }
        catch ( ValidationException $e ) {
            $this->log->notice(
                "Failed validation for marking deleted messages: ".
                $e->getMessage() );
        }
    }

    /**
     * Reads in all of the messages for a folder and tries to update
     * as many thread IDs as possible.
     * @param AccountModel $account
     * @param FolderModel $folder
     * @param array $options
     */
    private function updateFolderThreads( AccountModel $account, FolderModel $folder, $options )
    {
        if ( $folder->isIgnored() ) {
            return;
        }

        $offset = 0;
        $limit = 1000;
        $messageModel = new MessageModel;
        $this->log->debug( 'Updating thread IDs' );
        $messages = $messageModel->getByFolderForThreading(
            $account->getId(),
            $folder->getId(),
            $limit,
            $offset );

        // Read in a batch of messages, looking specifically for their
        // ID, Message ID, and Reply-To ID.
        while ( $messages && count( $messages ) > 0 ) {
            foreach ( $messages as $message ) {
                $this->updateMessageThreadId( $message, $messageModel );
            }

            $this->log->debug( 'Getting next batch of messages' );
            $offset += $limit;
            $messages = $messageModel->getByFolderForThreading(
                $account->getId(),
                $folder->getId(),
                $limit,
                $offset );
        }
    }

    /**
     * Adds a thread ID to the message. Searches forward and back looking
     * for any thread ID that is saved on any message. If found, update
     * this message with that thread ID.
     * @param object $message
     * @param MessageModel $model
     */
    private function updateMessageThreadId( $message, MessageModel $model )
    {
        $threadId = $message->id;
        $siblings = $model->getMessageSiblings(
            $message->message_id,
            $message->in_reply_to );
        $this->log->debug( 'Getting thread ID for '. $message->id );
echo count($siblings);
print_r($siblings);
        while ( count( $siblings ) > 0 ) {
            $nextSiblings = [];

            foreach ( $siblings as $sibling ) {
                if ( $sibling->thread_id ) {
                    $threadId = $sibling->thread_id;
                    break 2;
                }

                $nextSiblings = array_merge(
                    $nextSiblings,
                    $model->getBy( $sibling->message_id ) );
            }

            $siblings = array_filter( $nextSiblings );
echo count($siblings);
print_r($siblings);
sleep( 2 );
        }
exit;
        $model->updateThreadId( $message->id, $threadId );
    }

    private function sendMessage( $message, $status = STATUS_ERROR )
    {
        Message::send( new NotificationMessage( $status, $message ) );
    }

    /**
     * Checks if a halt command has been issued. This is a command
     * to stop the sync. We want to do is gracefull though so the
     * app checks in various places when it's save to halt.
     * @throws StopException
     * @throws TerminateException
     */
    private function checkForHalt()
    {
        pcntl_signal_dispatch();

        if ( $this->halt === TRUE ) {
            $this->disconnect();
            $this->stats->setActiveAccount( NULL );

            // If there was a stop command issued, then don't terminate
            if ( $this->stop === TRUE ) {
                throw new StopException;
            }

            // If we just want to sleep, then don't terminate
            if ( $this->sleep !== TRUE ) {
                throw new TerminateException;
            }
        }
    }

    /**
     * Checks the exception message for a "closed connection" string.
     * This can happen when the IMAP socket is closed or fails. When
     * this happens we want to terminate the sync and let the whole
     * thing pick back up.
     * @param Exception $e
     * @throws StopException
     */
    private function checkForClosedConnection( Exception $e )
    {
        if ( strpos( $e->getMessage(), "connection closed?" ) !== FALSE ) {
            $this->sendMessage(
                "The IMAP connection was lost. Your internet connection ".
                "could be down or it could just be a network error. The ".
                "system will sleep for a bit before re-trying.".
                STATUS_ERROR );
            throw new StopException;
        }
    }

    private function gc()
    {
        pcntl_signal_dispatch();

        if ( $this->gcEnabled ) {
            gc_collect_cycles();

            if ( $this->gcMemEnabled ) {
                gc_mem_caches();
            }
        }
    }
}