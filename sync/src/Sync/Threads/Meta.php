<?php

namespace App\Sync\Threads;

class Meta
{
    public $end;
    public $start;
    public $threadId;
    public $subjectHash;
    public $familyThreadIds = [];

    public const FIVE_DAYS = 432000;
    public const THREE_DAYS = 259200;

    public function __construct($threadId)
    {
        $this->threadId = $threadId;
        $this->familyThreadIds[] = $threadId;
    }

    public function exists()
    {
        return $this->start && $this->end && $this->subjectHash;
    }

    public function getKey()
    {
        return $this->start.':'.$this->threadId;
    }

    public function addMessage(Message $message)
    {
        if (! $this->end || ! $this->start) {
            $this->end = $message->timestamp;
            $this->start = $message->timestamp;
            $this->subjectHash = $message->subjectHash;

            return;
        }

        if ($message->timestamp > $this->end) {
            $this->end = $message->timestamp;
        }

        if ($message->timestamp < $this->start) {
            $this->start = $message->timestamp;
        }
    }

    public function merge(Meta $meta)
    {
        if ($meta->end > $this->end) {
            $this->end = $meta->end;
        }

        if ($meta->start < $this->start) {
            $this->start = $meta->start;
        }

        $this->familyThreadIds = array_unique(
            array_merge(
                $this->familyThreadIds,
                $meta->familyThreadIds
            ));
    }

    /**
     * Two messages are "close to" each other if their thread
     * start and end dates are within a specified amount of time.
     * This can be tweaked to the help the user experience of the
     * threading system.
     *
     * @return bool
     */
    public function isCloseTo(Meta $meta)
    {
        return $meta->start < $this->end
            || ($meta->end > $this->start && $meta->end < $this->end)
            || abs($meta->start - $this->end) < self::FIVE_DAYS
            || abs($this->start - $meta->end) < self::FIVE_DAYS;
    }
}
