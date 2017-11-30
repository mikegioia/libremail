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

    /**
     * Returns a list of messages by folder and account.
     * @param int $accountId
     * @param int $folderId
     * @return Message array
     */
    public function getThreadsByFolder( $accountId, $folderId )
    {
        $threads = [];
        $threadIds = [];
        $messages = $this->db()
            ->select([
                'id', '`to`', 'cc', 'bcc', '`from`', '`date`',
                'seen', 'subject', 'flagged', 'thread_id',
               // 'substring(text_plain, 1, 260) as text_plain'
            ])
            ->from( 'messages' )
            ->where( 'deleted', '=', 0 )
            ->where( 'folder_id', '=', $folderId )
            ->where( 'account_id', '=', $accountId )
            ->groupBy( 'thread_id' )
            ->orderBy( 'date', Model::DESC )
            ->execute()
            ->fetchAll( PDO::FETCH_CLASS, get_class() );

        // Count all messages by thread ID and add that as a property
        // on each message
        foreach ( $messages as $message ) {
            $threadIds[] = $message->thread_id;
        }

        $threadCounts = $this->db()
            ->select([ 'thread_id', 'count(1) as thread_count' ])
            ->from( 'messages' )
            ->where( 'deleted', '=', 0 )
            ->whereIn( 'thread_id', $threadIds )
            ->where( 'account_id', '=', $accountId )
            ->groupBy( 'thread_id' )
            ->execute()
            ->fetchAll();

        foreach ( $threadCounts as $row ) {
            $threads[ $row[ 'thread_id' ] ] = $row[ 'thread_count' ];
        }

        foreach ( $messages as $message ) {
            $message->thread_count = ( isset( $threads[ $message->thread_id ] ) )
                ? $threads[ $message->thread_id ]
                : 1;
        }

        return $messages ?: [];
    }
}