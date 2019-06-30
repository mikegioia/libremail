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
use App\Exceptions\NotFound as NotFoundException;
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
    public $purge;
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
    public $raw_headers;
    public $raw_content;

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
            'purge' => $this->purge,
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
            'attachments' => $this->attachments,
            'raw_headers' => $this->raw_headers,
            'raw_content' => $this->raw_content
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
        return Fn\intEq($this->synced, 1);
    }

    public function isDeleted()
    {
        return Fn\intEq($this->deleted, 1);
    }

    public function getAttachments()
    {
        if (! is_null($this->unserializedAttachments)) {
            return $this->unserializedAttachments;
        }

        $this->unserializedAttachments = @unserialize($this->attachments);

        return $this->unserializedAttachments;
    }

    public function loadById()
    {
        if (! $this->id) {
            throw new NotFoundException(MESSAGE);
        }

        $message = $this->getById($this->id);

        if ($message) {
            $this->setData($message);
        }

        return $this;
    }

    public function getById(int $id)
    {
        return $this->db()
            ->select()
            ->from('messages')
            ->where('id', '=', $id)
            ->execute()
            ->fetch(PDO::FETCH_OBJ);
    }

    public function getByIds(array $ids)
    {
        return $this->db()
            ->select()
            ->from('messages')
            ->whereIn('id', $ids)
            ->execute()
            ->fetchAll(PDO::FETCH_CLASS, $this->getClass());
    }

    public function getSubjectHash()
    {
        return self::makeSubjectHash($this->subject ?: '');
    }

    /**
     * Fetches a range of messages for an account. Used during the
     * threading computation.
     *
     * @param int $accountId
     * @param int $minId
     * @param int $maxId
     * @param int $limit
     *
     * @return array Messages
     */
    public function getRangeForThreading(
        int $accountId,
        int $minId,
        int $maxId,
        int $limit
    ) {
        return $this->db()
            ->select([
                'id', 'thread_id', 'message_id', '`date`',
                'in_reply_to', '`references`', 'subject',
                '`to`', 'cc', 'bcc', '`from`'
            ])
            ->from('messages')
            ->where('id', '>=', $minId)
            ->where('id', '<=', $maxId)
            ->where('account_id', '=', $accountId)
            ->limit($limit)
            ->execute()
            ->fetchAll(PDO::FETCH_CLASS, $this->getClass());
    }

    /**
     * Finds the highest ID for an account.
     *
     * @param int $accountId
     *
     * @return int
     */
    public function getMaxMessageId(int $accountId)
    {
        $row = $this->db()
            ->select(['max(id) as max'])
            ->from('messages')
            ->where('account_id', '=', $accountId)
            ->execute()
            ->fetch();

        return $row ? $row['max'] : 0;
    }

    /**
     * Finds the lowest ID for an account.
     *
     * @param int $accountId
     *
     * @return int
     */
    public function getMinMessageId(int $accountId)
    {
        $row = $this->db()
            ->select(['min(id) as min'])
            ->from('messages')
            ->where('account_id', '=', $accountId)
            ->execute()
            ->fetch();

        return $row ? $row['min'] : 0;
    }

    /**
     * Returns a list of integer unique IDs given an account ID
     * and a folder ID to search. This fetches IDs in pages to
     * not exceed any memory limits on the query response.
     *
     * @param int $accountId
     * @param int $folderId
     *
     * @return array of ints The index is the message_no and
     *   the value is the unique_id
     */
    public function getSyncedIdsByFolder(int $accountId, int $folderId)
    {
        $ids = [];
        $limit = 10000;
        $count = $this->countSyncedIdsByFolder($accountId, $folderId);

        for ($offset = 0; $offset < $count; $offset += $limit) {
            $ids += $this->getPagedSyncedIdsByFolder(
                $accountId,
                $folderId,
                $offset,
                $limit
            );
        }

        return $ids;
    }

    /**
     * Returns a count of unique IDs for an account.
     *
     * @param int $accountId
     *
     * @return int
     */
    public function countByAccount(int $accountId)
    {
        $this->requireInt($accountId, 'Account ID');

        $messages = $this->db()
            ->select()
            ->clear()
            ->count(1, 'count')
            ->from('messages')
            ->where('account_id', '=', $accountId)
            ->execute()
            ->fetch();

        return $messages ? $messages['count'] : 0;
    }

    /**
     * Returns a count of unique IDs for a folder.
     *
     * @param int $accountId
     * @param int $folderId
     *
     * @return int
     */
    public function countSyncedIdsByFolder(int $accountId, int $folderId)
    {
        $this->requireInt($folderId, 'Folder ID');
        $this->requireInt($accountId, 'Account ID');

        $messages = $this->db()
            ->select()
            ->clear()
            ->count(1, 'count')
            ->from('messages')
            ->where('synced', '=', 1)
            ->where('deleted', '=', 0)
            ->where('folder_id', '=', $folderId)
            ->where('account_id', '=', $accountId)
            ->execute()
            ->fetch();

        return $messages ? $messages['count'] : 0;
    }

    /**
     * This method is called by getSyncedIdsByFolder to return a
     * page of results.
     *
     * @param int $accountId
     * @param int $folderId
     * @param int $offset
     * @param int $limit
     *
     * @return array of ints
     */
    private function getPagedSyncedIdsByFolder(
        int $accountId,
        int $folderId,
        int $offset = 0,
        int $limit = 100
    ) {
        $this->requireInt($folderId, 'Folder ID');
        $this->requireInt($accountId, 'Account ID');

        $ids = [];
        $messages = $this->db()
            ->select(['unique_id', 'message_no'])
            ->from('messages')
            ->where('synced', '=', 1)
            ->where('deleted', '=', 0)
            ->where('folder_id', '=', $folderId)
            ->where('account_id', '=', $accountId)
            ->limit($limit, $offset)
            ->execute()
            ->fetchAll();

        if (! $messages) {
            return $ids;
        }

        foreach ($messages as $message) {
            $ids[$message['message_no']] = $message['unique_id'];
        }

        return $ids;
    }

    /**
     * Create or updates a message record.
     *
     * @param array $data
     * @param array $skipFields Set of fields to not update
     *
     * @throws ValidationException
     * @throws DatabaseUpdateException
     * @throws DatabaseInsertException
     */
    public function save(array $data = [], array $skipFields = [])
    {
        $val = new Validator;

        $val->required('folder_id', 'Folder ID')->integer();
        $val->required('unique_id', 'Unique ID')->integer();
        $val->required('account_id', 'Account ID')->integer();
        // Optional fields
        $val->required('size', 'Size')->integer();
        $val->required('message_no', 'Message Number')->integer();
        $val->optional('date', 'Date')->datetime(DATE_DATABASE);
        $val->optional('subject', 'Subject')->lengthBetween(0, 270);
        $val->optional('charset', 'Charset')->lengthBetween(0, 100);
        $val->optional('date_str', 'RFC Date')->lengthBetween(0, 100);
        $val->optional('seen', 'Seen')->callback([$this, 'isValidFlag']);
        $val->optional('message_id', 'Message ID')->lengthBetween(0, 250);
        $val->optional('draft', 'Draft')->callback([$this, 'isValidFlag']);
        $val->optional('recv_str', 'Received Date')->lengthBetween(0, 250);
        $val->optional('in_reply_to', 'In-Reply-To')->lengthBetween(0, 250);
        $val->optional('recent', 'Recent')->callback([$this, 'isValidFlag']);
        $val->optional('date_recv', 'Date Received')->datetime(DATE_DATABASE);
        $val->optional('flagged', 'Flagged')->callback([$this, 'isValidFlag']);
        $val->optional('deleted', 'Deleted')->callback([$this, 'isValidFlag']);
        $val->optional('answered', 'Answered')->callback([$this, 'isValidFlag']);

        $this->setData($data);

        $data = $this->getData();

        if ($skipFields) {
            $data = array_diff_key($data, array_flip($skipFields));
        }

        if (! $val->validate($data)) {
            throw new ValidationException(
                $this->getErrorString(
                    $val,
                    'This message is missing required data.'
                ));
        }

        // Update flags to have the right data type
        $this->updateFlagValues($data, [
            'seen', 'draft', 'recent', 'flagged',
            'deleted', 'answered', 'purge'
        ]);
        $this->updateUtf8Values($data, [
            'subject', 'text_html', 'text_plain',
            'raw_headers', 'raw_content'
        ]);

        // Check if this message exists
        $exists = $this->db()
            ->select()
            ->from('messages')
            ->where('folder_id', '=', $this->folder_id)
            ->where('unique_id', '=', $this->unique_id)
            ->where('account_id', '=', $this->account_id)
            ->execute()
            ->fetchObject();
        $updateMessage = function ($db, $id, $data) {
            return $db
                ->update($data)
                ->table('messages')
                ->where('id', '=', $id)
                ->execute();
        };
        $insertMessage = function ($db, $data) {
            return $db
                ->insert(array_keys($data))
                ->into('messages')
                ->values(array_values($data))
                ->execute();
        };

        if ($exists) {
            $this->id = $exists->id;
            unset($data['id']);
            unset($data['created_at']);

            try {
                $updated = $updateMessage($this->db(), $this->id, $data);
            } catch (PDOException $e) {
                // Check for bad UTF-8 errors
                if (strpos($e->getMessage(), 'Incorrect string value:')) {
                    $data['subject'] = Encoding::fixUTF8($data['subject']);
                    $data['text_html'] = Encoding::fixUTF8($data['text_html']);
                    $data['text_plain'] = Encoding::fixUTF8($data['text_plain']);
                    $data['raw_headers'] = Encoding::fixUTF8($data['raw_headers']);
                    $data['raw_content'] = Encoding::fixUTF8($data['raw_content']);
                    $newMessageId = $updateMessage($this->db(), $data);
                } else {
                    throw $e;
                }
            }

            $this->errorHandle($updated);

            return;
        }

        $createdAt = new DateTime;
        unset($data['id']);
        $data['created_at'] = $createdAt->format(DATE_DATABASE);

        try {
            $newMessageId = $insertMessage($this->db(), $data);
        } catch (PDOException $e) {
            // Check for bad UTF-8 errors
            if (strpos($e->getMessage(), 'Incorrect string value:')) {
                $data['subject'] = Encoding::fixUTF8($data['subject']);
                $data['text_html'] = Encoding::fixUTF8($data['text_html']);
                $data['text_plain'] = Encoding::fixUTF8($data['text_plain']);
                $newMessageId = $insertMessage($this->db(), $data);
            } else {
                throw $e;
            }
        }

        if (! $newMessageId) {
            throw new DatabaseInsertException(
                MESSAGE,
                $this->getError()
            );
        }

        $this->id = $newMessageId;
    }

    /**
     * Saves the meta information and content for a message as data
     * on the class object.
     *
     * @param array $meta
     * @param array $options
     */
    public function setMessageData(ImapMessage $message, array $options = [])
    {
        if (true === Fn\get($options, self::OPT_TRUNCATE_FIELDS)) {
            $message->subject = substr($message->subject, 0, 270);
        }

        if (true === Fn\get($options, self::OPT_SKIP_CONTENT)) {
            $message->textHtml = null;
            $message->textPlain = null;
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
            'raw_headers' => $message->rawHeaders,
            'raw_content' => $message->rawContent,
            'recv_str' => $message->receivedString,
            'answered' => $message->flags->answered,
            // The cc and inReplyTo fields come in as arrays with the
            // address as the index and the name as the value. Create
            // the proper comma separated strings for these fields.
            'cc' => $this->formatAddress($message->cc),
            'bcc' => $this->formatAddress($message->bcc),
            'reply_to' => $this->formatAddress($message->replyTo),
            'attachments' => $this->formatAttachments($message->getAttachments())
        ]);
    }

    /**
     * Takes in an array of message unique IDs and marks them all as
     * deleted in the database.
     *
     * @param array $uniqueIds
     * @param int $accountId
     * @param int $folderId
     *
     * @throws DatabaseUpdateException
     */
    public function markDeleted(array $uniqueIds, int $accountId, int $folderId)
    {
        if (! $uniqueIds || ! count($uniqueIds)) {
            return;
        }

        $updated = $this->db()
            ->update(['deleted' => 1])
            ->table('messages')
            ->where('folder_id', '=', $folderId)
            ->where('account_id', '=', $accountId)
            ->whereIn('unique_id', $uniqueIds)
            ->execute();

        $this->errorHandle($updated);
    }

    /**
     * Takes in an array of message unique IDs and sets a flag to on.
     *
     * @param array $uniqueIds
     * @param int $accountId
     * @param int $folderId
     * @param string $flag
     * @param bool $state On or off
     * @param bool $inverse If set, do where not in $uniqueIds query
     *
     * @throws DatabaseUpdateException
     */
    public function markFlag(
        array $uniqueIds,
        int $accountId,
        int $folderId,
        string $flag,
        bool $state = true,
        bool $inverse = false
    ) {
        if (false === $inverse
            && (! is_array($uniqueIds) || ! count($uniqueIds))
        ) {
            return;
        }

        $this->isValidFlag($state, 'State');
        $this->requireInt($folderId, 'Folder ID');
        $this->requireInt($accountId, 'Account ID');
        $this->requireValue($flag, [
            self::FLAG_SEEN, self::FLAG_FLAGGED
        ]);
        $query = $this->db()
            ->update([$flag => $state ? 1 : 0])
            ->table('messages')
            ->where($flag, '=', $state ? 0 : 1)
            ->where('folder_id', '=', $folderId)
            ->where('account_id', '=', $accountId);

        if (true === $inverse) {
            if (count($uniqueIds)) {
                $query->whereNotIn('unique_id', $uniqueIds);
            }
        } else {
            $query->whereIn('unique_id', $uniqueIds);
        }

        $updated = $query->execute();

        $this->errorHandle($updated);

        return $updated;
    }

    /**
     * Saves a flag value for a message by ID.
     *
     * @throws ValidationException
     * @throws DatabaseUpdateException
     */
    public function setFlag(string $flag, string $value)
    {
        $this->requireInt($this->id, 'Message ID');
        $this->requireValue($flag, [
            self::FLAG_SEEN, self::FLAG_FLAGGED,
            self::FLAG_DELETED
        ]);

        if (! $this->isValidFlag($value)) {
            throw new ValidationException(
                "Invalid flag value '$value' for $flag"
            );
        }

        $updated = $this->db()
            ->update([
                $flag => $value
            ])
            ->table('messages')
            ->where('id', '=', $this->id)
            ->execute();

        $this->errorHandle($updated);
    }

    /**
     * Sets a message to be killed during the purge.
     *
     * @param int $id Optional, defaults to internal ID
     *
     * @throws DatabaseUpdateException
     */
    public function markPurged(int $id = null)
    {
        $id = $id ?? $this->id;
        $this->requireInt($id, 'Message ID');

        $updated = $this->db()
            ->update(['purge' => 1])
            ->table('messages')
            ->where('id', '=', $id)
            ->execute();

        $this->errorHandle($updated);
    }

    /**
     * @throws DatabaseUpdateException
     */
    public function softDelete(bool $purge = false)
    {
        $this->requireInt($this->id, 'Message ID');

        $updates = ['deleted' => 1];

        if (true === $purge) {
            $updates['purge'] = 1;
        }

        $updated = $this->db()
            ->update($updates)
            ->table('messages')
            ->where('id', '=', $this->id)
            ->execute();

        $this->errorHandle($updated);
    }

    /**
     * Removes messages from a folder/account that are marked
     * for purge (removal).
     *
     * @param int $accountId
     * @param int $folderId
     *
     * @return int Count of deleted rows
     */
    public function deleteMarkedForPurge(int $accountId, int $folderId)
    {
        $deleted = $this->db()
            ->delete()
            ->from('messages')
            ->where('folder_id', '=', $folderId)
            ->where('account_id', '=', $accountId)
            ->whereNull('unique_id')
            ->whereNull('message_no')
            ->where('purge', '=', 1)
            ->execute();

        return is_numeric($deleted)
            ? $deleted
            : 0;
    }

    /**
     * Removes any message from the specified folder that is missing
     * a message_no and unique_id. These messages were copied by the
     * client and not synced yet.
     *
     * @throws ValidationException
     */
    public function deleteCopiesFrom(int $folderId)
    {
        $this->requireInt($this->thread_id, 'Thread ID');
        $this->requireInt($this->account_id, 'Account ID');
        $this->requireString($this->message_id, 'Message ID');

        $deleted = $this->db()
            ->delete()
            ->from('messages')
            ->whereNull('unique_id')
            ->where('folder_id', '=', $folderId)
            ->where('thread_id', '=', $this->thread_id)
            ->where('message_id', '=', $this->message_id)
            ->where('account_id', '=', $this->account_id)
            ->execute();

        return is_numeric($deleted); // To catch 0s
    }

    /**
     * Soft removes a message created by this application (usually
     * a draft) and any outbox message if there is one attached.
     *
     * @throws ValidationException
     * @throws DatabaseUpdateException
     *
     * @return bool
     */
    public function deleteCreatedMessage()
    {
        $this->requireInt($this->id, 'Message ID');

        if ($this->outbox_id) {
            return (new Outbox($this->outbox_id))->softDelete()
                && $this->softDelete(true);
        }

        return $this->softDelete(true);
    }

    /**
     * Saves a thread ID for the given messages.
     *
     * @param array $ids
     * @param int $threadId
     *
     * @throws DatabaseUpdateException
     *
     * @return int
     */
    public function saveThreadId(array $ids, int $threadId)
    {
        $updated = $this->db()
            ->update(['thread_id' => $threadId])
            ->table('messages')
            ->whereIn('id', $ids)
            ->execute();

        $this->errorHandle($updated);

        return $updated;
    }

    /**
     * Saves a message number for the given message ID.
     *
     * @param int $uniqueId
     * @param int $folderId
     * @param int $messageNo
     *
     * @throws DatabaseUpdateException
     *
     * @return int
     */
    public function saveMessageNo(int $uniqueId, int $folderId, int $messageNo)
    {
        $updated = $this->db()
            ->update(['message_no' => $messageNo])
            ->table('messages')
            ->where('unique_id', '=', $uniqueId)
            ->where('folder_id', '=', $folderId)
            ->execute();

        $this->errorHandle($updated);

        return $updated;
    }

    /**
     * Takes in an array of addresses and formats them in a list.
     *
     * @return string
     */
    private function formatAddress(array $addresses)
    {
        if (! is_array($addresses)) {
            return null;
        }

        $formatted = [];

        foreach ($addresses as $address) {
            $formatted[] = sprintf(
                '%s <%s>',
                $address->getName(),
                $address->getEmail()
            );
        }

        return implode(', ', $formatted);
    }

    /**
     * Attachments need to be serialized. They come in as an array
     * of objects with name, path, and id fields.
     *
     * @param Attachment array $attachments
     *
     * @return string
     */
    private function formatAttachments(array $attachments)
    {
        if (! is_array($attachments)) {
            return null;
        }

        $formatted = [];

        foreach ($attachments as $attachment) {
            $formatted[] = $attachment->toArray();
        }

        return json_encode($formatted, JSON_UNESCAPED_SLASHES);
    }

    /**
     * @param bool | int $updated Response from update operation
     *
     * @throws DatabaseUpdateException
     */
    private function errorHandle($updated)
    {
        if (! Belt::isNumber($updated)) {
            throw new DatabaseUpdateException(
                MESSAGE,
                $this->getError()
            );
        }
    }

    /**
     * Creates a hash of the simplified subject line.
     *
     * @param string $subject
     *
     * @return string
     */
    private static function makeSubjectHash(string $subject)
    {
        $subject = trim(
            preg_replace(
                "/Re\:|re\:|RE\:|Fwd\:|fwd\:|FWD\:/i",
                '',
                $subject
            ));

        return md5(trim($subject, '[]()'));
    }
}
