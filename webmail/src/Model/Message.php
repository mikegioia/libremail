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
                'id', '`to`', 'cc', '`from`', '`date`',
                'seen', 'subject', 'flagged', 'thread_id',
                'substring(text_plain, 1, 260) as text_plain'
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

        $messageIds = [];
        $threadMessages = $this->db()
            ->select([
                '`from`', 'thread_id', 'message_id', 'subject'
            ])
            ->from( 'messages' )
            ->where( 'deleted', '=', 0 )
            ->whereIn( 'thread_id', $threadIds )
            ->where( 'account_id', '=', $accountId )
            ->orderBy( 'date', Model::ASC )
            ->execute()
            ->fetchAll( PDO::FETCH_CLASS );

        foreach ( $threadMessages as $row ) {
            if ( $row->message_id ) {
                if ( isset( $messageIds[ $row->message_id ] ) ) {
                    continue;
                }

                $messageIds[ $row->message_id ] = TRUE;
            }

            if ( ! isset( $threads[ $row->thread_id ] ) ) {
                $threads[ $row->thread_id ] = (object) [
                    'count' => 0,
                    'names' => [],
                    'subject' => $row->subject
                ];
            }

            $threads[ $row->thread_id ]->count++;
            $threads[ $row->thread_id ]->names[] = $this->getName( $row->from );
        }

        foreach ( $messages as $message ) {
            $message->names = [];
            $message->thread_count = 1;

            if ( isset( $threads[ $message->thread_id ] ) ) {
                $found = $threads[ $message->thread_id ];
                $message->names = $found->names;
                $message->subject = $found->subject;
                $message->thread_count = $found->count;
            }
        }

        return $messages ?: [];
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