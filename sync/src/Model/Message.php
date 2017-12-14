<?php

namespace App\Model;

use Fn;
use PDO;
use DateTime;
use App\Model;
use Belt\Belt;
use PDOException;
use ForceUTF8\Encoding;
use Particle\Validator\Validator;
use Pb\Imap\Message as ImapMessage;
use App\Traits\Model as ModelTrait;
use App\Exceptions\Validation as ValidationException;
use App\Exceptions\DatabaseUpdate as DatabaseUpdateException;
use App\Exceptions\DatabaseInsert as DatabaseInsertException;

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
    public $unique_id;
    public $folder_id;
    public $thread_id;
    public $text_html;
    public $date_recv;
    public $account_id;
    public $message_id;
    public $message_no;
    public $text_plain;
    public $references;
    public $created_at;
    public $in_reply_to;
    public $attachments;

    private $unserializedAttachments;

    // Options
    const OPT_SKIP_CONTENT = 'skip_content';
    const OPT_TRUNCATE_FIELDS = 'truncate_fields';

    // Flags
    const FLAG_SEEN = 'seen';
    const FLAG_FLAGGED = 'flagged';
    const FLAG_DELETED = 'deleted';

    use ModelTrait;

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
            'unique_id' => $this->unique_id,
            'folder_id' => $this->folder_id,
            'thread_id' => $this->thread_id,
            'text_html' => $this->text_html,
            'date_recv' => $this->date_recv,
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

    public function getFolderId()
    {
        return (int) $this->folder_id;
    }

    public function getUniqueId()
    {
        return (int) $this->unique_id;
    }

    public function getAccountId()
    {
        return (int) $this->account_id;
    }

    public function isSynced()
    {
        return Fn\intEq( $this->synced, 1 );
    }

    public function isDeleted()
    {
        return Fn\intEq( $this->deleted, 1 );
    }

    public function getAttachments()
    {
        if ( ! is_null( $this->unserializedAttachments ) ) {
            return $this->unserializedAttachments;
        }

        $this->unserializedAttachments = @unserialize( $this->attachments );

        return $this->unserializedAttachments;
    }

    public function getById( $id )
    {
        return $this->db()
            ->select()
            ->from( 'messages' )
            ->where( 'id', '=', $id )
            ->execute()
            ->fetch( PDO::FETCH_OBJ );
    }

    public function getByIds( $ids )
    {
        return $this->db()
            ->select()
            ->from( 'messages' )
            ->whereIn( 'id', $ids )
            ->execute()
            ->fetchAll( PDO::FETCH_CLASS, $this->getClass() );
    }

    public function getSubjectHash()
    {
        return self::makeSubjectHash( $this->subject );
    }

    /**
     * Fetches a range of messages for an account. Used during the
     * threading computation.
     * @param int $accountId
     * @param int $minId
     * @param int $maxId
     * @param int $limit
     * @return array Messages
     */
    public function getRangeForThreading( $accountId, $minId, $maxId, $limit )
    {
        return $this->db()
            ->select([
                'id', 'thread_id', 'message_id', '`date`',
                'in_reply_to', '`references`', 'subject'
            ])
            ->from( 'messages' )
            ->where( 'id', '>=', $minId )
            ->where( 'id', '<=', $maxId )
            ->where( 'account_id', '=', $accountId )
            ->limit( $limit )
            ->execute()
            ->fetchAll( PDO::FETCH_CLASS, $this->getClass() );
    }

    /**
     * Finds the highest ID for an account.
     * @param int $accountId
     * @return int
     */
    public function getMaxMessageId( $accountId )
    {
        $row = $this->db()
            ->select([ 'max(id) as max' ])
            ->from( 'messages' )
            ->where( 'account_id', '=', $accountId )
            ->execute()
            ->fetch();

        return $row ? $row[ 'max' ] : 0;
    }

    /**
     * Finds the lowest ID for an account.
     * @param int $accountId
     * @return int
     */
    public function getMinMessageId( $accountId )
    {
        $row = $this->db()
            ->select([ 'min(id) as min' ])
            ->from( 'messages' )
            ->where( 'account_id', '=', $accountId )
            ->execute()
            ->fetch();

        return $row ? $row[ 'min' ] : 0;
    }

    /**
     * Returns a list of integer unique IDs given an account ID
     * and a folder ID to search. This fetches IDs in pages to
     * not exceed any memory limits on the query response.
     * @param int $accountId
     * @param int $folderId
     * @return array of ints
     */
    public function getSyncedIdsByFolder( $accountId, $folderId )
    {
        $ids = [];
        $limit = 10000;
        $count = $this->countSyncedIdsByFolder( $accountId, $folderId );

        for ( $offset = 0; $offset < $count; $offset += $limit ) {
            $ids = array_merge(
                $ids,
                $this->getPagedSyncedIdsByFolder(
                    $accountId,
                    $folderId,
                    $offset,
                    $limit
                ));
        }

        return $ids;
    }

    /**
     * Returns a count of unique IDs for an account.
     * @param int $accountId
     * @return int
     */
    public function countByAccount( $accountId )
    {
        $this->requireInt( $accountId, "Account ID" );
        $messages = $this->db()
            ->select()
            ->clear()
            ->count( 1, 'count' )
            ->from( 'messages' )
            ->where( 'account_id', '=', $accountId )
            ->execute()
            ->fetch();

        return ( $messages ) ? $messages[ 'count' ] : 0;
    }

    /**
     * Returns a count of unique IDs for a folder.
     * @param int $accountId
     * @param int $folderId
     * @return int
     */
    public function countSyncedIdsByFolder( $accountId, $folderId )
    {
        $this->requireInt( $folderId, "Folder ID" );
        $this->requireInt( $accountId, "Account ID" );
        $messages = $this->db()
            ->select()
            ->clear()
            ->count( 1, 'count' )
            ->from( 'messages' )
            ->where( 'synced', '=', 1 )
            ->where( 'deleted', '=', 0 )
            ->where( 'folder_id', '=', $folderId )
            ->where( 'account_id', '=', $accountId )
            ->execute()
            ->fetch();

        return ( $messages ) ? $messages[ 'count' ] : 0;
    }

    /**
     * This method is called by getSyncedIdsByFolder to return a
     * page of results.
     * @param int $accountId
     * @param int $folderId
     * @param int $offset
     * @param int $limit
     * @return array of ints
     */
    private function getPagedSyncedIdsByFolder( $accountId, $folderId, $offset = 0, $limit = 100 )
    {
        $ids = [];
        $this->requireInt( $folderId, "Folder ID" );
        $this->requireInt( $accountId, "Account ID" );
        $messages = $this->db()
            ->select([ 'unique_id' ])
            ->from( 'messages' )
            ->where( 'synced', '=', 1 )
            ->where( 'deleted', '=', 0 )
            ->where( 'folder_id', '=', $folderId )
            ->where( 'account_id', '=', $accountId )
            ->limit( $limit, $offset )
            ->execute()
            ->fetchAll();

        if ( ! $messages ) {
            return $ids;
        }

        foreach ( $messages as $message ) {
            $ids[] = $message[ 'unique_id' ];
        }

        return $ids;
    }

    /**
     * Create or updates a message record.
     * @param array $data
     * @throws ValidationException
     * @throws DatabaseUpdateException
     * @throws DatabaseInsertException
     */
    public function save( $data = [] )
    {
        $val = new Validator;
        $val->required( 'folder_id', 'Folder ID' )->integer();
        $val->required( 'unique_id', 'Unique ID' )->integer();
        $val->required( 'account_id', 'Account ID' )->integer();
        // Optional fields
        $val->required( 'size', 'Size' )->integer();
        $val->required( 'message_no', 'Message Number' )->integer();
        $val->optional( 'date', 'Date' )->datetime( DATE_DATABASE );
        $val->optional( 'subject', 'Subject' )->lengthBetween( 0, 270 );
        $val->optional( 'charset', 'Charset' )->lengthBetween( 0, 100 );
        $val->optional( 'date_str', 'RFC Date' )->lengthBetween( 0, 100 );
        $val->optional( 'seen', 'Seen' )->callback([ $this, 'isValidFlag' ]);
        $val->optional( 'message_id', 'Message ID' )->lengthBetween( 0, 250 );
        $val->optional( 'draft', 'Draft' )->callback([ $this, 'isValidFlag' ]);
        $val->optional( 'recv_str', 'Received Date' )->lengthBetween( 0, 250 );
        $val->optional( 'in_reply_to', 'In-Reply-To' )->lengthBetween( 0, 250 );
        $val->optional( 'recent', 'Recent' )->callback([ $this, 'isValidFlag' ]);
        $val->optional( 'date_recv', 'Date Received' )->datetime( DATE_DATABASE );
        $val->optional( 'flagged', 'Flagged' )->callback([ $this, 'isValidFlag' ]);
        $val->optional( 'deleted', 'Deleted' )->callback([ $this, 'isValidFlag' ]);
        $val->optional( 'answered', 'Answered' )->callback([ $this, 'isValidFlag' ]);

        $this->setData( $data );
        $data = $this->getData();

        if ( ! $val->validate( $data ) ) {
            throw new ValidationException(
                $this->getErrorString(
                    $val,
                    "This message is missing required data."
                ));
        }

        // Update flags to have the right data type
        $this->updateFlagValues( $data, [
            'seen', 'draft', 'recent', 'flagged',
            'deleted', 'answered'
        ]);
        $this->updateUtf8Values( $data, [
            'subject', 'text_html', 'text_plain'
        ]);

        // Check if this message exists
        $exists = $this->db()
            ->select()
            ->from( 'messages' )
            ->where( 'folder_id', '=', $this->folder_id )
            ->where( 'unique_id', '=', $this->unique_id )
            ->where( 'account_id', '=', $this->account_id )
            ->execute()
            ->fetchObject();
        $updateMessage = function ( $db, $id, $data ) {
            return $db
                ->update( $data )
                ->table( 'messages' )
                ->where( 'id', '=', $id )
                ->execute();
        };
        $insertMessage = function ( $db, $data ) {
            return $db
                ->insert( array_keys( $data ) )
                ->into( 'messages' )
                ->values( array_values( $data ) )
                ->execute();
        };

        if ( $exists ) {
            $this->id = $exists->id;
            unset( $data[ 'id' ] );
            unset( $data[ 'created_at' ] );

            try {
                $updated = $updateMessage( $this->db(), $this->id, $data );
            }
            catch ( PDOException $e ) {
                // Check for bad UTF-8 errors
                if ( strpos( $e->getMessage(), "Incorrect string value:" ) ) {
                    $data[ 'subject' ] = Encoding::fixUTF8( $data[ 'subject' ] );
                    $data[ 'text_html' ] = Encoding::fixUTF8( $data[ 'text_html' ] );
                    $data[ 'text_plain' ] = Encoding::fixUTF8( $data[ 'text_plain' ] );
                    $newMessageId = $updateMessage( $this->db(), $data );
                }
                else {
                    throw $e;
                }
            }

            if ( ! Belt::isNumber( $updated ) ) {
                throw new DatabaseUpdateException(
                    MESSAGE,
                    $this->db()->getError() );
            }

            return;
        }

        $createdAt = new DateTime;
        unset( $data[ 'id' ] );
        $data[ 'created_at' ] = $createdAt->format( DATE_DATABASE );

        try {
            $newMessageId = $insertMessage( $this->db(), $data );
        }
        catch ( PDOException $e ) {
            // Check for bad UTF-8 errors
            if ( strpos( $e->getMessage(), "Incorrect string value:" ) ) {
                $data[ 'subject' ] = Encoding::fixUTF8( $data[ 'subject' ] );
                $data[ 'text_html' ] = Encoding::fixUTF8( $data[ 'text_html' ] );
                $data[ 'text_plain' ] = Encoding::fixUTF8( $data[ 'text_plain' ] );
                $newMessageId = $insertMessage( $this->db(), $data );
            }
            else {
                throw $e;
            }
        }

        if ( ! $newMessageId ) {
            throw new DatabaseInsertException(
                MESSAGE,
                $this->getError() );
        }

        $this->id = $newMessageId;
    }

    /**
     * Saves the meta information and content for a message as data
     * on the class object.
     * @param array $meta
     * @param array $options
     */
    public function setMessageData( ImapMessage $message, array $options = [] )
    {
        if ( Fn\get( $options, self::OPT_TRUNCATE_FIELDS ) === TRUE ) {
            $message->subject = substr( $message->subject, 0, 270 );
        }

        if ( Fn\get( $options, self::OPT_SKIP_CONTENT ) === TRUE ) {
            $message->textHtml = NULL;
            $message->textPlain = NULL;
        }

        $this->setData([
            'size' => $message->size,
            'date' => $message->date,
            'to' => $message->toString,
            'unique_id' => $message->uid,
            'from' => $message->fromString,
            'subject' => $message->subject,
            'charset' => $message->charset,
            'seen' => $message->flags->seen,
            'text_html' => $message->textHtml,
            'draft' => $message->flags->draft,
            'date_str' => $message->dateString,
            'text_plain' => $message->textPlain,
            'message_id' => $message->messageId,
            'recent' => $message->flags->recent,
            'message_no' => $message->messageNum,
            'references' => $message->references,
            'in_reply_to' => $message->inReplyTo,
            'date_recv' => $message->dateReceived,
            'flagged' => $message->flags->flagged,
            'deleted' => $message->flags->deleted,
            'recv_str' => $message->receivedString,
            'answered' => $message->flags->answered,
            // The cc and inReplyTo fields come in as arrays with the
            // address as the index and the name as the value. Create
            // the proper comma separated strings for these fields.
            'cc' => $this->formatAddress( $message->cc ),
            'bcc' => $this->formatAddress( $message->bcc ),
            'reply_to' => $this->formatAddress( $message->replyTo ),
            'attachments' => $this->formatAttachments( $message->getAttachments() )
        ]);
    }

    /**
     * Takes in an array of message unique IDs and marks them all as
     * deleted in the database.
     * @param array $uniqueIds
     * @param int $accountId
     * @param int $folderId
     * @throws DatabaseUpdateException
     */
    public function markDeleted( $uniqueIds, $accountId, $folderId )
    {
        if ( ! is_array( $uniqueIds ) || ! count( $uniqueIds ) ) {
            return;
        }

        $this->requireInt( $folderId, "Folder ID" );
        $this->requireInt( $accountId, "Account ID" );
        $updated = $this->db()
            ->update([ 'deleted' => 1 ])
            ->table( 'messages' )
            ->where( 'folder_id', '=', $folderId )
            ->where( 'account_id', '=', $accountId )
            ->whereIn( 'unique_id', $uniqueIds )
            ->execute();

        if ( ! Belt::isNumber( $updated ) ) {
            throw new DatabaseUpdateException(
                MESSAGE,
                $this->getError() );
        }
    }

    /**
     * Takes in an array of message unique IDs and sets a flag to on.
     * @param array $uniqueIds
     * @param int $accountId
     * @param int $folderId
     * @param string $flag
     * @param bool $state On or off
     * @param bool $inverse If set, do where not in $uniqueIds query
     * @throws DatabaseUpdateException
     */
    public function markFlag(
        $uniqueIds,
        $accountId,
        $folderId,
        $flag,
        $state = TRUE,
        $inverse = FALSE )
    {
        if ( $inverse === FALSE
            && ( ! is_array( $uniqueIds ) || ! count( $uniqueIds ) ) )
        {
            return;
        }

        $this->isValidFlag( $state, "State" );
        $this->requireInt( $folderId, "Folder ID" );
        $this->requireInt( $accountId, "Account ID" );
        $this->requireValue( $flag, [
            self::FLAG_SEEN, self::FLAG_FLAGGED
        ]);
        $query = $this->db()
            ->update([ $flag => $state ? 1 : 0 ])
            ->table( 'messages' )
            ->where( $flag, '=', $state ? 0 : 1 )
            ->where( 'folder_id', '=', $folderId )
            ->where( 'account_id', '=', $accountId );

        if ( $inverse === TRUE ) {
            if ( count( $uniqueIds ) ) {
                $query->whereNotIn( 'unique_id', $uniqueIds );
            }
        }
        else {
            $query->whereIn( 'unique_id', $uniqueIds );
        }

        $updated = $query->execute();

        if ( ! Belt::isNumber( $updated ) ) {
            throw new DatabaseUpdateException(
                MESSAGE,
                $this->getError() );
        }

        return $updated;
    }

    /**
     * Saves a flag value for a message by ID.
     * @param int $id
     * @param string $flag
     * @param string $value
     * @throws ValidationException
     * @throws DatabaseUpdateException
     */
    public function setFlag( $id, $flag, $value )
    {
        $this->requireInt( $id, 'Message ID' );
        $this->requireValue( $flag, [
            self::FLAG_SEEN, self::FLAG_FLAGGED,
            self::FLAG_DELETED
        ]);

        if ( ! $this->isValidFlag( $value ) ) {
            throw new ValidationException(
                "Invalid flag value '$value' for $flag" );
        }

        $updated = $this->db()
            ->update([
                $flag => $value
            ])
            ->table( 'messages' )
            ->where( 'id', '=', $id )
            ->execute();

        if ( ! Belt::isNumber( $updated ) ) {
            throw new DatabaseUpdateException(
                MESSAGE,
                $this->db()->getError() );
        }
    }

    /**
     * Removes any message from the specified folder that is missing
     * a message_no and unique_id. These messages were copied by the
     * client and not synced yet.
     * @param int $messageId
     * @param int $folderId
     * @throws ValidationException
     */
    public function deleteCopiedMessages( $messageId, $folderId )
    {
        $this->requireInt( $folderId, 'Folder ID' );
        $this->requireInt( $messageId, 'Message ID' );

        $message = $this->getById( $messageId );

        if ( ! $message ) {
            throw new ValidationException(
                "No message found when deleting copies" );
        }

        $deleted = $this->db()
            ->delete()
            ->from( 'messages' )
            ->whereNull( 'unique_id' )
            ->where( 'folder_id', '=', $folderId )
            ->where( 'thread_id', '=', $message->thread_id )
            ->where( 'message_id', '=', $message->message_id )
            ->where( 'account_id', '=', $message->account_id )
            ->execute();

        return is_numeric( $deleted );
    }

    /**
     * Saves a thread ID for the given messages.
     * @param array $ids
     * @param int $threadId
     * @throws DatabaseUpdateException
     * @return int
     */
    public function saveThreadId( $ids, $threadId )
    {
        $this->requireArray( $ids, "IDs" );
        $this->requireInt( $threadId, "Thread ID" );
        $updated = $this->db()
            ->update([ 'thread_id' => $threadId ])
            ->table( 'messages' )
            ->whereIn( 'id', $ids )
            ->execute();

        if ( ! Belt::isNumber( $updated ) ) {
            throw new DatabaseUpdateException(
                MESSAGE,
                $this->getError() );
        }

        return $updated;
    }

    /**
     * Takes in an array of addresses and formats them in a list.
     * @return string
     */
    private function formatAddress( $addresses )
    {
        if ( ! is_array( $addresses ) ) {
            return NULL;
        }

        $formatted = [];

        foreach ( $addresses as $address ) {
            $formatted[] = sprintf(
                "%s <%s>",
                $address->getName(),
                $address->getEmail() );
        }

        return implode( ", ", $formatted );
    }

    /**
     * Attachments need to be serialized. They come in as an array
     * of objects with name, path, and id fields.
     * @param Attachment array $attachments
     * @return string
     */
    private function formatAttachments( $attachments )
    {
        if ( ! is_array( $attachments ) ) {
            return NULL;
        }

        $formatted = [];

        foreach ( $attachments as $attachment ) {
            $formatted[] = $attachment->toArray();
        }

        return json_encode( $formatted, JSON_UNESCAPED_SLASHES );
    }

    /**
     * Creates a hash of the simplified subject line.
     * @param string $subject
     * @return string
     */
    static private function makeSubjectHash( $subject )
    {
        $subject = trim(
            preg_replace(
                "/Re\:|re\:|RE\:|Fwd\:|fwd\:|FWD\:/i",
                '',
                $subject
            ));

        return md5( trim( $subject, '[]()' ) );
    }
}