<?php

namespace App\Model;

use Fn;
use PDO;
use stdClass;
use Exception;
use App\Model;
use App\Config;
use App\Messages\MessageInterface;
use App\Exceptions\NotFoundException;
use App\Exceptions\ValidationException;
use App\Exceptions\DatabaseInsertException;
use App\Exceptions\DatabaseUpdateException;

class Message extends Model implements MessageInterface
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
    public $date_recv;
    public $unique_id;
    public $folder_id;
    public $thread_id;
    public $outbox_id;
    public $text_html;
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
    public $uid_validity;

    // Computed properties
    public $thread_count;

    // Lazy-loaded outbox message
    private $outboxMessage;
    // Cache for threading info
    private $threadCache = [];
    // Cache for attachments
    private $unserializedAttachments;

    // Flags
    const FLAG_SEEN = 'seen';
    const FLAG_FLAGGED = 'flagged';
    const FLAG_DELETED = 'deleted';
    // Options
    const IS_DRAFTS = 'is_drafts';
    const ALL_SIBLINGS = 'all_siblings';
    const ONLY_FLAGGED = 'only_flagged';
    const ONLY_DELETED = 'only_deleted';
    const SPLIT_FLAGGED = 'split_flagged';
    const INCLUDE_DELETED = 'include_deleted';
    const ONLY_FUTURE_SIBLINGS = 'only_future';
    // Default options
    const DEFAULTS = [
        'is_drafts' => false,
        'only_future' => false,
        'all_siblings' => false,
        'only_flagged' => false,
        'only_deleted' => false,
        'split_flagged' => false,
        'include_deleted' => false
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
            'date_recv' => $this->date_recv,
            'unique_id' => $this->unique_id,
            'folder_id' => $this->folder_id,
            'thread_id' => $this->thread_id,
            'outbox_id' => $this->outbox_id,
            'text_html' => $this->text_html,
            'account_id' => $this->account_id,
            'message_id' => $this->message_id,
            'message_no' => $this->message_no,
            'text_plain' => $this->text_plain,
            'references' => $this->references,
            'created_at' => $this->created_at,
            'in_reply_to' => $this->in_reply_to,
            'attachments' => $this->attachments,
            'raw_headers' => $this->raw_headers,
            'raw_content' => $this->raw_content,
            'uid_validity' => $this->uid_validity
        ];
    }

    public function getAttachments()
    {
        if (! is_null($this->unserializedAttachments)) {
            return $this->unserializedAttachments;
        }

        $this->unserializedAttachments = @unserialize($this->attachments);

        return $this->unserializedAttachments;
    }

    public function getOriginal()
    {
        return $this->raw_headers."\r\n".$this->raw_content;
    }

    /**
     * @param bool $stringify If true, return string, otherwise array
     * @param string $ignoreAddress If set, ignore this email in the response
     * @param array $allowedFields Defaults to `from`, `to`, `cc`
     *
     * @return string|array
     */
    public function getReplyAllAddresses(
        bool $stringify = true,
        string $ignoreAddress = '',
        array $allowedFields = [],
        bool $allowEmpty = false
    ) {
        $addresses = [];
        $allowedKeys = ['from', 'to', 'cc', 'reply_to'];
        $fields = array_intersect($allowedKeys, $allowedFields);
        $fields = $fields ?: ['reply_to', 'to', 'cc'];

        foreach ($fields as $field) {
            if ($this->$field) {
                $addresses = array_merge(
                    $addresses,
                    explode(',', $this->$field)
                );
            }
        }

        $list = array_unique(array_filter(array_map('trim', $addresses)));

        if ($ignoreAddress) {
            $list = array_filter(
                $list,
                function ($address) use ($ignoreAddress) {
                    if (trim($address, '<> ') === $ignoreAddress
                        || false !== strpos($address, '<'.$ignoreAddress.'>')
                    ) {
                        return false;
                    } else {
                        return true;
                    }
                }
            );
        }

        if (! $list && false === $allowEmpty) {
            $list = [$this->from];
        }

        return $stringify
            ? implode(', ', $list)
            : array_values($list);
    }

    public function getReplyAddress(bool $stringify = true)
    {
        $replyTo = $this->getReplyAllAddresses($stringify, '', ['reply_to']);

        return $replyTo ?: $this->getReplyAllAddresses($stringify, '', ['from']);
    }

    public function getReplyToAddresses(string $accountEmail = '', bool $stringify = false)
    {
        return $this->getReplyAllAddresses(
            $stringify,
            $accountEmail,
            ['reply_to', 'from', 'to'],
            false
        );
    }

    public function getReplyCcAddresses(string $accountEmail = '', bool $stringify = false)
    {
        return $this->getReplyAllAddresses(
            $stringify,
            $accountEmail,
            ['cc'],
            true
        );
    }

    public function loadById()
    {
        if (! $this->id) {
            throw new NotFoundException;
        }

        return $this->getById($this->id, false, true);
    }

    public function getById(
        int $id,
        bool $throwExceptionOnNotFound = false,
        bool $useSelf = false
    ) {
        $message = $this->db()
            ->select()
            ->from('messages')
            ->where('id', '=', $id)
            ->execute()
            ->fetchObject();

        if (! $message && $throwExceptionOnNotFound) {
            throw new NotFoundException;
        }

        if (true === $useSelf) {
            $this->setData((array) $message);

            return $this;
        }

        return $message
            ? new self((array) $message)
            : false;
    }

    public function getByIds(array $ids)
    {
        if (! $ids) {
            return [];
        }

        return $this->db()
            ->select()
            ->from('messages')
            ->whereIn('id', $ids)
            ->execute()
            ->fetchAll(PDO::FETCH_CLASS, get_class());
    }

    public function getByOutboxId(int $outboxId, int $folderId)
    {
        $message = $this->db()
            ->select()
            ->from('messages')
            ->where('outbox_id', '=', $outboxId)
            ->where('folder_id', '=', $folderId)
            ->execute()
            ->fetch();

        return new self($message ?: null);
    }

    public function getOutboxMessage()
    {
        if ($this->outboxMessage) {
            return $this->outboxMessage;
        }

        $this->outboxMessage = (new Outbox)->getById($this->outbox_id ?: 0);

        return $this->outboxMessage;
    }

    /**
     * Returns the data for an entire thread.
     */
    public function getThread(
        int $accountId,
        int $threadId,
        array $skipFolderIds = [],
        array $onlyFolderIds = []
    ) {
        $query = $this->db()
            ->select()
            ->from('messages')
            ->where('deleted', '=', 0)
            ->where('thread_id', '=', $threadId)
            ->where('account_id', '=', $accountId)
            ->orderBy('date', Model::ASC);

        if ($onlyFolderIds) {
            // Some requests, like viewing the trash folder, want to
            // restrict all messages to that folder ID
            $query->whereIn('folder_id', $onlyFolderIds);
        } elseif ($skipFolderIds) {
            // Most requests want to skip the spam and trash folders
            $query->whereNotIn('folder_id', $skipFolderIds);
        }

        return $query
            ->execute()
            ->fetchAll(PDO::FETCH_CLASS, get_class());
    }

    /**
     * Returns a list of messages by folder and account.
     *
     * @return array Message objects
     */
    public function getThreadsByFolder(
        int $accountId,
        int $folderId,
        int $limit = 50,
        int $offset = 0,
        array $skipFolderIds = [],
        array $onlyFolderIds = [],
        array $options = []
    ) {
        $meta = $this->getThreadCountsByFolder(
            $accountId,
            $folderId,
            $skipFolderIds,
            $onlyFolderIds
        );

        return $this->loadThreadsByThreadIds(
            $meta,
            $accountId,
            $limit,
            $offset,
            $skipFolderIds,
            $onlyFolderIds,
            $options
        );
    }

    /**
     * Returns a list of messages by account and search query,
     * and optionally also by folder.
     *
     * @return array Message objects
     */
    public function getThreadsBySearch(
        int $accountId,
        string $query,
        int $folderId = null,
        int $limit = 50,
        int $offset = 0,
        string $sortBy = 'date',
        array $skipFolderIds = [],
        array $onlyFolderIds = [],
        array $options = []
    ) {
        $meta = $this->getThreadCountsBySearch(
            $accountId,
            $query,
            $folderId,
            $sortBy,
            $skipFolderIds,
            $onlyFolderIds
        );

        return $this->loadThreadsByThreadIds(
            $meta,
            $accountId,
            $limit,
            $offset,
            $skipFolderIds,
            $onlyFolderIds,
            $options
        );
    }

    /**
     * Returns two counts of messages, by flagged and un-flagged.
     * Also returns the thread IDs that belong to both groups.
     *
     * @return object
     */
    public function getThreadCountsByFolder(
        int $accountId,
        int $folderId,
        array $skipFolderIds = [],
        array $onlyFolderIds = []
    ) {
        $counts = (object) [
            'flagged' => 0,
            'unflagged' => 0,
            'flaggedIds' => [],
            'unflaggedIds' => []
        ];

        $threadIds = $this->getThreadIdsByFolder($accountId, $folderId);

        return $this->loadThreadCountsByThreadIds(
            $threadIds,
            $counts,
            $accountId,
            $skipFolderIds,
            $onlyFolderIds
        );
    }

    /**
     * Similar method, but pulls thread counts by a search query.
     *
     * @return object
     */
    public function getThreadCountsBySearch(
        int $accountId,
        string $query,
        int $folderId = null,
        string $sortBy = 'date',
        array $skipFolderIds = [],
        array $onlyFolderIds = []
    ) {
        $counts = (object) [
            'flagged' => 0,
            'unflagged' => 0,
            'flaggedIds' => [],
            'unflaggedIds' => []
        ];

        $threadIds = $this->getThreadIdsBySearch(
            $accountId, $query, $folderId, $sortBy
        );

        return $this->loadThreadCountsByThreadIds(
            $threadIds,
            $counts,
            $accountId,
            $skipFolderIds,
            $onlyFolderIds
        );
    }

    /**
     * Internal method for compiling counts by thread IDs.
     *
     * @return stdClass $counts Modified counts object
     */
    private function loadThreadCountsByThreadIds(
        array $threadIds,
        stdClass $counts,
        int $accountId,
        array $skipFolderIds = [],
        array $onlyFolderIds = []
    ) {
        if (! $threadIds) {
            return $counts;
        }

        $query = $this->db()
            ->select(['thread_id', 'sum(flagged) as flagged_count'])
            ->from('messages')
            ->where('deleted', '=', 0)
            ->where('account_id', '=', $accountId)
            ->whereIn('thread_id', $threadIds)
            ->groupBy(['thread_id']);

        if ($onlyFolderIds) {
            // Some requests, like viewing the trash folder, want to
            // restrict all messages to that folder ID
            $query->whereIn('folder_id', $onlyFolderIds);
        } elseif ($skipFolderIds) {
            // Most requests want to skip the spam and trash folders
            $query->whereNotIn('folder_id', $skipFolderIds);
        }

        // Now fetch all messages in any of these threads. Any message
        // in the thread could be starred.
        $results = $query->execute()->fetchAll(PDO::FETCH_CLASS);

        foreach ($results as $result) {
            if ($result->flagged_count > 0) {
                ++$counts->flagged;
                $counts->flaggedIds[] = $result->thread_id;
            } else {
                ++$counts->unflagged;
                $counts->unflaggedIds[] = $result->thread_id;
            }
        }

        return $counts;
    }

    /**
     * Load the thread IDs for a given folder.
     *
     * @return array
     */
    private function getThreadIdsByFolder(int $accountId, int $folderId)
    {
        $threadIds = [];
        $results = $this->db()
            ->select(['thread_id'])
            ->distinct()
            ->from('messages')
            ->where('deleted', '=', 0)
            ->where('folder_id', '=', $folderId)
            ->where('account_id', '=', $accountId)
            ->execute()
            ->fetchAll(PDO::FETCH_CLASS);

        foreach ($results as $result) {
            $threadIds[] = $result->thread_id;
        }

        return array_values(array_unique($threadIds));
    }

    /**
     * Returns a list of thread IDs by search query and account.
     *
     * @todo This should use a different more custom query builder.
     *       Queries could look like "from:name@email.com" and will
     *       need to be fully parsed properly.
     *
     * @return array
     */
    private function getThreadIdsBySearch(
        int $accountId,
        string $query,
        int $folderId = null,
        string $sortBy = 'date',
        string $sortDir = Model::DESC
    ) {
        // Work with the raw PDO object for this query
        // This will prepare the user query string
        $sql = 'SELECT thread_id, MATCH (subject, text_plain) '.
            'AGAINST (:queryA IN NATURAL LANGUAGE MODE) AS score '.
            'FROM messages '.
            'WHERE deleted = 0 AND account_id = :accountId '.
            ($folderId ? 'AND folder_id = :folderId ' : '').
            'AND MATCH (subject, text_plain) AGAINST (:queryB IN NATURAL LANGUAGE MODE) '.
            'GROUP BY thread_id '.
            'HAVING score > 5 '.
            'ORDER BY :sortBy '.$sortDir;

        $sth = $this->db()->prepare($sql);

        $sth->bindValue(':queryA', $query, PDO::PARAM_STR);
        $sth->bindValue(':queryB', $query, PDO::PARAM_STR);
        $sth->bindValue(':accountId', $accountId, PDO::PARAM_INT);
        $sth->bindValue(':sortBy', $sortBy, PDO::PARAM_STR);

        if ($folderId) {
            $sth->bindValue(':folderId', $folderId, PDO::PARAM_INT);
        }

        $threadIds = [];
        $sth->execute();
        $results = $sth->fetchAll(PDO::FETCH_CLASS);

        foreach ($results as $result) {
            $threadIds[] = $result->thread_id;
        }

        return array_values(array_unique($threadIds));
    }

    /**
     * Internal method for loading threads by a collection of IDs.
     *
     * @return array Message objects
     */
    private function loadThreadsByThreadIds(
        stdClass $meta,
        int $accountId,
        int $limit,
        int $offset,
        array $skipFolderIds,
        array $onlyFolderIds,
        array $options
    ) {
        $threadIds = array_merge($meta->flaggedIds, $meta->unflaggedIds);
        $options = array_merge(self::DEFAULTS, $options);

        if (! $threadIds) {
            return [];
        }

        if (true === $options[self::SPLIT_FLAGGED]) {
            $flagged = $this->getThreads(
                $meta->flaggedIds,
                $accountId, $limit, $offset,
                $skipFolderIds, $onlyFolderIds
            );
            $unflagged = $this->getThreads(
                $meta->unflaggedIds,
                $accountId, $limit, $offset,
                $skipFolderIds, $onlyFolderIds
            );
            $messages = array_merge($flagged, $unflagged);
        } elseif (true === $options[self::ONLY_FLAGGED]) {
            $messages = $this->getThreads(
                $meta->flaggedIds,
                $accountId, $limit, $offset,
                $skipFolderIds, $onlyFolderIds
            );
        } else {
            $messages = $this->getThreads(
                $threadIds,
                $accountId, $limit, $offset,
                $skipFolderIds, $onlyFolderIds
            );
        }

        // Load all messages in these threads. We need to get the names
        // of anyone involved in the thread, any folders, and the subject
        // from the first message in the thread, and the date from the
        // last message in the thread.
        $query = $this->db()
            ->select([
                'id', '`from`', 'thread_id',
                'message_id', 'folder_id', 'seen',
                'subject', '`date`', 'date_recv'
            ])
            ->from('messages')
            ->whereIn('thread_id', $threadIds)
            ->where('account_id', '=', $accountId)
            ->orderBy('`date`', Model::ASC);

        if (false === $options[self::INCLUDE_DELETED]) {
            $query->where('deleted', '=', 0);
        }

        $threadMessages = $query->execute()->fetchAll(PDO::FETCH_CLASS);

        $messages = $this->buildThreadMessages(
            $meta,
            $threadMessages,
            $messages,
            $skipFolderIds,
            $onlyFolderIds,
            $options[self::IS_DRAFTS] ?? false
        );

        return $messages ?: [];
    }

    /**
     * Load the messages for threading. The limit and offset need to
     * be applied after the threads are loaded. This is because we need
     * to load all messages before figuring out the date sorting, and
     * subsequent slicing of the array into the page.
     *
     * @return array Messages
     */
    private function getThreads(
        array $threadIds,
        int $accountId,
        int $limit,
        int $offset,
        array $skipFolderIds,
        array $onlyFolderIds
    ) {
        // Load all of the threads and sort by date
        $allThreadIds = $this->getThreadDates($threadIds, $accountId);

        // Take the slice of this list before querying
        // for the large payload of data below
        $sliceIds = array_slice($allThreadIds, $offset, $limit);

        if (! count($sliceIds)) {
            return [];
        }

        // Store additional query fields
        $ignoreFolders = array_diff($skipFolderIds, $onlyFolderIds);

        // Load all of the unique messages along with some
        // meta data. This is used to then locate the most
        // recent messages.
        $qry = $this->db()
            ->select([
                'id', 'thread_id', 'seen', 'folder_id', '`date`'
            ])
            ->from('messages')
            ->whereIn('thread_id', $sliceIds)
            ->where('deleted', '=', 0)
            ->groupBy('message_id')
            ->orderBy('thread_id', Model::ASC)
            ->orderBy('date', Model::DESC);

        if ($ignoreFolders) {
            $qry->whereNotIn('folder_id', $ignoreFolders);
        }

        $messages = $qry->execute()->fetchAll();

        // Locate the most recent message ID and the oldest
        // unseen message ID. These are used for querying
        // the full message data.
        $recents = [];
        $unseens = [];
        $textPlains = [];

        foreach ($messages as $message) {
            if (! isset($recents[$message['thread_id']])) {
                $recents[$message['thread_id']] = $message['id'];
            }

            // We want the snippet to contain the oldest
            // unread message unless 
            if (0 === (int) $message['seen']) {
                $unseens[$message['thread_id']] = $message['id'];
            }
        }

        if (! $recents) {
            return [];
        }

        // Load all of the recent messages
        $threads = $this->db()
            ->select([
                'id', '`to`', 'cc', '`from`', '`date`',
                'seen', 'subject', 'flagged', 'thread_id',
                'charset', 'message_id', 'outbox_id',
                'text_plain'
            ])
            ->from('messages')
            ->whereIn('id', $recents)
            ->execute()
            ->fetchAll(PDO::FETCH_CLASS, get_class());

        if ($unseens) {
            $textPlains = $this->db()
                ->select(['thread_id', 'text_plain'])
                ->from('messages')
                ->whereIn('id', $unseens)
                ->execute()
                ->fetchAll();
            $textPlains = array_combine(
                array_column($textPlains, 'thread_id'),
                array_column($textPlains, 'text_plain')
            );
        }

        foreach ($threads as $thread) {
            if (isset($textPlains[$thread->thread_id])) {
                $thread->text_plain = $textPlains[$thread->thread_id];
            }
        }

        return $threads;
    }

    /**
     * Returns thread and message IDs for the most recent
     * message in a set of threads. Result set includes
     * an array of rows containing thread_id and date.
     *
     * @return array
     */
    private function getThreadDates(array $threadIds, int $accountId)
    {
        if (! $threadIds) {
            return [];
        }

        $results = $this->db()
            ->select(['thread_id', 'max(date) as max_date'])
            ->from('messages')
            ->where('deleted', '=', 0)
            ->where('account_id', '=', $accountId)
            ->whereIn('thread_id', $threadIds)
            ->groupBy(['thread_id'])
            ->orderBy('max_date', Model::DESC)
            ->execute()
            ->fetchAll();

        return array_column($results ?: [], 'thread_id');
    }

    /**
     * Takes in raw message and thread data and combines them into
     * an array of messages with additional properties.
     *
     * @return array
     */
    private function buildThreadMessages(
        stdClass $meta,
        array $threadMessages,
        array $messages,
        array $skipFolderIds,
        array $onlyFolderIds,
        bool $preferNewest = false
    ) {
        $threads = [];
        $messageIds = [];

        foreach ($threadMessages as $row) {
            // Store the first message we find for the thread
            if (! isset($threads[$row->thread_id]) || $preferNewest) {
                $threads[$row->thread_id] = (object) [
                    'count' => 0,
                    'names' => [],
                    'seens' => [],
                    'folders' => [],
                    'id' => $row->id,
                    'unseen' => false,
                    'subject' => $row->subject,
                    'date' => $row->date_recv ?: $row->date
                ];
            }

            if ($onlyFolderIds) {
                $threads[$row->thread_id]->folders[$row->folder_id] = true;

                if (! in_array($row->folder_id, $onlyFolderIds)) {
                    continue;
                }
            }

            if ($skipFolderIds && in_array($row->folder_id, $skipFolderIds)) {
                continue;
            }

            // Update the list of folders in this thread
            $threads[$row->thread_id]->folders[$row->folder_id] = true;

            if ($row->message_id) {
                if (isset($messageIds[$row->message_id])) {
                    continue;
                } elseif (1 !== (int) $row->seen) {
                    $threads[$row->thread_id]->unseen = true;
                }

                $name = $this->getName($row->from);

                ++$threads[$row->thread_id]->count;
                $threads[$row->thread_id]->id = $row->id;
                $threads[$row->thread_id]->names[] = $name;
                $threads[$row->thread_id]->seens[] = $row->seen;
                $threads[$row->thread_id]->date = $row->date_recv ?: $row->date;

                $messageIds[$row->message_id] = true;
            }
        }

        foreach ($messages as $message) {
            $message->names = [];
            $message->seens = [];
            $message->folders = [];
            $message->thread_count = 1;

            if (isset($threads[$message->thread_id])) {
                $found = $threads[$message->thread_id];

                $message->id = $found->id;
                $message->date = $found->date;
                $message->names = $found->names;
                $message->seens = $found->seens;
                $message->subject = $found->subject;
                $message->thread_count = $found->count;
                $message->folders = array_keys($found->folders);
                // Update master seen flag
                $message->seen = $found->unseen ? 0 : 1;
            }

            if (in_array($message->thread_id, $meta->flaggedIds)) {
                $message->flagged = 1;
            }
        }

        return $messages;
    }

    public function getUnreadCounts(
        int $accountId,
        array $skipFolderIds,
        int $draftMailboxId
    ) {
        $indexed = [];
        $unseenThreadIds = $this->getUnseenThreads($accountId, $skipFolderIds);

        if ($unseenThreadIds) {
            $qry = $this->db()
                ->select(['folder_id', 'thread_id'])
                ->from('messages')
                ->where('deleted', '=', 0)
                ->where('seen', '=', 0)
                ->whereIn('thread_id', $unseenThreadIds)
                ->groupBy(['folder_id', 'thread_id']);

            if ($skipFolderIds) {
                $qry->whereNotIn('folder_id', $skipFolderIds);
            }

            $folderThreads = $qry->execute()->fetchAll(PDO::FETCH_CLASS);

            foreach ($folderThreads as $thread) {
                if (! isset($indexed[$thread->folder_id])) {
                    $indexed[$thread->folder_id] = 0;
                }

                ++$indexed[$thread->folder_id];
            }
        }

        // Set all messages as unread for the drafts mailbox
        if ($draftMailboxId) {
            $draftThreads = $this->db()
                ->select(['count(distinct(thread_id)) as count'])
                ->from('messages')
                ->where('deleted', '=', 0)
                ->where('folder_id', '=', $draftMailboxId)
                ->execute()
                ->fetch();
        }

        $indexed[$draftMailboxId] = $draftThreads['count'] ?? 0;

        return $indexed;
    }

    private function getUnseenThreads(int $accountId, array $skipFolderIds)
    {
        $threadIds = [];
        $qry = $this->db()
            ->select(['thread_id'])
            ->from('messages')
            ->where('seen', '=', 0)
            ->where('deleted', '=', 0)
            ->where('account_id', '=', $accountId);

        if ($skipFolderIds) {
            $qry->whereNotIn('folder_id', $skipFolderIds);
        }

        $threads = $qry->execute()->fetchAll(PDO::FETCH_CLASS);

        if (! $threads) {
            return [];
        }

        foreach ($threads as $row) {
            $threadIds[] = $row->thread_id;
        }

        return array_values(array_unique($threadIds));
    }

    /**
     * @return stdClass Object with three properties:
     *   count, thread_count, and size
     */
    public function getSizeCounts(int $accountId)
    {
        return $this->db()
            ->select([
                'count(distinct(message_id)) as count',
                'count(distinct(thread_id)) as thread_count',
                'sum(size) as size'
            ])
            ->from('messages')
            ->where('deleted', '=', 0)
            ->where('account_id', '=', $accountId)
            ->execute()
            ->fetchObject();
    }

    public function getName(string $from)
    {
        $from = trim($from);
        $pos = strpos($from, '<');

        if (false !== $pos && $pos > 0) {
            return trim(substr($from, 0, $pos));
        }

        return trim($from, '<> ');
    }

    /**
     * Store a computed thread count object in the cache.
     */
    private function setThreadCache(int $accountId, int $folderId, stdClass $counts)
    {
        $this->threadsCache[$accountId.':'.$folderId] = $counts;
    }

    /**
     * Checks the cache and returns (if set) the counts object.
     *
     * @return bool | object
     */
    private function threadCache(int $accountId, int $folderId)
    {
        $key = $accountId.':'.$folderId;

        if (! isset($this->threadCache[$key])) {
            return false;
        }

        return $this->threadCache[$key];
    }

    /**
     * Returns any message with the same message ID and thread ID.
     *
     * @return array of Messages
     */
    public function getSiblings(array $filters = [], array $options = [])
    {
        $ids = [];
        $addSelf = true;
        $options = array_merge(self::DEFAULTS, $options);

        // If even one filter value is different from the 
        // corresponding value on this message, then don't
        // include this message in the query
        foreach ($filters as $key => $value) {
            if (is_array($value)) {
                if (! in_array($this->$key, $value)) {
                    $addSelf = false;
                    break;
                }
            } elseif ((string) $this->$key !== (string) $value) {
                $addSelf = false;
                break;
            }
        }

        if ($addSelf) {
            $ids[] = $this->id;
        }

        $query = $this->db()
            ->select([
                'id', 'account_id', 'folder_id', 'subject',
                'message_id', 'thread_id', 'seen', 'draft',
                'recent', 'flagged', 'deleted', 'answered',
                'synced', 'date'
            ])
            ->from('messages')
            ->whereIn('deleted', true === $options[self::ONLY_DELETED]
                ? [1]
                : (true === $options[self::INCLUDE_DELETED]
                    ? [0, 1]
                    : [0]))
            ->where('thread_id', '=', $this->thread_id)
            ->where('account_id', '=', $this->account_id);

        if (false === $options[self::ALL_SIBLINGS]) {
            if (! $this->message_id) {
                $query->where('id', '=', $this->id);
            } else {
                $query->where('message_id', '=', $this->message_id);
            }
        }

        if (true === $options[self::ONLY_FUTURE_SIBLINGS]) {
            $query->where('date', '>=', $this->date);
        }

        foreach ($filters as $key => $value) {
            if (is_array($value) && count($value)) {
                $query->whereIn($key, $value);
            } else {
                $query->where($key, '=', $value);
            }
        }

        return $query->execute()->fetchAll(PDO::FETCH_CLASS, get_class());
    }

    /**
     * Creates or modifies a draft message based on an outbox message.
     * Only creates a new message if the outbox is a draft.
     *
     * @param Outbox $outbox
     * @param int $draftsId Drafts mailbox (folder) ID
     * @param Message $parent Message being replied to
     */
    public function createOrUpdateDraft(
        Outbox $outbox,
        int $draftsId,
        Message $parent = null
    ) {
        // Only create the draft if the outbox message is a draft
        if (1 !== (int) $outbox->draft) {
            return;
        }

        // New message will be returned if not found
        $message = $this->getByOutboxId($outbox->id, $draftsId);
        // Set the date to now and stored in UTC
        $utcDate = $this->utcDate();
        $localDate = $this->localDate();
        // Flags
        $message->seen = 1;
        $message->deleted = 0;
        // ID fields
        $message->unique_id = null;
        $message->message_no = null;
        $message->folder_id = $draftsId;
        $message->outbox_id = $outbox->id;
        $message->account_id = $outbox->account_id;
        // String fields
        $message->from = $outbox->from;
        $message->subject = $outbox->subject;
        $message->text_html = $outbox->text_html;
        $message->text_plain = $outbox->text_plain;
        $message->to = implode(', ', $outbox->to);
        $message->cc = implode(', ', $outbox->cc);
        $message->bcc = implode(', ', $outbox->bcc);
        // Date fields
        $message->date = $utcDate->format(DATE_DATABASE);
        $message->date_str = $localDate->format(DATE_RFC822);
        $message->date_recv = $utcDate->format(DATE_DATABASE);

        if ($parent) {
            $message->thread_id = $parent->thread_id;
            $message->in_reply_to = $parent->message_id;
            $message->message_id = Config::newMessageId();
            $message->references = $parent->references
                ? $parent->references.', '.$parent->message_id
                : $parent->message_id;

            return $this->createOrUpdate($message, true, false);
        }

        return $this->createOrUpdate($message);
    }

    public function createOrUpdate(
        Message $message,
        bool $removeNulls = true,
        bool $setThreadId = true
    ) {
        $data = $message->getData();

        if (true === $removeNulls) {
            $data = array_filter($data, function ($var) {
                return null !== $var;
            });
        }

        if ($message->exists()) {
            $updated = $this->db()
                ->update($data)
                ->table('messages')
                ->where('id', '=', $message->id)
                ->execute();

            if (! is_numeric($updated)) {
                throw new DatabaseUpdateException(
                    "Failed updating message {$message->id}"
                );
            }
        } else {
            $newMessageId = $this->db()
                ->insert(array_keys($data))
                ->into('messages')
                ->values(array_values($data))
                ->execute();

            if (! $newMessageId) {
                throw new DatabaseInsertException('Failed creating new message');
            }

            $message->id = $newMessageId;

            // Update the thread ID
            if (true === $setThreadId) {
                $message->setThreadId($newMessageId);
            }
        }

        return $message;
    }

    /**
     * Stores the thread ID on a message.
     *
     * @param int $threadId
     *
     * @return bool
     */
    public function setThreadId(int $threadId)
    {
        if (! $this->exists()) {
            return false;
        }

        $updated = $this->db()
            ->update(['thread_id' => $threadId])
            ->table('messages')
            ->where('id', '=', $this->id)
            ->execute();

        return is_numeric($updated) ? $updated : false;
    }

    /**
     * Updates a flag on the message.
     */
    public function setFlag(string $flag, bool $state, int $id = null)
    {
        $this->$flag = $state;

        $updated = $this->db()
            ->update([
                $flag => $state ? 1 : 0
            ])
            ->table('messages')
            ->where('id', '=', $id ?? $this->id)
            ->execute();

        return is_numeric($updated) ? $updated : false;
    }

    /**
     * Create a new message in the specified folder.
     *
     * @return Message
     *
     * @throws Exception
     */
    public function copyTo(int $folderId)
    {
        // If this message exists in the folder and is not deleted,
        // then skip the operation.
        $existingMessage = $this->db()
            ->select(['id'])
            ->from('messages')
            ->where('deleted', '=', 0)
            ->where('folder_id', '=', $folderId)
            ->where('thread_id', '=', $this->thread_id)
            ->where('message_id', '=', $this->message_id)
            ->where('account_id', '=', $this->account_id)
            ->execute()
            ->fetchObject();

        if ($existingMessage && $existingMessage->id) {
            return true;
        }

        $data = $this->getData();
        unset($data['id']);
        $data['synced'] = 0;
        $data['deleted'] = 0;
        $data['unique_id'] = null;
        $data['message_no'] = null;
        $data['seen'] = $this->seen;
        $data['folder_id'] = $folderId;
        $data['flagged'] = $this->flagged;

        $newMessageId = $this->db()
            ->insert(array_keys($data))
            ->into('messages')
            ->values(array_values($data))
            ->execute();

        if (! $newMessageId) {
            throw new DatabaseInsertException(
                "Failed copying message {$this->id} to Folder #{$folderId}"
            );
        }

        $data['id'] = $newMessageId;

        return new self($data);
    }

    /**
     * @throws NotFoundException
     */
    public function delete()
    {
        if (! $this->exists()) {
            throw new NotFoundException;
        }

        $deleted = $this->db()
            ->delete()
            ->table('messages')
            ->where('id', '=', $this->id)
            ->execute();

        return is_numeric($deleted) ? $deleted : false;
    }

    /**
     * @throws NotFoundException
     *
     * @return bool
     */
    public function softDelete(bool $purge = false)
    {
        if (! $this->exists()) {
            throw new NotFoundException;
        }

        $updates = ['deleted' => 1];

        if (true === $purge) {
            $updates['purge'] = 1;
        }

        $updated = $this->db()
            ->update($updates)
            ->table('messages')
            ->where('id', '=', $this->id)
            ->execute();

        return is_numeric($updated) ? $updated : false;
    }

    /**
     * Hard removes any message from the specified folder that is
     * missing a unique_id. These messages were copied by the client
     * and not synced yet.
     *
     * @throws ValidationException
     *
     * @return bool
     */
    public function deleteCopiesFrom(int $folderId)
    {
        if (! $this->exists() || ! $this->message_id) {
            throw new ValidationException(
                'Message needs to be loaded before copies are deleted'
            );
        }

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
     * @throws NotFoundException
     *
     * @return bool
     */
    public function deleteCreatedMessage()
    {
        if (! $this->exists()) {
            throw new ValidationException(
                'Message needs to be loaded before it can be deleted'
            );
        }

        if ($this->outbox_id) {
            // Throws NotFoundException
            return (new Outbox)->getById($this->outbox_id, true)->softDelete()
                && $this->softDelete(true);
        }

        // Throws NotFoundException
        return $this->softDelete(true);
    }
}
