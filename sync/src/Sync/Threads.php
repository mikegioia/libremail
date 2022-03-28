<?php

namespace App\Sync;

use App\Model\Account as AccountModel;
use App\Model\Contact as ContactModel;
use App\Model\Message as MessageModel;
use App\Sync;
use App\Sync\Threads\Message as ThreadMessage;
use App\Sync\Threads\Meta as ThreadMeta;
use App\Util;
use Evenement\EventEmitter as Emitter;
use League\CLImate\CLImate;
use Monolog\Logger;

/**
 * Groups messages into threads. This process runs in O(n).
 *
 * Pass 1: Load all messages from database and bucket by
 *   message-id. Store all references.
 * Pass 2: Run through each message's references and
 *   and compact the union of references on all messages,
 *   along with the lowest numbered internal ID as the
 *   thread ID.
 * Pass 3: Run through all thread collections and connect
 *   any messages that have the same subject are are within
 *   a certain window of time.
 * Pass 4: Iterate over each thread and save it to the
 *   database if anything has changed.
 */
class Threads
{
    private $log;
    private $cli;
    private $emitter;
    private $account;
    private $progress;
    private $interactive;

    public const EVENT_MESSAGE_DIRTY = 'message_dirty';

    public const SUBJECT_SIMILARITY = 80;

    // Index of the most recent message known
    private $maxId;
    // Total IDs to fetch
    private $totalIds;
    // Current index position in message processing
    private $currentId;
    // Master index of messages and references
    private $messages = [];
    // Master collection of addresses
    private $addresses = [];
    // Index of new, unthreaded messages
    private $unthreaded = [];
    // Index of all threads for subject matching
    private $threadMeta = [];
    // Index of subject hashes => thread IDs
    private $subjectHashes = [];
    // Index of thread IDs and their new thread ID
    private $groupedThreads = [];
    // List of dirty message IDs to update
    private $dirtyMessageIds = [];
    // Flag used when storing addresses
    private $prevAddressCount = 0;

    // How many messages to fetch at once
    public const BATCH_SIZE = 1000;

    public function __construct(Logger $log, CLImate $cli, bool $interactive)
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
     */
    public function run(AccountModel $account, Emitter $emitter)
    {
        if (! $this->account || $account->id !== $this->account->id) {
            $this->maxId = null;
            $this->totalIds = 0;
            $this->currentId = 0;
            $this->messages = [];
            $this->allProcessed = [];
            $this->account = $account;
        }

        $this->addresses = [];
        $this->emitter = $emitter;

        $this->updateEmitter();
        $this->storeMessageIdBounds();
        $this->storeTotalMessageIds();

        if (! $this->messages || $this->currentId < $this->maxId) {
            $this->storeMessages();
        }

        $this->setThreadIds();
        $this->combineThreads();
        $this->commitThreadIds();
        $this->commitAddressList();
    }

    /**
     * Finds the last message ID to store and update.
     */
    private function storeMessageIdBounds()
    {
        $model = new MessageModel;

        $this->maxId = $model->getMaxMessageId($this->account->id);

        if (! $this->currentId) {
            $this->currentId = $model->getMinMessageId($this->account->id);
        }
    }

    private function storeTotalMessageIds()
    {
        $this->totalIds = (new MessageModel)->countByAccount($this->account->id);
    }

    private function updateEmitter()
    {
        $this->emitter->on(self::EVENT_MESSAGE_DIRTY, function ($id) {
            $this->dirtyMessageIds[$id] = true;
        });
    }

    /**
     * Load all messages, or any new messages, from the database into
     * the internal storage array. This will instantiate a new message
     * object and store the references for that message.
     *
     * @param AccountModel $account
     */
    private function storeMessages()
    {
        $count = 0;
        $minId = $this->currentId;
        $currentId = $this->currentId;
        $total = $this->maxId - $minId;
        $messageModel = new MessageModel;

        if (! $total) {
            return;
        }

        $this->log->debug(
            "Threading: storing {$total} messages for threading for ".
            "{$this->account->email}"
        );
        $this->printMemory();
        $this->startProgress(1, $total);

        for ($minId; $minId < $this->maxId; $minId += self::BATCH_SIZE) {
            $messages = $messageModel->getRangeForThreading(
                $this->account->id,
                $minId,
                $this->maxId,
                self::BATCH_SIZE
            );

            foreach ($messages as $message) {
                ++$currentId;
                $this->storeMessage($message);
                $this->updateProgress(++$count, $total);
            }

            $this->currentId = $currentId - 1;
            $this->emitter->emit(Sync::EVENT_CHECK_HALT);
            $this->account->ping();
        }

        $this->updateProgress($total, $total); // Set to complete
        $this->printMemory();
        $this->account->ping();
        $this->emitter->emit(Sync::EVENT_GARBAGE_COLLECT);
    }

