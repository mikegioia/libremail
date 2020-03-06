<?php

namespace App;

use stdClass;
use App\Model\Account;
use App\Model\Message;
use App\Messages\Names;
use Zend\Escaper\Escaper;

class Messages
{
    private $folders;
    private $accountId;

    const UTF8 = 'utf-8';
    const SNIPPET_LENGTH = 160;

    public function __construct(Account $account, Folders $folders)
    {
        $this->folders = $folders;
        $this->accountId = $account->id;
    }

    /**
     * Load the threads for a folder. Returns two arrays, a starred
     * (or flagged) collection, and non-starred.
     *
     * @return [Message array, Message array, object, object]
     */
    public function getThreadsByFolder(
        int $folderId,
        int $page = 1,
        int $limit = 25,
        array $options = []
    ) {
        $messageModel = new Message;
        $messages = $messageModel->getThreadsByFolder(
            $this->accountId,
            $folderId,
            $limit,
            ($page - 1) * $limit,
            $this->folders->getSkipIds($folderId),
            $this->folders->getRestrictIds($folderId),
            $options
        );
        $messageCounts = $messageModel->getThreadCountsByFolder(
            $this->accountId,
            $folderId,
            $this->folders->getSkipIds($folderId),
            $this->folders->getRestrictIds($folderId)
        );

        return $this->loadThreads(
            $messages,
            $messageCounts,
            $folderId,
            $page,
            $limit,
            $options
        );
    }

    /**
     * Load the threads for a folder. Returns two arrays, a starred
     * (or flagged) collection, and non-starred.
     *
     * @return [Message array, Message array, object, object]
     */
    public function getThreadsBySearch(
        string $query,
        int $folderId = null,
        int $page = 1,
        int $limit = 25,
        string $sortBy = 'date',
        array $options = []
    ) {
        $messageModel = new Message;
        $messages = $messageModel->getThreadsBySearch(
            $this->accountId,
            $query,
            $folderId,
            $limit,
            ($page - 1) * $limit,
            $sortBy,
            $this->folders->getSkipIds($folderId),
            $this->folders->getRestrictIds($folderId),
            $options
        );
        $messageCounts = $messageModel->getThreadCountsBySearch(
            $this->accountId,
            $query,
            $folderId,
            $sortBy,
            $this->folders->getSkipIds($folderId),
            $this->folders->getRestrictIds($folderId)
        );

        return $this->loadThreads(
            $messages,
            $messageCounts,
            $folderId,
            $page,
            $limit,
            $options
        );
    }

    private function loadThreads(
        array $messages,
        stdClass $messageCounts,
        int $folderId = null,
        int $page = 1,
        int $limit = 25,
        array $options = []
    ) {
        $flagged = [];
        $unflagged = [];
        $messageNames = new Names;
        $escaper = new Escaper(self::UTF8);
        $folders = $this->getIndexedFolders($folderId);
        $splitFlagged = isset($options[Message::SPLIT_FLAGGED])
            && true === $options[Message::SPLIT_FLAGGED];

        usort($messages, function ($a, $b) {
            return strcmp($b->date, $a->date);
        });

        foreach ($messages as $message) {
            $this->setDisplayDate($message);
            $this->setSnippet($message, $escaper);
            $this->setFolders($message, $folders);
            $this->setNameList($message, $messageNames);

            if (1 === (int) $message->flagged && $splitFlagged) {
                $flagged[] = $message;
            } else {
                $unflagged[] = $message;
            }
        }

        $paging = $this->buildPaging(
            $messageCounts,
            $page,
            $limit,
            $splitFlagged
        );
        $totals = (new Message)->getSizeCounts($this->accountId);

        return [$flagged, $unflagged, $paging, $totals];
    }

    /**
     * Prepares an HTML-safe snippet to display in the message line.
     */
    private function setSnippet(Message &$message, Escaper $escaper)
    {
        $snippet = '';
        $separator = "\r\n";
        $text = trim(strip_tags($message->text_plain));
        $line = strtok($text, $separator);

        while (false !== $line) {
            if (0 !== strncmp('>', $line, 1)) {
                $snippet .= $line;
            }

            $line = strtok($separator);
        }

        $snippet = $escaper->escapeHtml($snippet);
        $message->snippet = substr(
            ltrim($text, '<>-_='),
            0,
            self::SNIPPET_LENGTH
        );
    }

