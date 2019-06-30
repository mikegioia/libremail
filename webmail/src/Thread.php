<?php

namespace App;

use App\Model\Message;
use Misd\Linkify\Linkify;
use Zend\Escaper\Escaper;
use App\Exceptions\NotFoundException;

class Thread
{
    private $thread;
    private $folders;
    private $messages;
    private $threadId;
    private $folderId;
    private $folderIds;
    private $accountId;
    private $messageCount;
    private $unreadIds = [];
    private $messageIds = [];
    private $threadIndex = [];
    private $threadFolders = [];
    private $threadFolderIds = [];

    const UTF8 = 'utf-8';
    const SNIPPET_LENGTH = 160;
    const INDEX_GROUP = 'group';
    const INDEX_MESSAGE = 'message';
    const EMAIL_REGEX = '@(http)?(s)?(://)?(([a-zA-Z])([-\w]+\.)+([^\s\.]+[^\s]*)+[^,.\s])@';

    /**
     * @param bool $load If true, loads the thread data from SQL. This
     *   will throw an exception if no thread is found.
     */
    public function __construct(
        Folders $folders,
        int $threadId,
        int $folderId,
        int $accountId,
        bool $load = true
    ) {
        $this->folders = $folders;
        $this->threadId = $threadId;
        $this->folderId = $folderId;
        $this->accountId = $accountId;

        if ($load) {
            $this->load();
        }
    }

    public static function constructFromMessage(Message $message, Folders $folders)
    {
        return new static(
            $folders,
            $message->thread_id,
            $message->folder_id,
            $message->account_id
        );
    }

    /**
     * Load the thread data.
     *
     * @throws NotFoundException
     */
    public function load()
    {
        $this->messages = [];
        $this->unreadIds = [];
        $this->threadFolderIds = [];

        $allMessages = (new Message)->getThread(
            $this->accountId,
            $this->threadId,
            $this->folders->getSkipIds($this->folderId),
            $this->folders->getRestrictIds($this->folderId)
        );

        if (! $allMessages) {
            throw new NotFoundException;
        }

        $this->updateMessages($allMessages);
        $this->buildThreadIndex();
        $this->message = reset($this->messages);

        foreach ($this->folders->get() as $folder) {
            if (in_array($folder->id, $this->threadFolderIds)
                && ! $folder->is_mailbox
            ) {
                $this->threadFolders[] = $folder;
            }
        }
    }

    public function get()
    {
        return $this->message;
    }

    public function getSubject()
    {
        return $this->message->subject;
    }

    public function getMessages()
    {
        return $this->messages;
    }

    public function getFolders()
    {
        return $this->threadFolders;
    }

    public function getFolderIds()
    {
        return $this->threadFolderIds;
    }

    public function getMessageCount()
    {
        return $this->messageCount;
    }

    public function getThreadIndex()
    {
        return $this->threadIndex;
    }

    public function getThreadId()
    {
        return $this->threadId;
    }

    public function getMessageIds()
    {
        return $this->messageIds;
    }

    public function isUnread(int $id)
    {
        return in_array($id, $this->unreadIds);
    }

    public function isOutboxMessage()
    {
        foreach ($this->messages as $message) {
            if ($message->outbox_id) {
                return true;
            }
        }

        return false;
    }

    /**
     * Adds additional fields to each message.
     */
    private function updateMessages(array $allMessages)
    {
        $messageIds = [];
        $escaper = new Escaper(self::UTF8);

        foreach ($allMessages as $message) {
            // Remove all duplicate messages (message-id)
            if (in_array($message->message_id, $messageIds)) {
                $this->threadFolderIds[] = $message->folder_id;
                continue;
            }

            $this->messages[] = $message;
            $this->threadFolderIds[] = $message->folder_id;
            $messageIds[] = $message->message_id;

            if (1 != $message->seen) {
                $this->unreadIds[] = $message->id;
            }

            $this->setTo($message);
            $this->setFrom($message);
            $this->setDate($message);
            $this->setAvatar($message);
            $this->setContent($message);
            $this->setSnippet($message, $escaper);
        }

        $this->messageCount = count($this->messages);
        $this->threadFolderIds = array_unique($this->threadFolderIds);
    }

