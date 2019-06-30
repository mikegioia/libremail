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
use App\Exceptions\DatabaseUpdate as DatabaseUpdateException;
use Pb\Imap\Exceptions\MessageSizeLimit as MessageSizeLimitException;

class Messages
{
    private $log;
    private $cli;
    private $emitter;
    private $mailbox;
    private $interactive;
    private $skipContent = false;

    const FLAG_UNSEEN = 'UNSEEN';
    const FLAG_FLAGGED = 'FLAGGED';

    const OPT_SKIP_CONTENT = 'skip_content';

    /**
     * @param Logger $log
     * @param CLImate $cli
     * @param Emitter $emitter
     * @param Mailbox $mailbox
     * @param bool $interactive
     */
    public function __construct(
        Logger $log,
        CLImate $cli,
        Emitter $emitter,
        Mailbox $mailbox,
        bool $interactive,
        array $options = []
    ) {
        $this->log = $log;
        $this->cli = $cli;
        $this->emitter = $emitter;
        $this->mailbox = $mailbox;
        $this->interactive = $interactive;

        if (isset($options[self::OPT_SKIP_CONTENT])) {
            $this->skipContent = $options[self::OPT_SKIP_CONTENT];
        }
    }

    /**
     * Syncs the content, threads, and flags for all messages in a folder.
     *
     * @param AccountModel $account
     * @param FolderModel $folder
     * @param array $options (see syncMessages)
     */
    public function run(AccountModel $account, FolderModel $folder, array $options)
    {
        $newIds = $this->mailbox->getUniqueIds();
        $savedIds = (new MessageModel)->getSyncedIdsByFolder(
            $account->getId(),
            $folder->getId()
        );

        $this->updateMessageNumbers($newIds, $savedIds, $folder);
        $this->downloadMessages($newIds, $savedIds, $folder, $options);
        $this->markDeleted($newIds, $savedIds, $folder, $options);
        $this->updateSeenFlags($account, $folder);
        $this->updateFlaggedFlags($account, $folder);
        $this->flushPurged($account, $folder);
    }

    /**
     * This is the engine that downloads and saves messages for a
     * given mailbox and folder. Given a list of current message IDs
     * retrieved from IMAP, and a list of the IDs we have in the
     * database, copy all the new messages down and mark the removed
     * ones as such in the database.
     *
     * @param array $newIds
     * @param array $savedIds
     * @param FolderModel $folder
     * @param array $options (see syncMessages)
     */
    private function downloadMessages(
        array $newIds,
        array $savedIds,
        FolderModel $folder,
        array $options
    ) {
        // First get the list of messages to download by taking
        // a diff of the arrays. Download all these messages.
        $i = 1;
        $progress = null;
        $total = count($newIds);

        // Ignore the draft folder, so that we re-download draft content
        if ($folder->isDrafts()) {
            $toDownload = $newIds;
        } else {
            $toDownload = array_diff($newIds, $savedIds);
        }

        $count = count($toDownload);
        $syncedCount = $total - $count;
        $noun = Fn\plural('message', $total);
        $this->log->debug("Downloading messages in {$folder->name}");

        if ($count) {
            $this->log->info("{$folder->name}: found $total $noun, $count new");
        } else {
            $this->log->debug("Found $total $noun, none are new");
        }

        // Update folder stats with count
        $folder->saveStats($total, $syncedCount);

        if (true === Fn\get($options, Sync::OPT_SKIP_DOWNLOAD)) {
            return;
        }

        if (! $count) {
            $this->log->debug("No new messages, skipping {$folder->name}");

            return;
        }

        if ($this->interactive) {
            $noun = Fn\plural('message', $count);
            $this->cli->whisper(
                "Syncing $count new $noun in {$folder->name}:");
            $progress = $this->cli->progress()->total(100);
        }

        // Sort these newest first to get the new mail earlier
        arsort($toDownload);

        foreach ($toDownload as $messageId => $uniqueId) {
            $this->downloadMessage($messageId, $uniqueId, $folder);

            if ($this->interactive) {
                $progress->current(($i++ / $count) * 100);
            }

            $message = null;
            $imapMessage = null;

            // Save stats about the folder
            $folder->saveStats($total, ++$syncedCount);

            // After each download, try to reclaim memory.
            $this->emitter->emit(Sync::EVENT_GARBAGE_COLLECT);
            $this->emitter->emit(Sync::EVENT_CHECK_HALT);
        }

        $this->emitter->emit(Sync::EVENT_GARBAGE_COLLECT);
    }