    /**
     * Stores a new message into the internal array.
     */
    private function storeMessage(MessageModel $message)
    {
        $threadMessage = new ThreadMessage($message);
        $messageId = $threadMessage->messageId;

        if (isset($this->messages[$messageId])) {
            $existingMessage = $this->messages[$messageId];
            $existingMessage->merge($threadMessage);
            $this->messages[$messageId] = $existingMessage;
        } else {
            $this->unthreaded[] = $messageId;
            $this->messages[$messageId] = $threadMessage;
        }
    }

    /**
     * Runs the thread ID computation across all messages.
     */
    private function setThreadIds()
    {
        $count = 0;
        $total = count($this->messages);
        $noun = Util::plural('message', $total);

        $this->log->debug(
            "Threading Pass 2: updating {$total} {$noun} for ".
            "{$this->account->email}"
        );
        $this->printMemory();

        foreach ($this->unthreaded as $unthreadedId) {
            ++$count;
            $refs = [];
            $processed = [];
            $threadId = null;
            $message = $this->messages[$unthreadedId];

            $this->updateMessageThread($message, $refs, $processed, $threadId);

            if (0 === $count % self::BATCH_SIZE) {
                $this->printMemory();
                $this->account->ping();
                $this->log->debug("{$count} of {$total} threaded");
                $this->emitter->emit(Sync::EVENT_CHECK_HALT);
                $this->emitter->emit(Sync::EVENT_GARBAGE_COLLECT);
            }

            if (! $threadId && ! $refs) {
                continue;
            }

            // This class prepares fields used for subject threading
            $threadMeta = new ThreadMeta($threadId);

            // Update all processed messages with this set of references,
            // and store an index with information about each thread. This
            // index will be used for the final thread pass (subject line).
            foreach ($refs as $messageId => $index) {
                $refMessage = $this->messages[$messageId];

                // This should only be stored for messages that match
                // on the subject line
                if ($this->subjectIsSimilar($message, $refMessage)) {
                    $refMessage->setThreadId($threadId);
                    $threadMeta->addMessage($refMessage);
                }
            }

            // Set the indexes with this thread meta info. Also build the
            // index arrays for the subject => thread ID lookup, and the
            // the thread ID => thread meta lookup
            if ($threadMeta->exists()) {
                $key = $threadMeta->getKey();
                $hash = $threadMeta->subjectHash;
                $this->threadMeta[$threadId] = $threadMeta;
                $this->subjectHashes[$hash][$key] = $threadId;
            }
        }

        $this->unthreaded = [];
    }

    /**
     * Recurses through the message's references and builds an array
     * of all references across all known messages.
     *
     * @param array $refs Master list of common message IDs
     * @param array $processed List of processed message IDs
     * @param int $threadId Final thread ID to set
     */
    private function updateMessageThread(
        ThreadMessage $message,
        array &$refs,
        array &$processed,
        int &$threadId = null
    ) {
        if (! $threadId
            || ($message->getThreadId()
                && $message->getThreadId() < $threadId)
        ) {
            $threadId = $message->getThreadId();
        }

        if (isset($this->allProcessed[$message->messageId])) {
            return;
        }

        $refs = $message->addReferences($refs);
        $processed[$message->messageId] = true;

        $this->allProcessed[$message->messageId] = true;

        // For each reference, add it's references to our set
        // and then recursively process it
        foreach ($message->references as $refId => $index) {
            $exists = isset($this->messages[$refId]);

            if (! $exists) {
                $this->messages[$refId] = new ThreadMessage(
                    new MessageModel([
                        'id' => null,
                        'references' => '',
                        'thread_id' => null,
                        'in_reply_to' => '',
                        'message_id' => $refId
                    ])
                );
            }

            // Only process references that have a similar subject
            // This is to prevent from grouping messages that should
            // really be separate threads
            if (! $exists
                || $this->subjectIsSimilar($message, $this->messages[$refId])
            ) {
                $this->updateMessageThread(
                    $this->messages[$refId],
                    $refs,
                    $processed,
                    $threadId
                );
            }
        }
    }

