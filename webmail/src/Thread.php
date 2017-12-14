<?php

namespace App;

use App\Model\Account;
use App\Model\Message;
use App\Exceptions\NotFoundException;

class Thread
{
    private $thread;
    private $folders;
    private $messages;
    private $folderIds;
    private $accountId;
    private $threadFolders = [];

    /**
     * @param Account $account
     * @param Folders $folders
     * @param int $threadId
     * @param bool $load If true, loads the thread data from SQL. This
     *   will throw an exception if no thread is found.
     */
    public function __construct( Account $account, Folders $folders, $threadId, $load = TRUE )
    {
        $this->folders = $folders;
        $this->threadId = $threadId;
        $this->accountId = $account->id;

        if ( $load ) {
            $this->load();
        }
    }

    /**
     * Load the thread data
     * @throws NotFoundException
     */
    public function load()
    {
        $folders = [];
        $messages = [];
        $messageIds = [];
        $allMessages = (new Message)->getThread(
            $this->accountId,
            $this->threadId );

        if ( ! $allMessages ) {
            throw new NotFoundException;
        }

        // Remove all duplicate messages (message-id)
        foreach ( $allMessages as $message ) {
            if ( in_array( $message->message_id, $messageIds ) ) {
                $folders[] = $message->folder_id;
                continue;
            }

            $messages[] = $message;
            $folders[] = $message->folder_id;
            $messageIds[] = $message->message_id;
        }

        $this->messages = $messages;
        $this->message = reset( $messages );
        $folders = array_unique( $folders );

        foreach ( $this->folders->get() as $folder ) {
            if ( in_array( $folder->id, $folders )
                && ! $folder->is_mailbox )
            {
                $this->threadFolders[] = $folder;
            }
        }
    }

    public function get()
    {
        return $this->message;
    }

    public function getDate()
    {
        return $this->message->date;
    }

    public function getSubject()
    {
        return $this->message->subject;
    }

    public function getFolders()
    {
        return $this->threadFolders;
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
}