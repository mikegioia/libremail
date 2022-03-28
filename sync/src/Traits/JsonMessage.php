<?php

namespace App\Traits;

trait JsonMessage
{
    // Used for internal message passing
    private $message = [];
    private $isReading = [];
    private $messageSize = [];

    /**
     * Reads in a message chunk from a stream. This is to be
     * implemented in the class and should call parseMessage.
     *
     * @param string $key
     */
    abstract protected function processMessage(string $message, string $key = null);

    /**
     * Reads in a JSON message for handling.
     *
     * @param string $key
     */
    abstract protected function handleMessage(string $json, string $key = null);

    /**
     * Parses a message chunk to be combined into a JSON array.
     * This expects JSON messages to be formatted in a certain
     * way:.
     *
     * @param string $key
     */
    private function parseMessage(string $message, string $key = null)
    {
        // Default index, otherwise a unique key can be specified
        // for handling multiple different streams of messages.
        $key = $key ?: 0;

        // Start of message signal
        if (JSON_HEADER_CHAR === substr($message, 0, 1)) {
            $this->message[$key] = '';
            $this->isReading[$key] = true;
            $unpacked = unpack('isize', substr($message, 1, 4));
            $message = substr($message, 5);
            $this->messageSize[$key] = intval($unpacked['size']);
        }

        if (isset($this->isReading[$key]) && $this->isReading[$key]) {
            $this->message[$key] .= $message;
            $msg = $this->message[$key];
            $msgSize = $this->messageSize[$key];

            if (strlen($msg) >= $msgSize) {
                $json = substr($msg, 0, $msgSize);
                $nextMessage = substr($msg, $msgSize);
                $this->message[$key] = null;
                $this->isReading[$key] = false;
                $this->messageSize[$key] = null;
                $this->handleMessage($json, $key);

                if (strlen($nextMessage) > 0) {
                    $this->parseMessage($nextMessage, $key);
                }
            }

            return;
        }

        return $message;
    }
}
