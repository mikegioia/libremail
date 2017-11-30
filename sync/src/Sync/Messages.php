<?php

/**
 * Syncs IMAP messages to SQL.
 */

namespace App\Sync;

use Fn;
use App\Sync;
use Exception;
use PDOException;
use Monolog\Logger;
use Pb\Imap\Mailbox;
use League\CLImate\CLImate;
use App\Model\Folder as FolderModel;
use App\Model\Account as AccountModel;
use App\Model\Message as MessageModel;
use Evenement\EventEmitter as Emitter;
use App\Exceptions\Validation as ValidationException;
use Pb\Imap\Exceptions\MessageSizeLimit as MessageSizeLimitException;

class Messages
{
    private $log;
    private $cli;
    private $emitter;
    private $mailbox;
    private $interactive;

    const FLAG_UNSEEN = 'UNSEEN';
    const FLAG_FLAGGED = 'FLAGGED';

    /**
     * @param Logger $log
     * @param CLImate $cli
     * @param Emitter $emitter
     * @param bool $interactive
     */
    public function __construct(
        Logger $log,
        CLImate $cli,
        Emitter $emitter,
        Mailbox $mailbox,
        $interactive )
    {
        $this->log = $log;
        $this->cli = $cli;
        $this->emitter = $emitter;
        $this->mailbox = $mailbox;
        $this->interactive = $interactive;
    }

    /**
     * Syncs the content, threads, and flags for all messages in a folder.
     * @param AccountModel $account
     * @param FolderModel $folder
     * @param array $newIds New message IDs
     * @param array $savedIds Existing message IDs
     * @param array $options (see syncMessages)
     */
    public function run( AccountModel $account, FolderModel $folder, $options )
    {
        $newIds = $this->mailbox->getUniqueIds();
        $savedIds = (new MessageModel)->getSyncedIdsByFolder(
            $account->getId(),
            $folder->getId() );

        $this->downloadMessages( $newIds, $savedIds, $folder, $options );
        $this->markDeleted( $newIds, $savedIds, $folder, $options );
        $this->updateThreads( $account, $folder );
        $this->updateSeenFlags( $account, $folder );
        $this->updateFlaggedFlags( $account, $folder );
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

        if ( Fn\get( $options, Sync::OPT_SKIP_DOWNLOAD ) === TRUE ) {
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

        // Sort these newest first to get the new mail earlier
        arsort( $toDownload );

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
            $this->emitter->emit( Sync::EVENT_GARBAGE_COLLECT );
            $this->emitter->emit( Sync::EVENT_CHECK_HALT );
        }

        $this->emitter->emit( Sync::EVENT_GARBAGE_COLLECT );
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
            $this->emitter->emit( Sync::EVENT_CHECK_CLOSED_CONN );
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
            (new MessageModel)->markDeleted(
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
     */
    private function updateThreads( AccountModel $account, FolderModel $folder )
    {
        if ( $folder->isIgnored() ) {
            return;
        }

        $limit = 250;
        $messageModel = new MessageModel;
        $this->log->debug(
            "Updating thread IDs in {$folder->name} for ".
            $account->email );
        $ids = $messageModel->getIdsForThreadingByFolder(
            $account->getId(),
            $folder->getId() );
        $count = count( $ids );

        // Read in a batch of messages, looking specifically for their
        // ID, Message ID, and Reply-To ID.
        for ( $offset = 0; $offset < $count; $offset += $limit ) {
            $slice = array_slice( $ids, $offset, $limit );
            $messages = $messageModel->getByIds( $slice );

            foreach ( $messages as $message ) {
                $message->updateThreadId();
                $this->emitter->emit( Sync::EVENT_CHECK_HALT );
            }
        }
    }

    /**
     * Syncs the seen flag between the IMAP mailbox and SQL.
     * @param AccountModel $account
     * @param FolderModel $model
     */
    private function updateSeenFlags( AccountModel $account, FolderModel $folder )
    {
        // Fetch all unseen message IDs from the mailbox
        $unseenIds = $this->mailbox->search( self::FLAG_UNSEEN, TRUE );

        // Mark as unseen anything in this collection
        if ( $unseenIds ) {
            $count = count( $unseenIds );
            $this->log->info(
                "Marking $count as unseen in {$folder->name}" );

            try {
                (new MessageModel)->markFlag(
                    $unseenIds,
                    $folder->getAccountId(),
                    $folder->getId(),
                    MessageModel::FLAG_SEEN,
                    FALSE );
            }
            catch ( ValidationException $e ) {
                $this->log->notice(
                    "Failed validation for marking unseen messages: ".
                    $e->getMessage() );
            }
        }

        // Mark as seen anything unseen that's not in this collection
        $this->log->debug(
            "Marking remaining messages as seen in {$folder->name}" );

        try {
            (new MessageModel)->markFlag(
                $unseenIds,
                $folder->getAccountId(),
                $folder->getId(),
                MessageModel::FLAG_SEEN,
                TRUE,
                TRUE ); // Inverse
        }
        catch ( ValidationException $e ) {
            $this->log->notice(
                "Failed validation for marking seen messages: ".
                $e->getMessage() );
        }
    }

    /**
     * Syncs the flagged (starred) flag between the IMAP mailbox and SQL.
     * @param AccountModel $account
     * @param FolderModel $model
     */
    private function updateFlaggedFlags( AccountModel $account, FolderModel $folder )
    {
        // Fetch all flagged message IDs from the mailbox
        $flaggedIds = $this->mailbox->search( self::FLAG_FLAGGED, TRUE );

        // Mark as flagged anything in this collection
        if ( $flaggedIds ) {
            $count = count( $flaggedIds );
            $this->log->info(
                "Marking $count as flagged in {$folder->name}" );

            try {
                (new MessageModel)->markFlag(
                    $flaggedIds,
                    $folder->getAccountId(),
                    $folder->getId(),
                    MessageModel::FLAG_FLAGGED,
                    TRUE );
            }
            catch ( ValidationException $e ) {
                $this->log->notice(
                    "Failed validation for marking flagged messages: ".
                    $e->getMessage() );
            }
        }

        // Mark as seen anything unseen that's not in this collection
        $this->log->debug(
            "Marking remaining messages as seen in {$folder->name}" );

        try {
            (new MessageModel)->markFlag(
                $flaggedIds,
                $folder->getAccountId(),
                $folder->getId(),
                MessageModel::FLAG_FLAGGED,
                FALSE,
                TRUE ); // Inverse
        }
        catch ( ValidationException $e ) {
            $this->log->notice(
                "Failed validation for marking un-flagged messages: ".
                $e->getMessage() );
        }
    }
}