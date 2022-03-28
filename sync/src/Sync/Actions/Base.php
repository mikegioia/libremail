<?php

namespace App\Sync\Actions;

use App\Model\Account as AccountModel;
use App\Model\Folder as FolderModel;
use App\Model\Message as MessageModel;
use App\Model\Outbox as OutboxModel;
use App\Model\Task as TaskModel;
use Pb\Imap\Mailbox;
use Pb\Imap\Message;

abstract class Base
{
    protected $task;
    protected $folder;
    protected $outbox;
    protected $account;
    protected $message;
    protected $imapMessage;

    public function __construct(
        TaskModel $task,
        AccountModel $account,
        FolderModel $folder,
        MessageModel $message,
        OutboxModel $outbox,
        Message $imapMessage
    ) {
        $this->task = $task;
        $this->folder = $folder;
        $this->outbox = $outbox;
        $this->account = $account;
        $this->message = $message;
        $this->imapMessage = $imapMessage;
    }

    /**
     * Implemented by sub-classes.
     */
    abstract public function getType();

    abstract public function run(Mailbox $mailbox);

    /**
     * Extended in sub-classes, if necessary.
     */
    public function isReady()
    {
        return true;
    }

    /**
     * Checks if the SQL message is "real", i.e. that it has
     * a message number and unique ID. We only want to perform
     * mailbox actions on real messages, otherwise, we want to
     * mark them as "purged" and have them removed.
     *
     * @return bool True if purged
     */
    public function checkPurge()
    {
        if (! $this->message->message_no) {
            return (new MessageModel)->markPurged($this->task->message_id);
        }

        return false;
    }
}
