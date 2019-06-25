<?php

namespace App\Sync\Actions;

use stdClass;
use Pb\Imap\Mailbox;
use Pb\Imap\Message;
use App\Model\Task as TaskModel;

abstract class Base
{
    protected $task;
    protected $folder;
    protected $message;
    protected $imapMessage;

    public function __construct(
        TaskModel $task,
        stdClass $folder,
        stdClass $message,
        Message $imapMessage
    ) {
        $this->task = $task;
        $this->folder = $folder;
        $this->message = $message;
        $this->imapMessage = $imapMessage;
    }

    /**
     * Implemented by sub-classes.
     */
    abstract public function getType();

    abstract public function run(Mailbox $mailbox);

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
