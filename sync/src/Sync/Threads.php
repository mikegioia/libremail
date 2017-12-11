<?php

/**
 * Groups messages into threads. This process runs in O(2n).
 *
 * Pass 1: Load all messages from database and bucket by
 *   message-id. Store all references.
 * Pass 2: Run through each message's references and
 *   and compact the union of references on all messages,
 *   along with the lowest numbered internal ID as the
 *   thread ID.
 */

namespace App\Sync;

use Fn;
use App\Sync;
use Monolog\Logger;
use League\CLImate\CLImate;
use App\Model\Account as AccountModel;
use App\Model\Message as MessageModel;
use Evenement\EventEmitter as Emitter;
use App\Sync\Threads\Meta as ThreadMeta;
use App\Sync\Threads\Message as ThreadMessage;

class Threads
{
    private $log;
    private $cli;
    private $emitter;
    private $account;
    private $progress;
    private $interactive;

    const EVENT_MESSAGE_DIRTY = 'message_dirty';

    // Index of the most recent message known
    private $maxId;
    // Total IDs to fetch
    private $totalIds;
    // Current index position in message processing
    private $currentId;
    // Master index of messages and references
    private $messages = [];
    // Index of new, unthreaded messages
    private $unthreaded = [];
    // Index of all threads for subject matching
    private $threadMeta = [];
    // Index of subject hashes => thread IDs
    private $subjectHashes = [];
    // Index of all threads related by subject
    private $subjectThreads = [];
    // List of dirty message IDs to update
    private $dirtyMessageIds = [];

    // How many messages to fetch at once
    const BATCH_SIZE = 1000;

    /**
     * @param Logger $log
     * @param CLImate $cli
     * @param bool $interactive
     */
    public function __construct( Logger $log, CLImate $cli, $interactive )
    {
        $this->log = $log;
        $this->cli = $cli;
        $this->interactive = $interactive;
    }

    /**
     * Run the threading for an account. If the account is different
     * than the one we have in the cache, then start over. This should
     * either be set up to handle multiple accounts, or the sync script
     * should not run for multiple accounts on the same process.
     * @param AccountModel $account
     * @param Emitter $emitter
     */
    public function run( AccountModel $account, Emitter $emitter )
    {
        if ( ! $this->account || $account->id !== $this->account->id ) {
            $this->maxId = NULL;
            $this->totalIds = 0;
            $this->currentId = 0;
            $this->messages = [];
            $this->allProcessed = [];
            $this->account = $account;
        }

        $this->emitter = $emitter;
        $this->updateEmitter();
        $this->storeMessageIdBounds();
        $this->storeTotalMessageIds();

        // Pass 1
        if ( ! $this->messages || $this->currentId < $this->maxId ) {
            $this->storeMessages();
        }

        // Pass 2
        $this->updateThreadIds();
        // Pass 3
        $this->commitThreadIds();
    }

    /**
     * Finds the last message ID to store and update.
     */
    private function storeMessageIdBounds()
    {
        $model = new MessageModel;
        $this->maxId = $model->getMaxMessageId( $this->account->id );

        if ( ! $this->currentId ) {
            $this->currentId = $model->getMinMessageId( $this->account->id );
        }
    }

    private function storeTotalMessageIds()
    {
        $this->totalIds = (new MessageModel)->countByAccount( $this->account->id );
    }

    private function updateEmitter()
    {
        $this->emitter->on( self::EVENT_MESSAGE_DIRTY, function ( $id ) {
            $this->dirtyMessageIds[ $id ] = TRUE;
        });
    }

    /**
     * Load all messages, or any new messages, from the database into
     * the internal storage array. This will instantiate a new message
     * object and store the references for that message.
     * @param AccountModel $account
     */
    private function storeMessages()
    {
        $count = 0;
        $minId = $this->currentId;
        $currentId = $this->currentId;
        $total = $this->maxId - $minId;
        $messageModel = new MessageModel;
        $this->log->debug(
            "Storing messages for threading for {$this->account->email}" );
        $this->printMemory();
        $this->startProgress( 1, $total );

        for ( $minId; $minId < $this->maxId; $minId += self::BATCH_SIZE ) {
            $messages = $messageModel->getRangeForThreading(
                $this->account->id,
                $minId,
                $this->maxId,
                self::BATCH_SIZE );

            foreach ( $messages as $message ) {
                $currentId++;
                $this->storeMessage( $message );
                $this->updateProgress( ++$count, $total );
            }

            $this->currentId = $currentId - 1;
            $this->emitter->emit( Sync::EVENT_CHECK_HALT );
            $this->account->ping();
        }

        $this->printMemory();
        $this->account->ping();
        $this->emitter->emit( Sync::EVENT_GARBAGE_COLLECT );
    }

    /**
     * Stores a new message into the internal array.
     * @param MessageModel $message
     */
    private function storeMessage( MessageModel $message )
    {
        $threadMessage = new ThreadMessage( $message );
        $messageId = $threadMessage->messageId;

        if ( isset( $this->messages[ $messageId ] ) ) {
            $existingMessage = $this->messages[ $messageId ];
            $existingMessage->merge( $threadMessage );
            $this->messages[ $messageId ] = $existingMessage;
        }
        else {
            $this->unthreaded[] = $messageId;
            $this->messages[ $messageId ] = $threadMessage;
        }
    }

