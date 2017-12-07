<?php

namespace App\Model;

use PDO;
use App\Model;

class Message extends Model
{
    public $id;
    public $to;
    public $cc;
    public $bcc;
    public $from;
    public $date;
    public $size;
    public $seen;
    public $draft;
    public $synced;
    public $recent;
    public $flagged;
    public $deleted;
    public $subject;
    public $charset;
    public $answered;
    public $reply_to;
    public $date_str;
    public $unique_id;
    public $folder_id;
    public $text_html;
    public $account_id;
    public $message_id;
    public $message_no;
    public $text_plain;
    public $references;
    public $created_at;
    public $in_reply_to;
    public $attachments;
    // Computed properties
    public $thread_count;

    private $unserializedAttachments;

    public function getAttachments()
    {
        if ( ! is_null( $this->unserializedAttachments ) ) {
            return $this->unserializedAttachments;
        }

        $this->unserializedAttachments = @unserialize( $this->attachments );

        return $this->unserializedAttachments;
    }

    public function getThreadCountsByFolder( $accountId, $folderId )
    {
        $counts = (object) [
            'flagged' => 0,
            'unflagged' => 0
        ];
        $results = $this->db()
            ->select([ 'thread_id', 'flagged' ])
            ->from( 'messages' )
            ->where( 'deleted', '=', 0 )
            ->where( 'folder_id', '=', $folderId )
            ->where( 'account_id', '=', $accountId )
            ->groupBy( 'thread_id' )
            ->execute()
            ->fetchAll( PDO::FETCH_CLASS );

        foreach ( $results as $result ) {
            if ( $result->flagged == 1 ) {
                $counts->flagged++;
            }
            else {
                $counts->unflagged++;
            }
        }

        return $counts;
    }

    /**
     * Returns a list of messages by folder and account.
     * @param int $accountId
     * @param int $folderId
     * @param int $limit
     * @param int $offset
     * @return Message array
     */
    public function getThreadsByFolder( $accountId, $folderId, $limit = 50, $offset = 0 )
    {
        $threads = [];
        $threadIds = [];
        $messageIds = [];
        $flagged = $this->getThreads( $accountId, $folderId, TRUE, $limit, $offset );
        $unflagged = $this->getThreads( $accountId, $folderId, FALSE, $limit, $offset );
        $messages = array_merge( $flagged, $unflagged );

        // Count all messages by thread ID and add that as a property
        // on each message
        foreach ( $messages as $message ) {
            $threadIds[] = $message->thread_id;
        }

        $threadMessages = $this->db()
            ->select([
                '`from`', 'thread_id', 'message_id',
                'folder_id', 'subject'
            ])
            ->from( 'messages' )
            ->where( 'deleted', '=', 0 )
            ->whereIn( 'thread_id', $threadIds )
            ->where( 'account_id', '=', $accountId )
            ->orderBy( 'date', Model::ASC )
            ->execute()
            ->fetchAll( PDO::FETCH_CLASS );

        foreach ( $threadMessages as $row ) {
            if ( ! isset( $threads[ $row->thread_id ] ) ) {
                $threads[ $row->thread_id ] = (object) [
                    'count' => 0,
                    'names' => [],
                    'folders' => [],
                    'subject' => $row->subject
                ];
            }

            $threads[ $row->thread_id ]->folders[] = (int) $row->folder_id;
            $threads[ $row->thread_id ]->names[] = $this->getName( $row->from );

            if ( $row->message_id ) {
                if ( isset( $messageIds[ $row->message_id ] ) ) {
                    continue;
                }

                $threads[ $row->thread_id ]->count++;
                $messageIds[ $row->message_id ] = TRUE;
            }
        }

        foreach ( $messages as $message ) {
            $message->names = [];
            $message->folders = [];
            $message->thread_count = 1;

            if ( isset( $threads[ $message->thread_id ] ) ) {
                $found = $threads[ $message->thread_id ];
                $message->names = $found->names;
                $message->subject = $found->subject;
                $message->folders = $found->folders;
                $message->thread_count = $found->count;
            }
        }

        return $messages ?: [];
    }

    private function getThreads( $accountId, $folderId, $flagged, $limit, $offset )
    {
        return $this->db()
            ->select([
                'id', '`to`', 'cc', '`from`', '`date`',
                'seen', 'subject', 'flagged', 'thread_id',
                'substring(text_plain, 1, 260) as text_plain'
            ])
            ->from( 'messages' )
            ->where( 'deleted', '=', 0 )
            ->where( 'folder_id', '=', $folderId )
            ->where( 'account_id', '=', $accountId )
            ->where( 'flagged', '=', $flagged ? 1 : 0 )
            ->groupBy( 'thread_id' )
            ->orderBy( 'date', Model::DESC )
            ->limit( $limit, $offset )
            ->execute()
            ->fetchAll( PDO::FETCH_CLASS, get_class() );
    }

    private function getName( $from )
    {
        $from = trim( $from );
        $pos = strpos( $from, '<' );

        if ( $pos !== FALSE && $pos > 0 ) {
            return trim( substr( $from, 0, $pos ) );
        }

        return trim( $from, '<> ' );
    }
}