    /**
     * Adds two new properties, from_name and from_email.
     */
    private function setFrom(Message &$message)
    {
        list(
            $message->from_name,
            $message->from_email
        ) = $this->getNameParts($message->from);
    }

    /**
     * Prepares a shortened version of the to addresses and
     * names. This is added as a new property, to_names.
     */
    private function setTo(Message &$message)
    {
        $to = [];
        $names = explode(',', $message->to);

        foreach ($names as $string) {
            list($name, $email) = $this->getNameParts($string);
            $to[] = $name;
        }

        $message->to_names = implode(', ', $to);
    }

    /**
     * Adds the property date_string to display a formatted
     * date for the message, and a timestamp property for use
     * in calls to date().
     */
    private function setDate(Message &$message)
    {
        $now = time();
        $startOfToday = strtotime('today');
        $timestamp = strtotime($message->date_recv);

        $message->timestamp = $timestamp;
        $message->datetime_string = date('j F Y H:i', $timestamp);

        // Show the time if it's today
        if ($timestamp >= $startOfToday) {
            $message->date_string = date('H:i', $timestamp);
        } else {
            // If the year is different and older than 6 months,
            // show the full date
            if (date('Y', $now) !== date('Y', $timestamp)
                && $now - $timestamp >= 15552000
            ) {
                // @TODO MM/DD/YYYY or DD/MM/YYYY user setting
                $message->date_string = date('j/m/Y', $timestamp);
            } else {
                // Catch-all is like "8 Jul"
                $message->date_string = date('j M', $timestamp);
            }
        }
    }

    private function setAvatar(Message &$message)
    {
        $message->avatar_url = sprintf(
            'https://www.gravatar.com/avatar/%s?d=identicon',
            md5(strtolower(trim($message->from_email)))
        );
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
     * Parses the text/plain content into escaped HTML.
     */
    private function setContent(Message &$message)
    {
        // Cleanse it
        $body = htmlspecialchars($message->text_plain, ENT_QUOTES, 'UTF-8');

        // Convert any links
        $linkify = new Linkify;
        $body = $linkify->processEmails($body);
        $body = $linkify->processUrls($body, [
            'attr' => [
                'target' => '_blank'
            ]
        ]);

        $message->body = nl2br(trim($body));
    }

    /**
     * Builds an index of which messages to display, and which
     * to collapse when rendering the thread. This also builds
     * the messageIds array.
     */
    private function buildThreadIndex()
    {
        $group = [];
        $this->threadIndex = [];
        $count = $this->messageCount;
        // Adds a group of collapsed messages to the index
        $closeGroup = function (array &$group) {
            if (count($group) > 0) {
                $this->threadIndex[] = (object) [
                    'messages' => $group,
                    'count' => count($group),
                    'type' => self::INDEX_GROUP
                ];
            }

            $group = [];
        };
        // Adds an individual message
        $addItem = function (Message $message, bool $open, bool $current = false) {
            $this->threadIndex[] = (object) [
                'open' => $open,
                'message' => $message,
                'current' => $current,
                'type' => self::INDEX_MESSAGE
            ];
        };

        foreach ($this->messages as $i => $message) {
            // The first and penultimate messages always display,
            // but open it only if they're unread
            if (0 === $i || $i === $count - 2) {
                $closeGroup($group);
                $addItem($message, 1 != $message->seen || 1 === $count);
            } elseif (1 != $message->seen || $i >= $count - 1) {
                // If it's unread or the last message in the thread,
                // then display it opened
                $closeGroup($group);
                $addItem($message, true, $i >= $count - 1);
            } else {
                $group[] = $message;
            }

            $this->messageIds[] = $message->id;
        }

        $closeGroup($group);

        $this->messageIds = array_values(array_unique($this->messageIds));
    }

    /**
     * Takes a string like "John D. <john@abc.org>" and returns
     * the name and email broken out.
     *
     * @return [string $name, string $email]
     */
    private function getNameParts(string $nameString)
    {
        $parts = explode('<', $nameString, 2);
        $count = count($parts);

        if (1 === $count) {
            $name = trim($parts[0], ' >');
            $email = '';
        } else {
            $name = trim($parts[0]);
            $email = '<'.trim($parts[1], ' <>').'>';
        }

        return [$name, $email];
    }
}
