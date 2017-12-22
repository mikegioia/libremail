<?php

namespace App\Model;

use PDO;
use Exception;
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
    public $recv_str;
    public $date_recv;
    public $unique_id;
    public $folder_id;
    public $thread_id;
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
    // Flags
    const FLAG_SEEN = 'seen';
    const FLAG_FLAGGED = 'flagged';
    const FLAG_DELETED = 'deleted';
    // Options
    const ALL_SIBLINGS = 'all_siblings';
    const ONLY_FLAGGED = 'only_flagged';
    const SPLIT_FLAGGED = 'split_flagged';
    // Default options
    const DEFAULTS = [
        'all_siblings' => FALSE,
        'only_flagged' => FALSE,
        'split_flagged' => FALSE
    ];

    public function getData()
    {
        return [
            'id' => $this->id,
            'to' => $this->to,
            'cc' => $this->cc,
            'bcc' => $this->bcc,
            'from' => $this->from,
            'date' => $this->date,
            'size' => $this->size,
            'seen' => $this->seen,
            'draft' => $this->draft,
            'synced' => $this->synced,
            'recent' => $this->recent,
            'flagged' => $this->flagged,
            'deleted' => $this->deleted,
            'subject' => $this->subject,
            'charset' => $this->charset,
            'answered' => $this->answered,
            'reply_to' => $this->reply_to,
            'date_str' => $this->date_str,
            'recv_str' => $this->recv_str,
            'date_recv' => $this->date_recv,
            'unique_id' => $this->unique_id,
            'folder_id' => $this->folder_id,
            'thread_id' => $this->thread_id,
            'text_html' => $this->text_html,
            'account_id' => $this->account_id,
            'message_id' => $this->message_id,
            'message_no' => $this->message_no,
            'text_plain' => $this->text_plain,
            'references' => $this->references,
            'created_at' => $this->created_at,
            'in_reply_to' => $this->in_reply_to,
            'attachments' => $this->attachments
        ];
    }

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
     * Returns the data for an entire thread.
     */
    public function getThread( $accountId, $threadId )
    {
        return $this->db()
            ->select()
            ->from( 'messages' )
            ->where( 'deleted', '=', 0 )
            ->where( 'thread_id', '=', $threadId )
            ->where( 'account_id', '=', $accountId )
            ->orderBy( 'date', Model::ASC )
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

        if ( ! $threadIds ) {
            return $counts;
        }

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
     * @param array $options
     * @return Message array
     */
    public function getThreadsByFolder( $accountId, $folderId, $limit = 50, $offset = 0, $options = [] )
    {
        $threads = [];
        $messageIds = [];
        $meta = $this->getThreadCountsByFolder( $accountId, $folderId );
        $threadIds = array_merge( $meta->flaggedIds, $meta->unflaggedIds );
        $options = array_merge( self::DEFAULTS, $options );

        if ( ! $threadIds ) {
            return [];
        }

        if ( $options[ self::SPLIT_FLAGGED ] === TRUE ) {
            $flagged = $this->getThreads( $meta->flaggedIds, $accountId, $limit, $offset );
            $unflagged = $this->getThreads( $meta->unflaggedIds, $accountId, $limit, $offset );
            $messages = array_merge( $flagged, $unflagged );
        }
        elseif ( $options[ self::ONLY_FLAGGED ] === TRUE ) {
            $messages = $this->getThreads( $meta->flaggedIds, $accountId, $limit, $offset );
        }
        else {
            $messages = $this->getThreads( $threadIds, $accountId, $limit, $offset );
        }

        // Load all messages in these threads. We need to get the names
        // of anyone involved in the thread, any folders, and the subject
        // from the first message in the thread, and the date from the
        // last message in the thread.
        $threadMessages = $this->db()
            ->select([
                '`from`', 'thread_id', 'message_id',
                'folder_id', 'subject', '`date`',
                'seen', 'date_recv'
            ])
            ->from( 'messages' )
            ->where( 'deleted', '=', 0 )
            ->whereIn( 'thread_id', $threadIds )
            ->where( 'account_id', '=', $accountId )
            ->orderBy( '`date`', Model::ASC )
            ->execute()
            ->fetchAll( PDO::FETCH_CLASS );

        foreach ( $threadMessages as $row ) {
            if ( ! isset( $threads[ $row->thread_id ] ) ) {
                $threads[ $row->thread_id ] = (object) [
                    'count' => 0,
                    'names' => [],
                    'seens' => [],
                    'folders' => [],
                    'unseen' => FALSE,
                    'subject' => $row->subject
                ];
            }

            $threads[ $row->thread_id ]->folders[ $row->folder_id ] = TRUE;

            if ( $row->message_id ) {
                if ( isset( $messageIds[ $row->message_id ] ) ) {
                    continue;
                }

                if ( $row->seen != 1 ) {
                    $threads[ $row->thread_id ]->unseen = TRUE;
                }

                $name = $this->getName( $row->from );
                $threads[ $row->thread_id ]->count++;
                $threads[ $row->thread_id ]->names[] = $name;
                $threads[ $row->thread_id ]->seens[] = $row->seen;
                $threads[ $row->thread_id ]->date = $row->date_recv ?: $row->date;
                $messageIds[ $row->message_id ] = TRUE;
            }
        }

        foreach ( $messages as $message ) {
            $message->names = [];
            $message->seens = [];
            $message->folders = [];
            $message->thread_count = 1;

            if ( isset( $threads[ $message->thread_id ] ) ) {
                $found = $threads[ $message->thread_id ];
                $message->date = $found->date;
                $message->names = $found->names;
                $message->seens = $found->seens;
                $message->subject = $found->subject;
                $message->thread_count = $found->count;
                $message->folders = array_keys( $found->folders );

                if ( $found->unseen ) {
                    $message->seen = 0;
                }
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
                'text_plain', 'charset'
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

    public function getUnreadCounts( $accountId )
    {
        $indexed = [];
        $unseenThreadIds = (new Message)->getUnseenThreads( $accountId );

        if ( $unseenThreadIds ) {
            $folderThreads = $this->db()
                ->select([ 'folder_id', 'thread_id' ])
                ->from( 'messages' )
                ->whereIn( 'thread_id', $unseenThreadIds )
                ->groupBy( 'folder_id, thread_id' )
                ->execute()
                ->fetchAll( PDO::FETCH_CLASS );

            foreach ( $folderThreads as $thread ) {
                if ( ! isset( $indexed[ $thread->folder_id ] ) ) {
                    $indexed[ $thread->folder_id ] = 0;
                }

                $indexed[ $thread->folder_id ] += 1;
            }
        }

        return $indexed;
    }

    public function getUnseenThreads( $accountId )
    {
        $threads = [];
        $threadIds = $this->db()
            ->select([ 'thread_id' ])
            ->from( 'messages' )
            ->where( 'seen', '=', 0 )
            ->where( 'deleted', '=', 0 )
            ->where( 'account_id', '=', $accountId )
            ->execute()
            ->fetchAll( PDO::FETCH_CLASS );

        if ( ! $threadIds ) {
            return [];
        }

        foreach ( $threadIds as $row ) {
            $threads[] = $row->thread_id;
        }

        return $threads;
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

    /**
     * Returns any message with the same message ID and thread ID.
     * @param array $filters
     * @param array $options
     * @return array of Messages
     */
    public function getSiblings( $filters = [], $options = [] )
    {
        $ids = [];
        $addSelf = TRUE;
        $options = array_merge( self::DEFAULTS, $options );

        // If there are any filters, first check this message
        foreach ( $filters as $key => $value ) {
            if ( $this->$key != $value ) {
                $addSelf = FALSE;
                break;
            }
        }

        if ( $addSelf ) {
            $ids[] = $this->id;
        }

        if ( ! $this->message_id ) {
            return $ids;
        }

        $query = $this->db()
            ->select()
            ->from( 'messages' )
            ->where( 'deleted', '=', 0 )
            ->where( 'thread_id', '=', $this->thread_id )
            ->where( 'account_id', '=', $this->account_id );

        if ( ! $options[ self::ALL_SIBLINGS ] ) {
            $query->where( 'message_id', '=', $this->message_id );
        }

        foreach ( $filters as $key => $value ) {
            $query->where( $key, '=', $value );
        }

        return $query->execute()->fetchAll( PDO::FETCH_CLASS, get_class() );
    }

    /**
     * Updates a flag on the message.
     * @param int $messageId
     * @param string $flag
     * @param bool $state
     */
    public function setFlag( $flag, $state )
    {
        $updated = $this->db()
            ->update([
                $flag => $state ? 1 : 0
            ])
            ->table( 'messages' )
            ->where( 'id', '=', $this->id )
            ->execute();

        return is_numeric( $updated ) ? $updated : FALSE;
    }

    /**
     * Create a new message in the specified folder.
     * @param int $folderId
     * @return Message
     * @throws Exception
     */
    public function copyTo( $folderId )
    {
        // If this message exists in the folder and is not deleted,
        // then skip the operation.
        $existingMessage = $this->db()
            ->select([ 'id' ])
            ->from( 'messages' )
            ->where( 'deleted', '=', 0 )
            ->where( 'folder_id', '=', $folderId )
            ->where( 'thread_id', '=', $this->thread_id )
            ->where( 'message_id', '=', $this->message_id )
            ->where( 'account_id', '=', $this->account_id )
            ->execute()
            ->fetchObject();

        if ( $existingMessage && $existingMessage->id ) {
            return TRUE;
        }

        $data = $this->getData();
        unset( $data[ 'id' ] );
        $data[ 'unique_id' ] = NULL;
        $data[ 'message_no' ] = NULL;
        $data[ 'folder_id' ] = $folderId;

        $newMessageId = $this->db()
            ->insert( array_keys( $data ) )
            ->into( 'messages' )
            ->values( array_values( $data ) )
            ->execute();

        if ( ! $newMessageId ) {
            throw new Exception(
                "Failed copying message {$this->id} to Folder #{$folderId}" );
        }

        $data[ 'id' ] = $newMessageId;

        return new static( $data );
    }
}