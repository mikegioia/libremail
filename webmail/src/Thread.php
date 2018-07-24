<?php

namespace App;

use App\Model\Account;
use App\Model\Message;
use Zend\Escaper\Escaper;
use App\Exceptions\NotFoundException;

class Thread
{
    private $thread;
    private $folders;
    private $messages;
    private $folderIds;
    private $accountId;
    private $messageCount;
    private $unreadIds = [];
    private $threadFolders = [];

    const UTF8 = 'utf-8';
    const SNIPPET_LENGTH = 160;
    const EMAIL_REGEX = '@(http)?(s)?(://)?(([a-zA-Z])([-\w]+\.)+([^\s\.]+[^\s]*)+[^,.\s])@';

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
        $escaper = new Escaper( self::UTF8 );
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

            if ( $message->seen != 1 ) {
                $unreadIds[] = $message->id;
            }

            $this->setFrom( $message );
            $this->setContent( $message );
            $this->setSnippet( $message, $escaper );
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

        $this->messageCount = count( $messages );
    }

    public function get()
    {
        return $this->message;
    }

    public function getSubject()
    {
        return $this->message->subject;
    }

    public function getMessages()
    {
        return $this->messages;
    }

    public function getFolders()
    {
        return $this->threadFolders;
    }

    public function getMessageCount()
    {
        return $this->messageCount;
    }

    public function isUnread( $id )
    {
        return in_array( $id, $this->unreadIds );
    }

    /**
     * Adds two new properties, from_name and from_email.
     * @param Message $message
     */
    private function setFrom( Message &$message )
    {
        $parts = explode( '<', $message->from, 2 );
        $count = count( $parts );

        if ( $count === 1 ) {
            $message->from_email = '';
            $message->from_name = trim( $parts[ 0 ], ' >' );
        }
        else {
            $message->from_name = trim( $parts[ 0 ] );
            $message->from_email = '<'. trim( $parts[ 1 ], ' <>' ) .'>';
        }
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
     * Parses the text/plain content into escaped HTML.
     * @param Message $message
     * @todo
     */
    private function setContent( Message &$message )
    {
        // @TODO
        //$message->text_markdown = $message->text_plain;

        // Cleanse it
        $body = htmlspecialchars( $message->text_plain, ENT_QUOTES, 'UTF-8' );
        // Convert any links
        $body = preg_replace(
            self::EMAIL_REGEX,
            '<a href="http$2://$4" target="_blank" title="$0">$0</a>',
            $body);

        $message->body = nl2br( trim( $body ) );
    }
}