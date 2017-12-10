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
        $flagged = $this->getThreads( $meta->flaggedIds, $accountId, $limit, $offset );
        $unflagged = $this->getThreads( $meta->unflaggedIds, $accountId, $limit, $offset );
        $messages = array_merge( $flagged, $unflagged );

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

    /**
     * Returns any message with the same message ID and thread ID.
     * @param array $filters
     * @return array of ints
     */
    public function getSiblingIds( $filters = [] )
    {
        $ids = [];
        $addSelf = TRUE;

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
            ->select([ 'id' ])
            ->from( 'messages' )
            ->where( 'deleted', '=', 0 )
            ->where( 'thread_id', '=', $this->thread_id )
            ->where( 'account_id', '=', $this->account_id )
            ->where( 'message_id', '=', $this->message_id );

        foreach ( $filters as $key => $value ) {
            $query->where( $key, '=', $value );
        }

        $results = $query->execute()->fetchAll( PDO::FETCH_CLASS );

        foreach ( $results as $result ) {
            $ids[] = $result->id;
        }

        return array_values( array_unique( $ids ) );
    }

    /**
     * Updates a flag on the message.
     * @param int $messageId
     * @param string $flag
     * @param bool $state
     */
    public function setFlag( $messageId, $flag, $state )
    {
        $updated = $this->db()
            ->update([
                $flag => $state ? 1 : 0
            ])
            ->table( 'messages' )
            ->where( 'id', '=', $messageId )
            ->execute();

        return is_numeric( $updated );
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