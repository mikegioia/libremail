<?php

namespace App\Server;

use GuzzleHttp\Psr7\Response as Psr7Response;

class Response extends Psr7Response
{
    /**
     * @return string
     */
    public function __toString()
    {
        return $this->getMessage();
    }

    /**
     * Get the entire response as a string
     *
     * @return string
     */
    public function getMessage()
    {
        $body = (string) $this->getBody();
        $message = $this->getRawHeaders();

        // Only include the body in the message if the size is < 2MB
        if (strlen($body) < 2097152) {
            $message .= $body;
        }

        return $message;
    }

    /**
     * Get the the raw message headers as a string
     *
     * @return string
     */
    public function getRawHeaders()
    {
        $lines = $this->getHeaderLines();
        $headers = sprintf(
            "HTTP/1.1 %s %s \r\n",
            $this->getStatusCode(),
            $this->getReasonPhrase());

        if (! empty($lines)) {
            $headers .= implode("\r\n", $lines) . "\r\n";
        }

        return $headers . "\r\n";
    }

    /**
     * Get the the header lines formatted as an array of strings.
     *
     * @return array
     */
    public function getHeaderLines()
    {
        $headers = [];

        foreach ($this->getHeaders() as $name => $values) {
            foreach ($values as $value) {
                $headers[] = $name.': '.$value;
            }
        }

        return $headers;
    }
}