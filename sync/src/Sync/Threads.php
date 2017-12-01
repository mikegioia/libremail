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
use App\Sync\Threads\Message as ThreadMessage;

class Threads
{
    private $log;
    private $cli;
    private $emitter;
    private $account;
    private $progress;
    private $interactive;

    // Master index of messages and references
    private $messages = [];
    // Index of the most recent message known
    private $maxId;
    // Total IDs to fetch
    private $totalIds;
    // Current index position in message processing
    private $currentId;

    // How many messages to fetch at once
    const BATCH_SIZE = 1000;

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
        $interactive )
    {
        $this->log = $log;
        $this->cli = $cli;
        $this->emitter = $emitter;
        $this->interactive = $interactive;
    }

    /**
     * Run the threading for an account. If the account is different
     * than the one we have in the cache, then start over. This should
     * either be set up to handle multiple accounts, or the sync script
     * should not run for multiple accounts on the same process.
     * @param AccountModel $account
     */
    public function run( AccountModel $account )
    {
        if ( ! $this->account || $account->id !== $this->account->id ) {
            $this->maxId = NULL;
            $this->totalIds = 0;
            $this->currentId = 0;
            $this->messages = [];
            $this->allProcessed = [];
            $this->account = $account;
        }

        $this->storeMaxMessageId();
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
    private function storeMaxMessageId()
    {
        $this->maxId = (new MessageModel)->getMaxMessageId( $this->account->id );
    }

    private function storeTotalMessageIds()
    {
        $this->totalIds = (new MessageModel)->countByAccount( $this->account->id );
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
        $i = $this->currentId;
        $messageModel = new MessageModel;
        $this->log->debug(
            "Storing messages for threading for {$this->account->email}" );
        $this->printMemory();
        $this->startProgress( 1, $this->totalIds );

        for ( $i; $i < $this->maxId; $i += self::BATCH_SIZE ) {
            $messages = $messageModel->getRangeForThreading(
                $this->account->id,
                $i,
                $this->maxId,
                self::BATCH_SIZE );
            
            foreach ( $messages as $message ) {
                $this->storeMessage( $message );
                $this->updateProgress( ++$count );
            }

            $this->currentId = $i;
            $this->emitter->emit( Sync::EVENT_CHECK_HALT );
        }

        $this->printMemory();
        $this->emitter->emit( Sync::EVENT_GARBAGE_COLLECT );
    }

    /**
     * Stores a new message into the internal array.
     * @param MessageModel $message
     */
    private function storeMessage( MessageModel $message )
    {
        $threadMessage = new ThreadMessage( $message );
        $this->messages[ $threadMessage->getMessageId() ] = $threadMessage;
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

        foreach ( $this->messages as $message ) {
            $count++;
            $refs = [];
            $processed = [];
            $threadId = NULL;
            $this->updateMessageThread( $message, $refs, $processed, $threadId );

            if ( $count % self::BATCH_SIZE === 0 ) {
                $this->printMemory();
                $this->log->debug( "{$count} of {$total} threaded" );
                $this->emitter->emit( Sync::EVENT_CHECK_HALT );
                $this->emitter->emit( Sync::EVENT_GARBAGE_COLLECT );
            }

            // Update all processed messages with this set of references
            foreach ( $processed as $messageId => $index ) {
                $message = $this->messages[ $messageId ];

                if ( $message->getId() ) {
                    $message->setThreadId( $threadId );
                }
            }
        }
    }

    /**
     * Recurses through the message's references and builds an array
     * of all references across all known messages.
     * @param ThreadMessage $message
     * @param array $refs Master list of common message IDs
     * @param array $processed List of processed message IDs
     * @param int $threadId Final thread ID to set
     */
    private function updateMessageThread( ThreadMessage $message, &$refs, &$processed, &$threadId )
    {
        if ( isset( $this->allProcessed[ $message->getMessageId() ] ) ) {
            return;
        }

        $refs = $message->addReferences( $refs );
        $processed[ $message->getMessageId() ] = TRUE;
        $this->allProcessed[ $message->getMessageId() ] = TRUE;

        if ( ! $threadId
            || ( $message->getId() && $message->getId() < $threadId ) )
        {
            $threadId = $message->getId();
        }

        // For each reference, add it's references to our set
        // and then recursively process it.
        foreach ( $message->getReferences() as $refId => $index ) {
            if ( ! isset( $this->messages[ $refId ] ) ) {
                $this->messages[ $refId ] = new ThreadMessage(
                    new MessageModel([
                        'id' => NULL,
                        'references' => '',
                        'in_reply_to' => '',
                        'message_id' => $refId,
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
        $total = count( $this->messages );
        $this->log->debug(
            "Saving new thread IDs for {$this->account->email}" );
        $this->printMemory();
        $this->startProgress( 3, $total );

        foreach ( $this->messages as $message ) {
            $message->save();
            $this->updateProgress( ++$count );

            if ( $count % 100 === 0 ) {
                $this->emitter->emit( Sync::EVENT_CHECK_HALT );
                $this->emitter->emit( Sync::EVENT_GARBAGE_COLLECT );
            }
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

    private function updateProgress( $count )
    {
        if ( $this->interactive && $count <= $this->totalIds ) {
            $this->progress->current( ( $count / $this->totalIds ) * 100 );
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