    /**
     * Look at the thread subjects and try to combine any threads
     * that share a subject and are within a time window.
     */
    private function combineThreads()
    {
        $this->log->debug(
            'Threading Pass 3: combining threads by subject for '.
            "{$this->account->email}"
        );
        $this->printMemory();

        foreach ($this->subjectHashes as $hash => $threads) {
            // Sort the thread keys (dates) oldest to newest
            ksort($threads);

            $master = null;

            foreach ($threads as $threadId) {
                if (is_null($master)) {
                    $master = $this->threadMeta[$threadId];
                    continue;
                }

                $meta = $this->threadMeta[$threadId];

                // Two threads share a subject line and are within
                // a certain number of days of each other
                if ($master->isCloseTo($meta)) {
                    if ($master->threadId < $meta->threadId) {
                        $master->merge($meta);
                    } else {
                        $meta->merge($master);
                        $master = $meta;
                    }
                }
            }

            if (count($master->familyThreadIds) > 1) {
                $this->groupedThreads += array_fill_keys(
                    $master->familyThreadIds,
                    $master->threadId
                );
            }
        }

        $this->subjectHashes = [];
    }

    /**
     * Update all dirty messages with a new thread ID.
     */
    private function commitThreadIds()
    {
        $count = 0;
        $updateCount = 0;
        $transactionStarted = false;
        $messageModel = new MessageModel;
        $total = count($this->messages);

        $this->log->debug(
            'Threading: saving new thread IDs for '.
            $this->account->email
        );
        $this->printMemory();
        $this->startProgress(4, $total);

        foreach ($this->messages as $message) {
            if (isset($this->groupedThreads[$message->getThreadId()])) {
                $message->setThreadId(
                    $this->groupedThreads[$message->getThreadId()]
                );
            }

            if ($message->hasThreadId()) {
                $message->setThreadId($message->getThreadId());
            }

            if ($message->hasUpdate()) {
                $updateCount += count($message->ids);
            }

            if ($updateCount && ! $transactionStarted) {
                $messageModel->db()->beginTransaction();
                $transactionStarted = true;
            }

            $message->save($messageModel);

            // After we get enough to make, commit them
            if ($updateCount > self::BATCH_SIZE) {
                $messageModel->db()->commit();
                $transactionStarted = false;
                $updateCount = 0;
            }

            $this->addresses = array_merge(
                $this->addresses,
                $message->getAddresses()
            );

            $this->updateProgress(++$count, $total);

            if (0 === $count % self::BATCH_SIZE) {
                $this->emitter->emit(Sync::EVENT_CHECK_HALT);
                $this->emitter->emit(Sync::EVENT_GARBAGE_COLLECT);
            }
        }

        if ($transactionStarted) {
            $messageModel->db()->commit();
        }

        // We can safely clear this now
        $this->groupedThreads = [];
    }

    /**
     * Stores meta information for each contact among all
     * messages in the account. To and From are stored
     * separately because they're weighted differently.
     */
    private function commitAddressList()
    {
        // Only store these in SQL if the total count changed
        if (count($this->addresses) === $this->prevAddressCount) {
            return;
        }

        $this->prevAddressCount = count($this->addresses);

        $contacts = [];
        $counts = array_filter(
            array_count_values($this->addresses),
            function ($value) {
                return $value > 1;
            });
        $keys = [
            'name', 'tally', 'account_id'
        ];

        foreach ($counts as $name => $tally) {
            $contacts[] = [
                'name' => $name,
                'tally' => $tally,
                'account_id' => $this->account->id
            ];
        }

        // Store these in SQL
        if ($contacts) {
            (new ContactModel)->store($keys, $contacts);
        }
    }

    private function startProgress(int $pass, int $total)
    {
        if ($this->interactive) {
            $noun = Util::plural('message', $total);
            $this->log->debug(
                "Threading Pass {$pass}: processing {$total} {$noun} ".
                "for {$this->account->getEmail()}:"
            );
            $this->progress = $this->cli->progress()->total(100);
        }
    }

    private function updateProgress(int $count, int $total)
    {
        if ($this->interactive && $count <= $total) {
            $this->progress->current(($count / $total) * 100);
        }
    }

    private function printMemory()
    {
        $this->log->debug(
            'Memory usage: '.Util::formatBytes(memory_get_usage()).
            ', real usage: '.Util::formatBytes(memory_get_usage(true)).
            ', peak usage: '.Util::formatBytes(memory_get_peak_usage())
        );
    }

    private function subjectIsSimilar(ThreadMessage $base, ThreadMessage $ref)
    {
        similar_text(
            strtolower($base->subject),
            strtolower($ref->subject),
            $percent
        );

        return $percent > self::SUBJECT_SIMILARITY;
    }
}
