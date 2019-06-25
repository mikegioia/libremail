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
}