    /**
     * Runs the thread ID computation across all messages.
     */
    private function updateThreadIds()
    {
        $count = 0;
        $total = count( $this->messages );
        $noun = Fn\plural( 'message', $total );
        $this->log->debug(
            "Threading {$total} {$noun} for {$this->account->email}" );
        $this->printMemory();

        if ( $this->interactive ) {
            $this->cli->whisper( "Threading Pass 2" );
        }

        foreach ( $this->unthreaded as $unthreadedId ) {
            $count++;
            $refs = [];
            $processed = [];
            $threadId = NULL;
            $message = $this->messages[ $unthreadedId ];
            $this->updateMessageThread( $message, $refs, $processed, $threadId );

            if ( $count % self::BATCH_SIZE === 0 ) {
                $this->printMemory();
                $this->account->ping();
                $this->log->debug( "{$count} of {$total} threaded" );
                $this->emitter->emit( Sync::EVENT_CHECK_HALT );
                $this->emitter->emit( Sync::EVENT_GARBAGE_COLLECT );
            }

            if ( ! $threadId && ! $refs ) {
                continue;
            }

            // Use this for subject threading (not done)
            // $threadMeta = new ThreadMeta( $threadId );

            // Update all processed messages with this set of references,
            // and store an index with information about each thread. This
            // index will be used for the final thread pass (subject line).
            foreach ( $refs as $messageId => $index ) {
                $message = $this->messages[ $messageId ];
                $message->setThreadId( $threadId );
                // Un-comment for subject threading
                // $threadMeta->addMessage( $message );
            }

            // Set the indexes with this thread meta info
            // Uncomment form subject threading
            // if ( $threadMeta->exists() ) {
            //     $key = $threadMeta->getKey();
            //     $hash = $threadMeta->subjectHash;
            //     $this->threadMeta[ $threadId ] = $threadMeta;
            //     $this->subjectHashes[ $hash ][ $key ] = $threadId;
            // }
        }

        // Clear the unthreaded array
        $this->unthreaded = [];
    }

    /**
     * Recurses through the message's references and builds an array
     * of all references across all known messages.
     * @param ThreadMessage $message
     * @param array $refs Master list of common message IDs
     * @param array $processed List of processed message IDs
     * @param int $threadId Final thread ID to set
     */
    private function updateMessageThread(
        ThreadMessage $message,
        array &$refs,
        array &$processed,
        &$threadId )
    {
        if ( ! $threadId
            || ( $message->getThreadId()
                && $message->getThreadId() < $threadId ) )
        {
            $threadId = $message->getThreadId();
        }

        if ( isset( $this->allProcessed[ $message->messageId ] ) ) {
            return;
        }

        $refs = $message->addReferences( $refs );
        $processed[ $message->messageId ] = TRUE;
        $this->allProcessed[ $message->messageId ] = TRUE;

        // For each reference, add it's references to our set
        // and then recursively process it.
        foreach ( $message->references as $refId => $index ) {
            if ( ! isset( $this->messages[ $refId ] ) ) {
                $this->messages[ $refId ] = new ThreadMessage(
                    new MessageModel([
                        'id' => NULL,
                        'references' => '',
                        'thread_id' => NULL,
                        'in_reply_to' => '',
                        'message_id' => $refId
                    ]));
            }

            $this->updateMessageThread(
                $this->messages[ $refId ],
                $refs,
                $processed,
                $threadId );
        }
    }

    /**
     * Update all dirty messages with a new thread ID.
     */
    private function commitThreadIds()
    {
        $count = 0;
        $updateCount = 0;
        $transactionStarted = FALSE;
        $messageModel = new MessageModel;
        $total = count( $this->messages );
        $this->log->debug(
            "Saving new thread IDs for {$this->account->email}" );
        $this->printMemory();
        $this->startProgress( 3, $total );

        foreach ( $this->messages as $message ) {
            if ( $message->hasUpdate() ) {
                $updateCount += count( $message->ids );
            }

            if ( $updateCount && ! $transactionStarted ) {
                $messageModel->db()->beginTransaction();
                $transactionStarted = TRUE;
            }

            $message->save( $messageModel );

            // After we get enough to make, commit them
            if ( $updateCount > self::BATCH_SIZE ) {
                $messageModel->db()->commit();
                $transactionStarted = FALSE;
                $updateCount = 0;
            }

            $this->updateProgress( ++$count, $total );

            if ( $count % self::BATCH_SIZE === 0 ) {
                $this->emitter->emit( Sync::EVENT_CHECK_HALT );
                $this->emitter->emit( Sync::EVENT_GARBAGE_COLLECT );
            }
        }

        if ( $transactionStarted ) {
            $messageModel->db()->commit();
        }
    }

    private function startProgress( $pass, $total )
    {
        if ( $this->interactive ) {
            $noun = Fn\plural( 'message', $total );
            $this->cli->whisper( "Threading Pass {$pass}" );
            $this->cli->whisper(
                "Processing {$total} {$noun} for {$this->account->getEmail()}:" );
            $this->progress = $this->cli->progress()->total( 100 );
        }
    }

    private function updateProgress( $count, $total )
    {
        if ( $this->interactive && $count <= $total ) {
            $this->progress->current( ( $count / $total ) * 100 );
        }
    }

    private function printMemory()
    {
        $this->log->debug(
            "Memory usage: ". Fn\formatBytes( memory_get_usage() ) .
            ", real usage: ". Fn\formatBytes( memory_get_usage( TRUE ) ) .
            ", peak usage: ". Fn\formatBytes( memory_get_peak_usage() ) );
    }
}