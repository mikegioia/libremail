<?php

namespace App\Actions;

use DateTime;
use App\Folders;
use DateTimeZone;
use DateInterval;
use App\Model\Task as TaskModel;
use App\Messages\MessageInterface;
use App\Exceptions\ServerException;
use App\Model\Outbox as OutboxModel;

class Queue extends Delete
{
    /**
     * Takes an outbox message and updates it to be delivered
     * via SMTP by the sync engine. The draft message is deleted,
     * the outbox message loses its draft status, and a `send`
     * action is queued to deliver the message via SMTP.
     *
     * @see Base for params
     *
     * @param $options Options used:
     *   Outbox `outbox_message` Required
     *   string `send_after` Optional local date string
     *
     * @throws ServerException
     */
    public function update(MessageInterface $message, Folders $folders, array $options = [])
    {
        $outboxMessage = $options['outbox_message'] ?? null;

        if (! $outboxMessage) {
            throw new ServerException(
                'Outbox message missing in queue mail task'
            );
        }

        $outboxMessage->draft = 0; // no longer a draft
        $outboxMessage->send_after = $this->getSendAfter(
            $outboxMessage,
            $options['send_after'] ?? null
        );

        $outboxMessage->save();

        // Create a task to send the outbox message
        TaskModel::create(
            $message->id,
            $message->account_id,
            TaskModel::TYPE_SEND,
            null,
            null,
            $outboxMessage->id
        );

        // Mark the external draft message as deleted
        parent::update($message, $folders, $options);
    }

    /**
     * Returns a SQL formatted date in UTC representing the earliest
     * time to deliver/send the email. If $sendAfter comes in, then
     * this time will be converted to a UTC time. Otherwise, a default
     * of 60 seconds from now is used.
     *
     * @param OutboxModel $message
     * @param string $sendAfter Must be in user's local time
     *
     * @return string
     */
    private function getSendAfter(OutboxModel $message, string $sendAfter = null)
    {
        if ($sendAfter) {
            $date = $message->localDate($sendAfter);
            $date->setTimezone(new DateTimeZone('UTC'));
        } else {
            // Get the default delay from the environment settings
            $delay = $this->env('SEND_DELAY', 60);
            $date = new DateTime(gmdate(DATE_ATOM));
            $date->add(new DateInterval('PT'.$delay.'S'));
        }

        return $date->format(DATE_DATABASE);
    }
}
