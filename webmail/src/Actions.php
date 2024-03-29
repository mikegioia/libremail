<?php

/**
 * Actions class for processing all actions in the
 * webmail client. This will update flags, copy, delete,
 * and move messages.
 */

namespace App;

use App\Actions\Copy as CopyAction;
use App\Actions\Delete as DeleteAction;
use App\Actions\Flag as FlagAction;
use App\Actions\MarkRead as MarkReadAction;
use App\Actions\MarkUnread as MarkUnreadAction;
use App\Exceptions\NotFoundException;
use App\Model\Task as TaskModel;
use Exception;

class Actions
{
    private $folders;
    private $params;

    // Actions
    public const COPY = 'copy';
    public const FLAG = 'flag';
    public const MOVE = 'move';
    public const SPAM = 'spam';
    public const TRASH = 'trash';
    public const UNCOPY = 'uncopy';
    public const UNFLAG = 'unflag';
    public const UNSPAM = 'unspam';
    public const DELETE = 'delete';
    public const RESTORE = 'restore';
    public const ARCHIVE = 'archive';
    public const UNTRASH = 'untrash';
    public const MARK_READ = 'mark_read';
    public const MARK_UNREAD = 'mark_unread';
    public const CONVERT_DRAFT = 'convert_draft';
    public const RESTORE_DRAFT = 'restore_draft';
    public const MARK_ALL_READ = 'mark_all_read';
    public const MARK_ALL_UNREAD = 'mark_all_unread';
    public const MARK_UNREAD_FROM_HERE = 'mark_unread_from_here';
    // Selections
    public const SELECT_ALL = 'all';
    public const SELECT_NONE = 'none';
    public const SELECT_READ = 'read';
    public const SELECT_UNREAD = 'unread';
    public const SELECT_FLAGGED = 'starred';
    public const SELECT_UNFLAGGED = 'unstarred';
    // Options
    public const OUTBOX_ID = 'outbox_id';
    public const SEND_AFTER = 'send_after';
    public const ALL_MESSAGES = 'all_messages';
    public const TO_FOLDER_ID = 'to_folder_id';
    public const FROM_FOLDER_ID = 'from_folder_id';
    public const SINGLE_MESSAGE = 'single_message';
    public const OUTBOX_MESSAGE = 'outbox_message';
    // Convert these action names
    public const ACTION_CONVERSIONS = [
        'Add star' => 'flag',
        'Remove star' => 'unflag',
        'Move to Inbox' => 'restore',
        'Mark as read' => 'mark_read',
        'Mark as unread' => 'mark_unread',
        'Mark all as read' => 'mark_all_read',
        'Mark all as unread' => 'mark_all_unread'
    ];
    // Lookup of actions to action classes
    public const ACTION_CLASSES = [
        'flag' => 'App\Actions\Flag',
        'spam' => 'App\Actions\Spam',
        'trash' => 'App\Actions\Trash',
        'unflag' => 'App\Actions\Unflag',
        'unspam' => 'App\Actions\Unspam',
        'delete' => 'App\Actions\Delete',
        'restore' => 'App\Actions\Restore',
        'archive' => 'App\Actions\Archive',
        'untrash' => 'App\Actions\Untrash',
        'mark_read' => 'App\Actions\MarkRead',
        'mark_unread' => 'App\Actions\MarkUnread',
        'convert_draft' => 'App\Actions\ConvertDraft',
        'restore_draft' => 'App\Actions\RestoreDraft',
        'mark_unread_from_here' => 'App\Actions\MarkUnreadFromHere'
    ];

    public function __construct(Folders $folders, array $params)
    {
        $this->params = $params;
        $this->folders = $folders;
    }

    /**
     * Returns a param from the request.
     *
     * @param mixed $default
     *
     * @return mixed
     */
    public function param(string $key, $default = null)
    {
        return $this->params[$key] ?? $default;
    }

    /**
     * Parses the POST data and runs the requested actions.
     * This will also route the user to the next page.
     *
     * @throws Exception
     */
    public function run(): void
    {
        // Strings
        $action = $this->param('action');
        $select = $this->param('select');
        $urlId = $this->param('url_id', '');
        // Integers
        $page = (int) $this->param('page', 0);
        $folderId = (int) $this->param('folder_id', 0);
        // Arrays
        $messageIds = $this->param('message', []);
        $allMessageIds = $this->param('message_all', []);
        $moveTo = array_filter($this->param('move_to', []));
        $copyTo = array_filter($this->param('copy_to', []));
        // Some actions have names that need to be converted
        $action = $this->convertAction($action);
        // Prepare the options
        $options = [
            self::ALL_MESSAGES => 1 === (int) $this->param('apply_to_all'),
            self::SINGLE_MESSAGE => 1 === (int) $this->param('single_message')
        ];

        // If a selection was made, return to the previous page
        // with the key in the query params.
        if ($select) {
            Url::redirectRaw(
                OUTBOX === $urlId
                    ? Url::outbox()
                    : Url::folder($folderId),
                ['select' => strtolower($select)]
            );
        }

        Model::getDb()->beginTransaction();

        try {
            // If an action came in, route it to the child class
            $this->runAction($action, $messageIds, $allMessageIds, $options);
            // Copy and/or move any messages that were sent in
            $this->copyMessages($messageIds, $copyTo, $action);
            $this->moveMessages($messageIds, $moveTo, $folderId);
        } catch (NotFoundException $e) {
            // Ignore these
        } catch (Exception $e) {
            Model::getDb()->rollBack();

            throw $e;
        }

        Model::getDb()->commit();

        // Set an alert message
        $count = true === $options[self::ALL_MESSAGES]
            ? count($allMessageIds)
            : count($messageIds);
        $this->setResponseMessage($action, $count, array_merge($copyTo, $moveTo));

        // If we got here, redirect
        Url::actionRedirect($urlId, $folderId, $page, $action);
    }

