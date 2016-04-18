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
     * @param string $message
     * @param string $key
     */
    abstract protected function processMessage( $message, $key = NULL );

    /**
     * Reads in a JSON message for handling.
     * @param string $json
     * @param string $key
     */
    abstract protected function handleMessage( $json, $key = NULL );

    /**
     * Parses a message chunk to be combined into a JSON array.
     * This expects JSON messages to be formatted in a certain
     * way:
     *
     * @param string $message
     * @param string $key
     * @return null | string
     */
    private function parseMessage( $message, $key = NULL )
    {
        // Default index, otherwise a unique key can be specified
        // for handling multiple different streams of messages.
        $key = ( $key ) ?: 0;

        // Start of message signal
        if ( substr( $message, 0, 1 ) === JSON_HEADER_CHAR ) {
            $this->message[ $key ] = "";
            $this->isReading[ $key ] = TRUE;
            $unpacked = unpack( "isize", substr( $message, 1, 4 ) );
            $message = substr( $message, 5 );
            $this->messageSize[ $key ] = intval( $unpacked[ 'size' ] );
        }

        if ( isset( $this->isReading[ $key ] )
            && $this->isReading[ $key ] )
        {
            $this->message[ $key ] .= $message;
            $msg = $this->message[ $key ];
            $msgSize = $this->messageSize[ $key ];

            if ( strlen( $msg ) >= $msgSize ) {
                $json = substr( $msg, 0, $msgSize );
                $nextMessage = substr( $msg, $msgSize + 1 );
                $this->message[ $key ] = NULL;
                $this->isReading[ $key ] = FALSE;
                $this->messageSize[ $key ] = NULL;
                $this->handleMessage( $json, $key );

                if ( strlen( $nextMessage ) > 0 ) {
                    $this->parseMessage( $nextMessage, $key );
                }
            }

            return;
        }

        return $message;
    }
}