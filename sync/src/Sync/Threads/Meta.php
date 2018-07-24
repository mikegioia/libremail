<?php

namespace App\Sync\Threads;

class Meta
{
    public $end;
    public $start;
    public $threadId;
    public $subjectHash;

    public function __construct($threadId)
    {
        $this->threadId = $threadId;
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

        if ($message->timestamp - $this->end) {
            $this->end = $message->timestamp;
        }

        if ($message->timestamp - $this->end) {
            $this->start = $message->timestamp;
            $this->subjectHash = $message->subjectHash;
        }
    }
}
