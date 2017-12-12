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
     * @param array $options
     * @return [ array, array, array ] Messages, Messages, ints
     */
    public function getThreads( $folderId, $page = 1, $limit = 25, array $options = [] )
    {
        $flagged = [];
        $unflagged = [];
        $messageModel = new Message;
        $escaper = new Escaper( self::UTF8 );
        $folders = $this->getIndexedFolders();
        $messages = $messageModel->getThreadsByFolder(
            $this->accountId,
            $folderId,
            $limit,
            ($page - 1) * $limit,
            $options );
        $messageCounts = $messageModel->getThreadCountsByFolder(
            $this->accountId,
            $folderId );
        $splitFlagged = isset( $options[ Message::SPLIT_FLAGGED ] )
            && $options[ Message::SPLIT_FLAGGED ] === TRUE;
        usort( $messages, function ($a, $b) {
            return strcmp( $b->date, $a->date );
        });

        foreach ( $messages as $message ) {
            $this->setNameList( $message );
            $this->setDisplayDate( $message );
            $this->setSnippet( $message, $escaper );
            $this->setFolders( $message, $folders );

            if ( $message->flagged == 1 && $splitFlagged ) {
                $flagged[] = $message;
            }
            else {
                $unflagged[] = $message;
            }
        }

        $counts = $this->buildCounts(
            $messageCounts,
            $page,
            $limit,
            $splitFlagged );

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
        $text = $escaper->escapeHtml( trim( $text ) );
        $message->snippet = ltrim( $text, "<>-_=" );
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
            $message->names = $this->getNames(
                $message->names,
                $message->seens,
                $uniqueCount );
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
     * @param array $seens List of seen flags for all names
     * @param int $count Number of unique names in the set
     * @return string
     */
    private function getNames( array $list, array $seens, $count )
    {
        $i = 0;
        $first = array_shift( $list );

        do {
            $last = ( $list ) ? array_pop( $list ) : NULL;

            if ( $last != $first ) {
                break;
            }
        } while ( $list );

        $firstName = current( explode( " ", $first ) );
        $lastName = current( explode( " ", $last ?: [] ) );
        $firstName = trim( $firstName, '"' );
        $lastName = trim( $lastName, '"' );

        // Long names? Try to get email handle.
        if ( strlen( $firstName . $lastName ) > 20 ) {
            $firstName = current( explode( "@", $firstName ) );
            $lastName = current( explode( "@", $lastName ) );
        }

        if ( ! $lastName ) {
            return $firstName;
        }

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
    private function setFolders( Message &$message, array $folders )
    {
        $message->folders = array_intersect_key(
            $folders,
            array_flip( $message->folders ) );
    }

    /**
     * Returns a set of folders indexed by folder ID.
     */
    private function getIndexedFolders()
    {
        $folders = [];

        foreach ( $this->folders->get() as $folder ) {
            if ( ! $folder->is_mailbox && $folder->ignored != 1 ) {
                $folders[ $folder->id ] = $folder;
            }
        }

        return $folders;
    }

    /**
     * Prepare the counts and paging info for the folders.
     * @param array $counts
     * @param int $page
     * @param int $limit
     * @param bool $splitFlagged
     * @return object
     */
    private function buildCounts( $counts, $page, $limit, $splitFlagged )
    {
        $start = 1 + (($page - 1) * $limit);

        return (object) [
            'flagged' => (object) [
                'page' => $page,
                'start' => $start,
                'prevPage' => ( $page > 1 )
                    ? $page - 1
                    : NULL,
                'total' => $counts->flagged,
                'end' => ( $start + $limit - 1 > $counts->flagged )
                    ? $counts->flagged
                    : $start + $limit - 1,
                'totalPages' => ceil( $counts->flagged / $limit ),
                'nextPage' => ( $page >= ceil( $counts->flagged / $limit ) )
                    ? NULL
                    : $page + 1,
            ],
            'unflagged' => (object) [
                'page' => $page,
                'start' => $start,
                'prevPage' => ( $page > 1 )
                    ? $page - 1
                    : NULL,
                'total' => $counts->unflagged,
                'end' => ( $start + $limit - 1 > $counts->unflagged )
                    ? $counts->unflagged
                    : $start + $limit - 1,
                'totalPages' => ceil( $counts->unflagged / $limit ),
                'nextPage' => ( $page >= ceil( $counts->unflagged / $limit ) )
                    ? NULL
                    : $page + 1
            ]
        ];
    }
}