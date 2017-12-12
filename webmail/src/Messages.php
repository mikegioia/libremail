<?php

namespace App;

use App\View;
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
        $snippet = "";
        $separator = "\r\n";
        $text = trim( strip_tags( $message->text_plain ) );
        $line = strtok( $text, $separator );

        while ( $line !== FALSE ) {
            if ( strncmp( '>', $line, 1 ) !== 0 ) {
                $snippet .= $line;
            }

            $line = strtok( $separator );
        }

        $snippet = $escaper->escapeHtml( $snippet );
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
        $message->names = $this->getNames(
            $message->names,
            $message->seens );

        if ( $message->thread_count > 1 ) {
            $message->names .= " (". $message->thread_count .")";
        }
    }

    /**
     * Prepare the name strings for the message.
     * @param array $names List of all names on the message
     * @param array $seens List of seen flags for all names
     * @param int $count Number of unique names in the set
     * @return string
     */
    private function getNames( array $names, array $seens )
    {
        $view = new View;
        $i = count( $names );
        $getRow = function ( $name, $seen, $index ) {
            $short = trim( current( explode( " ", $name ) ), ' "' );

            return (object) [
                'name' => $name,
                'index' => $index,
                'seen' => $seen == 1,
                'short' => current( explode( "@", $short ) )
            ];
        };
        // Final set will have at most three, and at least two
        $final = [
            1 => $getRow( array_shift( $names ), array_shift( $seens ), 1 ),
            2 => NULL,
            3 => $getRow( array_pop( $names ), array_pop( $seens ), $i )
        ];

        if ( $i === 1 ) {
            return sprintf(
                '<%s>%s</%s>',
                $final[ 1 ]->seen ? 'span' : 'strong',
                $view->clean( $final[ 1 ]->short, TRUE ),
                $final[ 1 ]->seen ? 'span' : 'strong' );
        }

        while ( $names ) {
            $i--;
            $lastName = array_pop( $names );
            $lastSeen = array_pop( $seens );

            // Don't show author twice, even if author is most recent,
            // but only if the final message has been seen
            if ( $final[ 3 ]->seen
                && $final[ 3 ]->name == $final[ 1 ]->name )
            {
                $final[ 3 ] = $getRow( $lastName, $lastSeen, $i );
            }
            elseif ( $lastSeen != 1 ) {
                if ( $final[ 2 ]
                    && $final[ 3 ]->seen && ! $final[ 2 ]->seen )
                {
                    $final[ 3 ] = $final[ 2 ];
                }

                $final[ 2 ] = $getRow( $lastName, $lastSeen, $i );
            }
        }

        // Clean up instances of the same name adjacent to itself
        if ( $final[ 2 ] && $final[ 1 ]->name == $final[ 2 ]->name ) {
            if ( $final[ 1 ]->seen && $final[ 2 ]->seen ) {
                unset( $final[ 2 ] );
            }
            elseif ( ! $final[ 1 ]->seen && $final[ 2 ]->seen ) {
                $final[ 1 ] = $final[ 2 ];
                unset( $final[ 2 ] );
            }
        }

        if ( ! $final[ 2 ] && $final[ 1 ]->name == $final[ 3 ]->name ) {
            if ( $final[ 1 ]->seen && $final[ 3 ]->seen ) {
                unset( $final[ 3 ] );
            }
            elseif ( ! $final[ 1 ]->seen && $final[ 3 ]->seen ) {
                $final[ 1 ] = $final[ 3 ];
                unset( $final[ 3 ] );
            }
        }

        $i = 0;
        $raw = '';
        $return = '';
        $final = array_filter( $final );

        // Finally, we need to combine the names in the most
        // space efficient way possible
        if ( count( $final ) === 1 ) {
            return sprintf(
                '<%s>%s</%s>',
                $final[ 1 ]->seen ? 'span' : 'strong',
                $view->clean( $final[ 1 ]->short, TRUE ),
                $final[ 1 ]->seen ? 'span' : 'strong' );
        }

        foreach ( $final as $item ) {
            $raw .= $item->short;

            if ( ! $return ) {
                $i = $item->index;
                $return = sprintf(
                    '<%s>%s</%s>',
                    $item->seen ? 'span' : 'strong',
                    $view->clean( $item->short, TRUE ),
                    $item->seen ? 'span' : 'strong' );
                continue;
            }

            if ( strlen( $raw . $item->short ) > 20 ) {
                if ( $item->index - $i > 1 ) {
                    return $return .'&nbsp;..';
                }

                return $return;
            }

            $return .= ( $item->index - $i > 1 )
                ? ' .. '
                : ', ';
            $return .= sprintf(
                '<%s>%s</%s>',
                $item->seen ? 'span' : 'strong',
                $view->clean( $item->short, TRUE ),
                $item->seen ? 'span' : 'strong' );
        }

        return $return;
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
                'prevPage' => ( $page > 1 )
                    ? $page - 1
                    : NULL,
                'total' => $counts->flagged,
                'start' => ( $counts->flagged )
                    ? $start
                    : 0,
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
                'prevPage' => ( $page > 1 )
                    ? $page - 1
                    : NULL,
                'total' => $counts->unflagged,
                'start' => ( $counts->unflagged )
                    ? $start
                    : 0,
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