    /**
     * Download a specified message by message ID.
     *
     * @param int $messageId
     * @param int $uniqueId
     * @param FolderModel $folder
     */
    private function downloadMessage(int $messageId, int $uniqueId, FolderModel $folder)
    {
        try {
            $imapMessage = $this->mailbox->getMessage($messageId);
            $message = new MessageModel([
                'synced' => 1,
                'folder_id' => $folder->getId(),
                'account_id' => $folder->getAccountId(),
            ]);
            // We interpret any message received as valid, regardless
            // of the \Deleted flag. If the message's UID no longer
            // comes back, then we mark it as deleted. This is most
            // likely only relevant for drafts.
            $imapMessage->flags->deleted = 0;
            $message->setMessageData($imapMessage, [
                // This will trim subjects to the max size
                MessageModel::OPT_TRUNCATE_FIELDS => true,
                MessageModel::OPT_SKIP_CONTENT => $this->skipContent
            ]);
        } catch (PDOException $e) {
            throw $e;
        } catch (MessageSizeLimitException $e) {
            $this->log->notice(
                "Size exceeded during download of message $messageId: ".
                $e->getMessage()
            );

            return;
        } catch (Exception $e) {
            $this->log->warning(
                "Failed download for message {$messageId}: ".
                $e->getMessage());
            $this->emitter->emit(Sync::EVENT_CHECK_CLOSED_CONN, [$e]);

            return;
        }

        // Save the meta info that comes back from the server
        // regardless if the record exists.
        try {
            $message->save([], ['thread_id']);
        } catch (ValidationException $e) {
            $this->log->notice(
                "Failed validation for message $messageId: ".
                $e->getMessage()
            );
        }
    }

    /**
     * For any messages we have saved that didn't come back from the
     * mailbox, mark them as deleted in the database.
     *
     * @param array $newIds
     * @param array $savedIds
     * @param FolderModel $folder
     */
    private function markDeleted(array $newIds, array $savedIds, FolderModel $folder)
    {
        $toDelete = array_diff($savedIds, $newIds);
        $count = count($toDelete);

        if (! $count) {
            $this->log->debug("No messages to delete in {$folder->name}");

            return;
        }

        $this->log->info(
            "Marking $count deletion".(1 === $count ? '' : 's').
            " in {$folder->name}"
        );

        try {
            (new MessageModel)->markDeleted(
                array_values($toDelete),
                $folder->getAccountId(),
                $folder->getId()
            );
        } catch (PDOException $e) {
            $this->log->notice(
                "Failed updating deleted messages in {$folder->name}: ".
                $e->getMessage()
            );
        } catch (ValidationException $e) {
            $this->log->notice(
                'Failed validation for marking deleted messages: '.
                $e->getMessage()
            );
        }
    }

    /**
     * Updates the messages with any new message numbers. These numbers
     * can change and will need their references updated on sync.
     *
     * @param array $newIds
     * @param array $savedIds
     * @param FolderModel $folder
     */
    private function updateMessageNumbers(array $newIds, array $savedIds, FolderModel $folder)
    {
        $messageModel = new MessageModel;

        if (! $newIds || ! $savedIds) {
            return;
        }

        // Flip these so that unique ID is the index
        $newIds = array_flip($newIds);
        $savedIds = array_flip($savedIds);
        // Only update messages we've stored already
        $toUpdate = array_intersect_key($newIds, $savedIds);
        $count = count($toUpdate);

        if ($count) {
            $this->log->debug(
                "Updating $count with new message numbers in {$folder->name}"
            );
        }

        try {
            foreach ($toUpdate as $uid => $newMsgNo) {
                if ((int) $savedIds[$uid] !== (int) $newIds[$uid]) {
                    $messageModel->saveMessageNo($uid, $folder->id, $newMsgNo);
                    $this->emitter->emit(Sync::EVENT_CHECK_HALT);
                }
            }
        } catch (DatabaseUpdateException $e) {
            $this->log->notice(
                "Failed updating message number for UID $uid in ".
                "folder {$folder->id}: ".$e->getMessage()
            );
        }
    }

