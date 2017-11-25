<?php

namespace App;

use App\Model\Account;
use App\Model\Message;
use Zend\Escaper\Escaper;

class Messages
{
    private $accountId;

    const UTF8 = 'utf-8';
    const TIME = 'g:i a';
    const DATE_SHORT = 'M j';
    const DATE_FULL = 'Y-m-d';

    /**
     * @param Account $account
     */
    public function __construct( Account $account )
    {
        $this->accountId = $account->id;
    }

    /**
     * Load the threads for a folder. Returns two arrays, a starred
     * (or flagged) collection, and non-starred.
     * @param int $folderId
     * @param int $limit
     * @param int $offset
     * @return [ array, array ] Messages
     */
    public function getThreads( $folderId, $limit = 50, $offset = 0 )
    {
        $flagged = [];
        $unflagged = [];
        $escaper = new Escaper( self::UTF8 );
        $messages = (new Message)->getThreadsByFolder(
            $this->accountId,
            $folderId );

        foreach ( $messages as $message ) {
            $this->setNameList( $message );
            $this->setDisplayDate( $message );
            $this->setSnippet( $message, $escaper );

            if ( $message->flagged == 1 ) {
                $flagged[] = $message;
            }
            else {
                $unflagged[] = $message;
            }
        }

        return [ $flagged, $unflagged ];
    }

    /**
     * Prepares an HTML-safe snippet to display in the message line.
     * @param Message $message
     * @param Escaper $escaper
     */
    private function setSnippet( Message &$message, Escaper $escaper )
    {
        $text = strip_tags( $message->text_plain );
        $message->snippet = trim( $escaper->escapeHtml( $text ) );
    }

    /**
     * Prepares a list of names of people involved in the message thread
     * to display in the message line.
     * @todo Make this smarter, right now it only shows original from but
     *   gmail shows multiple people on the thread.
     * @param Message $message
     */
    private function setNameList( Message &$message )
    {
        $message->names = $message->from;

        if ( $message->thread_count > 1 ) {
            $message->names .= "(". $message->thread_count .")";
        }
    }

    /**
     * Prepares a human-readable date for the message line.
     * @param Message $message
     */
    private function setDisplayDate( Message &$message )
    {
        $today = date( self::DATE_FULL );
        $messageTime = strtotime( $message->date );
        $messageDate = date( self::DATE_FULL, $messageTime );

        if ( $today === $messageDate ) {
            $message->display_date = date( self::TIME, $messageTime );
        }
        else {
            $message->display_date = date( self::DATE_SHORT, $messageTime );
        }
    }
}