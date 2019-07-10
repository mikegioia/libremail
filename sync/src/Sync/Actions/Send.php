<?php

namespace App\Sync\Actions;

use DateTime;
use Pb\Imap\Mailbox;
use App\Sync\Actions;
use App\Model\Task as TaskModel;

class Send extends Base
{
    /**
     * Tasks are ready if the outbox message is set to be delivered.
     * This function returns true if the `send_after` field is in the
     * past.
     *
     * @return bool
     */
    public function isReady()
    {
        // If the outbox message is deleted, update task reason and get out
        if (1 === (int) $this->outbox->deleted) {
            $this->task->ignore(Actions::IGNORE_OUTBOX_DELETED);

            return false;
        }

        $now = new DateTime;
        $sendAfter = new DateTime($this->outbox->send_after);

        if (! $this->outbox->send_after || $now < $sendAfter) {
            return false;
        }

        return true;
    }

    /**
     * Copies a message to a new folder.
     *
     * @see Base for params
     */
    public function run(Mailbox $mailbox)
    {
        // Create the IMAP message and perform the SMTP send


        // If successful, create a new temporary message in the
        // sent mail mailbox with purge=1 to ensure it's removed
        // upon re-sync


        // Update the status of the outbox message to sent if succeeded
        // or failed if not (log a reason to update_history)


        exit('ready to send a message');
    }

    public function getType()
    {
        return TaskModel::TYPE_SEND;
    }
}
