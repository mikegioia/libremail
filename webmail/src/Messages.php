<?php

namespace App;

use App\View;
use App\Folders;
use App\Model\Account;
use App\Model\Message;
use App\Messages\Names;
use Zend\Escaper\Escaper;

class Messages
{
    private $folders;
    private $accountId;

    const UTF8 = 'utf-8';
    const SNIPPET_LENGTH = 160;

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
        $messageNames = new Names;
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
            $this->setDisplayDate( $message );
            $this->setSnippet( $message, $escaper );
            $this->setFolders( $message, $folders );
            $this->setNameList( $message, $messageNames );

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
        $message->snippet = substr(
            ltrim( $text, "<>-_=" ),
            0,
            self::SNIPPET_LENGTH );
    }

    /**
     * Prepares a list of names of people involved in the message thread
     * to display in the message line.
     * @todo Make this smarter, right now it only shows original from but
     *   gmail shows multiple people on the thread.
     * @param Message $message
     * @param Names $messageNames
     */
    private function setNameList( Message &$message, Names $messageNames )
    {
        $count = count( $message->names );
        $unique = array_values( array_unique( $message->names ) );
        $message->names = $messageNames->get(
            $message->names,
            $message->seens );

        if ( $message->thread_count > 1 ) {
            $message->names .= " (". $message->thread_count .")";
        }
    }

    /**
     * Prepares a human-readable date for the message line.
     * @param Message $message
     */
    private function setDisplayDate( Message &$message )
    {
        $today = View::getDate( NULL, View::DATE_FULL );
        $messageTime = ( $message->date_recv )
            ? strtotime( $message->date_recv )
            : strtotime( $message->date );
        $messageDate = View::getDate( $message->date, View::DATE_FULL );

        if ( $today === $messageDate ) {
            $message->display_date = View::getDate( $message->date, View::TIME );
        }
        else {
            $message->display_date = View::getDate( $message->date, View::DATE_SHORT );
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