    /**
     * Syncs the seen flag between the IMAP mailbox and SQL.
     *
     * @param AccountModel $account
     * @param FolderModel $model
     */
    private function updateSeenFlags(AccountModel $account, FolderModel $folder)
    {
        // Fetch all unseen message IDs from the mailbox
        $unseenIds = $this->mailbox->search(self::FLAG_UNSEEN, true);

        // Mark as unseen anything in this collection
        if ($unseenIds) {
            $count = count($unseenIds);

            try {
                $updated = (new MessageModel)->markFlag(
                    $unseenIds,
                    $folder->getAccountId(),
                    $folder->getId(),
                    MessageModel::FLAG_SEEN,
                    false
                );

                if ($updated) {
                    $this->log->info(
                        "Marking $updated as unseen in {$folder->name}"
                    );
                }
            } catch (ValidationException $e) {
                $this->log->notice(
                    'Failed validation for marking unseen messages: '.
                    $e->getMessage()
                );
            }
        }

        // Mark as seen anything unseen that's not in this collection
        try {
            $updated = (new MessageModel)->markFlag(
                $unseenIds,
                $folder->getAccountId(),
                $folder->getId(),
                MessageModel::FLAG_SEEN,
                true,
                true
            ); // Inverse

            if ($updated) {
                $this->log->debug(
                    "Marking {$updated} as seen in {$folder->name}"
                );
            }
        } catch (ValidationException $e) {
            $this->log->notice(
                'Failed validation for marking seen messages: '.
                $e->getMessage()
            );
        }
    }

    /**
     * Syncs the flagged (starred) flag between the IMAP mailbox and SQL.
     *
     * @param AccountModel $account
     * @param FolderModel $model
     */
    private function updateFlaggedFlags(AccountModel $account, FolderModel $folder)
    {
        // Fetch all flagged message IDs from the mailbox
        $flaggedIds = $this->mailbox->search(self::FLAG_FLAGGED, true);

        // Mark as flagged anything in this collection
        if ($flaggedIds) {
            $count = count($flaggedIds);

            try {
                $updated = (new MessageModel)->markFlag(
                    $flaggedIds,
                    $folder->getAccountId(),
                    $folder->getId(),
                    MessageModel::FLAG_FLAGGED,
                    true
                );

                if ($updated) {
                    $this->log->info(
                        "Marking $updated as flagged in {$folder->name}"
                    );
                }
            } catch (ValidationException $e) {
                $this->log->notice(
                    'Failed validation for marking flagged messages: '.
                    $e->getMessage()
                );
            }
        }

        // Mark as seen anything unseen that's not in this collection
        try {
            $updated = (new MessageModel)->markFlag(
                $flaggedIds,
                $folder->getAccountId(),
                $folder->getId(),
                MessageModel::FLAG_FLAGGED,
                false,
                true
            ); // Inverse

            if ($updated) {
                $this->log->debug(
                    "Marking {$updated} as un-flagged in {$folder->name}"
                );
            }
        } catch (ValidationException $e) {
            $this->log->notice(
                'Failed validation for marking un-flagged messages: '.
                $e->getMessage()
            );
        }
    }

    /**
     * Cleans up any messages in the folder that we're marked for purge.
     * These are duplicate messages created by the client, and after
     * they're synced to the mailbox are left as duplicates in SQL. They
     * have no message number or unique ID and can't be updated during
     * the sync process.
     *
     * @param AccountModel $account
     * @param FolderModel $model
     */
    private function flushPurged(AccountModel $account, FolderModel $folder)
    {
        // Mark as seen anything unseen that's not in this collection
        try {
            $updated = (new MessageModel)->deleteMarkedForPurge(
                $account->getId(),
                $folder->getId()
            );

            if ($updated) {
                $this->log->debug(
                    "Purged {$updated} from {$folder->name}"
                );
            }
        } catch (PDOException $e) {
            $this->log->notice(
                'Failed removing purged messages: '.
                $e->getMessage()
            );
        }
    }
}
