<?php

namespace App;

use App\Folders;
use App\Model\Account;
use App\Model\Message;
use Zend\Escaper\Escaper;

class Messages
{
    private $folders;
    private $accountId;

    const UTF8 = 'utf-8';
    const TIME = 'g:i a';
    const DATE_SHORT = 'M j';
    const DATE_FULL = 'Y-m-d';

    /**
     * @param Account $account
     * @param Folders $folders
     */
    public function __construct( Account $account, Folders $folders )
    {
        $this->folders = $folders;
        $this->accountId = $account->id;
    }

    /**
     * Load the threads for a folder. Returns two arrays, a starred
     * (or flagged) collection, and non-starred.
     * @param int $folderId
     * @param int $page
     * @param int $limit
     * @return [ array, array, array ] Messages, Messages, ints
     */
    public function getThreads( $folderId, $page = 1, $limit = 50 )
    {
        $flagged = [];
        $unflagged = [];
        $messageModel = new Message;
        $escaper = new Escaper( self::UTF8 );
        $messages = $messageModel->getThreadsByFolder(
            $this->accountId,
            $folderId );
        $messageCounts = $messageModel->getThreadCountsByFolder(
            $this->accountId,
            $folderId );

        foreach ( $messages as $message ) {
            $this->setFolders( $message );
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

        // @TODO slice the flagged/unflagged arrays by page
        $counts = $this->buildCounts( $messageCounts, $page, $limit );

        return [ $flagged, $unflagged, $counts ];
    }

    /**
     * Prepares an HTML-safe snippet to display in the message line.
     * @param Message $message
     * @param Escaper $escaper
     */
    private function setSnippet( Message &$message, Escaper $escaper )
    {
        $text = strip_tags( $message->text_plain );
        $text = trim( $escaper->escapeHtml( $text ) );
        $message->snippet = ltrim( $text, "-_" );
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
        $count = count( $message->names );
        $unique = array_values( array_unique( $message->names ) );
        $uniqueCount = count( $unique );

        if ( $uniqueCount === 1 ) {
            $message->names = trim( $unique[ 0 ], '"' );
        }
        elseif ( $uniqueCount ) {
            $message->names = $this->getNames( $unique, $uniqueCount );
        }
        else {
            $message->names = trim( $message->from );
        }

        if ( $message->thread_count > 1 ) {
            $message->names .= " (". $message->thread_count .")";
        }
    }

    /**
     * Prepare the name strings for the message.
     * @param array $list List of all names on the message
     * @param int $count Number of unique names in the set
     * @return string
     */
    private function getNames( $list, $count )
    {
        $firstName = current( explode( " ", current( $list ) ) );
        $lastName = current( explode( " ", end( $list ) ) );
        $firstName = trim( $firstName, '"' );
        $lastName = trim( $lastName, '"' );

        if ( $count === 2 ) {
            return sprintf( "%s, %s", $firstName, $lastName );
        }

        return sprintf( "%s .. %s", $firstName, $lastName );
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

    /**
     * Prepare the folder labels for the message.
     * @param Message $message
     */
    private function setFolders( Message &$message )
    {

    }

    private function buildCounts( $counts, $page, $limit )
    {
        $start = $page + (($page - 1) * $limit);

        return (object) [
            'flagged' => (object) [
                'start' => $start,
                'total' => $counts->flagged,
                'end' => ( $counts->flagged < $limit )
                    ? $counts->flagged
                    : $limit
            ],
            'unflagged' => (object) [
                'start' => $start,
                'total' => $counts->unflagged,
                'end' => ( $counts->unflagged < $limit )
                    ? $counts->unflagged
                    : $limit
            ]
        ];
    }
}