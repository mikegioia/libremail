<?php

namespace App\Sync\Actions;

use Pb\Imap\Mailbox;
use App\Model\Task as TaskModel;

class DeleteOutbox extends Base
{
    /**
     * This is a placeholder for the rollback operation. The task
     * is created when the message is mark as deleted. There's nothing
     * for this task to do here.
     *
     * @see Base for params
     */
    public function run(Mailbox $mailbox)
    {
        if (0 === (int) $this->outbox->deleted) {
            $this->outbox->softDelete();
        }

        return true;
    }

    public function getType()
    {
        return TaskModel::TYPE_DELETE_OUTBOX;
    }
}
