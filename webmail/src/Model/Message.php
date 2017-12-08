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
    // Cache for threading info
    private $threadCache = [];
    // Cache for attachments
    private $unserializedAttachments;

    public function getAttachments()
    {
        if ( ! is_null( $this->unserializedAttachments ) ) {
            return $this->unserializedAttachments;
        }

        $this->unserializedAttachments = @unserialize( $this->attachments );

        return $this->unserializedAttachments;
    }

    public function getByIds( array $ids )
    {
        return $this->db()
            ->select()
            ->from( 'messages' )
            ->whereIn( 'id', $ids )
            ->execute()
            ->fetchAll( PDO::FETCH_CLASS, get_class() );
    }

    /**
     * Returns two counts of messages, by flagged and un-flagged.
     * @param int $accountId
     * @param int $folderId
     * @return object
     */
    public function getThreadCountsByFolder( $accountId, $folderId )
    {
        if ( $this->threadCache( $accountId, $folderId ) ) {
            return $this->threadCache( $accountId, $folderId );
        }

        $counts = (object) [
            'flagged' => 0,
            'unflagged' => 0,
            'flaggedIds' => [],
            'unflaggedIds' => []
        ];

        // First get all of the thread IDs for this folder
        $threadIds = $this->getThreadIdsByFolder( $accountId, $folderId );

        // Now fetch all messages in any of these threads. Any message
        // in the thread could be starred.
        $results = $this->db()
            ->select([ 'thread_id', 'sum(flagged) as flagged_count' ])
            ->from( 'messages' )
            ->where( 'deleted', '=', 0 )
            ->where( 'account_id', '=', $accountId )
            ->whereIn( 'thread_id', $threadIds )
            ->groupBy( 'thread_id' )
            ->execute()
            ->fetchAll( PDO::FETCH_CLASS );

        foreach ( $results as $result ) {
            if ( $result->flagged_count > 0 ) {
                $counts->flagged++;
                $counts->flaggedIds[] = $result->thread_id;
            }
            else {
                $counts->unflagged++;
                $counts->unflaggedIds[] = $result->thread_id;
            }
        }

        $this->setThreadCache( $accountId, $folderId, $counts );

        return $counts;
    }

    /**
     * Load the thread IDs for a given folder.
     * @param int $accountId
     * @param int $folderId
     * @return array
     */
    private function getThreadIdsByFolder( $accountId, $folderId )
    {
        $threadIds = [];
        $results = $this->db()
            ->select([ 'thread_id' ])
            ->distinct()
            ->from( 'messages' )
            ->where( 'deleted', '=', 0 )
            ->where( 'folder_id', '=', $folderId )
            ->where( 'account_id', '=', $accountId )
            ->execute()
            ->fetchAll( PDO::FETCH_CLASS );

        foreach ( $results as $result ) {
            $threadIds[] = $result->thread_id;
        }

        return $threadIds;
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
        $messageIds = [];
        $meta = $this->getThreadCountsByFolder( $accountId, $folderId );
        $threadIds = array_merge( $meta->flaggedIds, $meta->unflaggedIds );
        $messages = $this->getThreads( $threadIds, $accountId, $limit, $offset );

        // Load all messages in these threads. We need to get the names
        // of anyone involved in the thread, any folders, and the subject
        // from the first message in the thread.
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

            if ( in_array( $message->thread_id, $meta->flaggedIds ) ) {
                $message->flagged = 1;
            }
        }

        return $messages ?: [];
    }

    /**
     * Load the messages for threading.
     * @param array $threadIds
     * @param int $accountId
     * @param int $limit
     * @param int $offset
     * @return array Messages
     */
    private function getThreads( array $threadIds, $accountId, $limit, $offset )
    {
        if ( ! count( $threadIds) ) {
            return [];
        }

        return $this->db()
            ->select([
                'id', '`to`', 'cc', '`from`', '`date`',
                'seen', 'subject', 'flagged', 'thread_id',
                'substring(text_plain, 1, 260) as text_plain'
            ])
            ->from( 'messages' )
            ->where( 'deleted', '=', 0 )
            ->where( 'account_id', '=', $accountId )
            ->whereIn( 'thread_id', $threadIds )
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

    /**
     * Store a computed thread count object in the cache.
     * @param int $accountId
     * @param int $folderId
     * @param object $counts
     */
    private function setThreadCache( $accountId, $folderId, $counts )
    {
        $this->threadsCache[ $accountId .':'. $folderId ] = $counts;
    }

    /**
     * Checks the cache and returns (if set) the counts object.
     * @param int $accountId
     * @param int $folderId
     * @return bool | object
     */
    private function threadCache( $accountId, $folderId )
    {
        $key = $accountId .':'. $folderId;

        if ( ! isset( $this->threadCache[ $key ] ) ) {
            return FALSE;
        }

        return $this->threadCache[ $key ];
    }
}