    /**
     * Processes a requested action.
     *
     * @throws Exception
     */
    public function runAction(
        string $action,
        array $messageIds,
        array $allMessageIds,
        array $options = []
    ): void {
        if (self::MARK_ALL_READ === $action) {
            (new MarkReadAction())->run($allMessageIds, $this->folders);
        } elseif (self::MARK_ALL_UNREAD === $action) {
            (new MarkUnreadAction())->run($allMessageIds, $this->folders);
        } elseif (self::FLAG === $action) {
            (new FlagAction())->run(
                [end($allMessageIds)],
                $this->folders,
                $options
            );
        } elseif (array_key_exists($action, self::ACTION_CLASSES)) {
            $class = self::ACTION_CLASSES[$action];
            $actionHandler = new $class;
            $ids = true === ($options[self::ALL_MESSAGES] ?? false)
                ? $allMessageIds
                : $messageIds;
            $actionHandler->run($ids, $this->folders, $options);
        }
    }

    private function convertAction($action): string
    {
        return str_replace(
            array_keys(self::ACTION_CONVERSIONS),
            array_values(self::ACTION_CONVERSIONS),
            $action
        );
    }

    /**
     * Copy messages to a set of folders. This will ignore any messages
     * (by message-id) that are already in the folders. If remove folders
     * was selected, then remove the folders from the selected messages.
     */
    private function copyMessages(array $messageIds, array $copyTo, string $action): void
    {
        if (! $copyTo) {
            return;
        } elseif (self::UNCOPY === $action) {
            $action = new DeleteAction;
            $param = self::FROM_FOLDER_ID;
        } elseif (self::COPY === $action) {
            $action = new CopyAction;
            $param = self::TO_FOLDER_ID;
        } else {
            return;
        }

        foreach ($copyTo as $nameOrId) {
            $action->run($messageIds, $this->folders, [
                $param => is_numeric($nameOrId)
                    ? $nameOrId
                    : $this->folders->findIdByName($nameOrId)
            ]);
        }
    }

    /**
     * Move messages to a set of folders. This will ignore any messages
     * (by message-id) that are already in the folders. Moving messages
     * is a copy followed by a delete of the original message.
     */
    private function moveMessages(array $messageIds, array $moveTo, int $fromFolderId): void
    {
        if (! $moveTo) {
            return;
        }

        // First copy them
        $this->copyMessages($messageIds, $moveTo, self::COPY);

        // Then delete all of the messages
        $this->deleteMessages($messageIds, $fromFolderId);
    }

    private function deleteMessages(array $messageIds, int $fromFolderId): void
    {
        $deleteAction = new DeleteAction();
        $deleteAction->run($messageIds, $this->folders, [
            self::FROM_FOLDER_ID => $fromFolderId
        ]);
    }

    /**
     * Writes a response message to the session for the next
     * page to render.
     */
    private function setResponseMessage(
        string $action,
        int $count,
        array $folders
    ): void {
        $message = null;
        $nounUpper = 'Conversation';
        $nounPlural = 'conversations';
        $folderName = count($folders) > 1
            ? count($folders).' folders'
            : implode(', ', $folders);

        if (self::MARK_ALL_READ === $action
            || self::MARK_READ === $action
        ) {
            $message = 'marked as read';
        } elseif (self::MARK_ALL_UNREAD === $action
            || self::MARK_UNREAD === $action
            || self::MARK_UNREAD_FROM_HERE === $action
        ) {
            $message = 'marked as unread';
        } elseif (self::COPY === $action && $folderName) {
            $message = "copied to {$folderName}";
        } elseif (self::UNCOPY === $action && $folderName) {
            $message = "removed from {$folderName}";
        } elseif (self::MOVE === $action && $folderName) {
            $message = "moved to {$folderName}";
        } elseif (self::FLAG === $action) {
            $message = 'starred';
        } elseif (self::UNFLAG === $action) {
            $message = 'unstarred';
        } elseif (self::SPAM === $action) {
            $message = 'flagged as spam';
        } elseif (self::UNSPAM === $action) {
            $message = 'un-flagged as spam';
        } elseif (self::DELETE === $action) {
            $message = 'deleted';
        } elseif (self::RESTORE === $action) {
            $message = 'removed from trash';
        } elseif (self::ARCHIVE === $action) {
            $message = 'archived';
        } elseif (self::TRASH === $action) {
            $message = 'moved to trash';
        } elseif (self::UNTRASH === $action) {
            $message = 'removed from trash';
        } elseif (self::CONVERT_DRAFT === $action) {
            $nounUpper = 'Draft';
            $nounPlural = 'drafts';
            $message = 'imported to outbox';
        } elseif (self::RESTORE_DRAFT === $action) {
            $nounUpper = 'Message';
            $nounPlural = 'messages';
            $message = 'converted back to draft';
        }

        if (! $message) {
            return;
        }

        $message = sprintf(
            "%s $message.",
            1 === $count
                ? $nounUpper
                : $count.' '.$nounPlural
        );

        Session::alert($message, TaskModel::getBatchId());
    }
}