    /**
     * Prepares a list of names of people involved in the message thread
     * to display in the message line.
     *
     * @todo Make this smarter, right now it only shows original from but
     *   gmail shows multiple people on the thread.
     */
    private function setNameList(Message &$message, Names $messageNames)
    {
        $count = count($message->names);
        $unique = array_values(array_unique($message->names));
        $message->names = $messageNames->get(
            $message->names,
            $message->seens,
            $message->has_draft
        );

        if ($message->thread_count > 1) {
            $message->names .= ' ('.$message->thread_count.')';
        }
    }

    /**
     * Prepares a human-readable date for the message line.
     */
    private function setDisplayDate(Message &$message)
    {
        $today = View::getDate(null, View::DATE_FULL);
        $dateString = $message->date_recv ?: $message->date;
        $messageDate = View::getDate($dateString, View::DATE_FULL);
        $message->display_date = $today === $messageDate
            ? View::getDate($dateString, View::TIME)
            : View::getDate($dateString, View::DATE_SHORT);
    }

    /**
     * Prepare the folder labels for the message.
     */
    private function setFolders(Message &$message, array $folders)
    {
        $message->folder_ids = $message->folders;
        $message->has_draft = in_array(
            $this->folders->getDraftsId(),
            $message->folders);
        $message->folders = array_intersect_key(
            $folders,
            array_flip($message->folders)
        );
    }

    /**
     * Returns a set of folders indexed by folder ID.
     */
    private function getIndexedFolders(int $folderId)
    {
        $folders = [];
        $inboxId = (int) $this->folders->getInboxId();
        $trashId = (int) $this->folders->getTrashId();
        $isInbox = $folderId === $inboxId;
        $isTrash = $folderId === $trashId;

        foreach ($this->folders->get() as $folder) {
            // Normally, hide all mailboxes from showing in the list
            // of folders (labels). If we're viewing any folder but
            // the inbox or trash, then show if it's in the inbox.
            // If we're viewing the trash, show the trash folder.
            $whitelisted = ($isTrash && (int) $folder->id === $trashId)
                || (! $isInbox && ! $isTrash && (int) $folder->id === $inboxId);

            if ($whitelisted
                || (! $folder->is_mailbox
                    && 1 !== (int) $folder->ignored)
            ) {
                $folders[$folder->id] = $folder;
            }
        }

        // Sort the folders first by name
        uasort($folders, function ($a, $b) {
            return $a->name <=> $b->name;
        });

        // and then put the mailboxes first
        uasort($folders, function ($a, $b) {
            return $b->is_mailbox <=> $a->is_mailbox;
        });

        return $folders;
    }

    /**
     * Prepare the counts and paging info for the folders.
     *
     * @return object
     */
    private function buildPaging(stdClass $counts, int $page, int $limit, bool $splitFlagged)
    {
        $start = 1 + (($page - 1) * $limit);

        return (object) [
            'flagged' => (object) [
                'page' => $page,
                'prevPage' => ($page > 1)
                    ? $page - 1
                    : null,
                'total' => $counts->flagged,
                'start' => $counts->flagged
                    ? $start
                    : 0,
                'end' => $start + $limit - 1 > $counts->flagged
                    ? $counts->flagged
                    : $start + $limit - 1,
                'totalPages' => ceil($counts->flagged / $limit),
                'nextPage' => $page >= ceil($counts->flagged / $limit)
                    ? null
                    : $page + 1,
            ],
            'unflagged' => (object) [
                'page' => $page,
                'prevPage' => ($page > 1)
                    ? $page - 1
                    : null,
                'total' => $counts->unflagged,
                'start' => $counts->unflagged
                    ? $start
                    : 0,
                'end' => $start + $limit - 1 > $counts->unflagged
                    ? $counts->unflagged
                    : $start + $limit - 1,
                'totalPages' => ceil($counts->unflagged / $limit),
                'nextPage' => $page >= ceil($counts->unflagged / $limit)
                    ? null
                    : $page + 1
            ]
        ];
    }
}
