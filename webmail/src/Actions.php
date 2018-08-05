<?php

/**
 * Actions class for processing all actions in the
 * webmail client. This will update flags, copy, delete,
 * and move messages.
 */

namespace App;

use App\Actions\Copy as CopyAction;
use App\Actions\Delete as DeleteAction;
use App\Actions\MarkRead as MarkReadAction;
use App\Actions\MarkUnread as MarkUnreadAction;

class Actions
{
    private $params;

    // Actions
    const COPY = 'copy';
    const FLAG = 'flag';
    const MOVE = 'move';
    const SPAM = 'spam';
    const UNCOPY = 'uncopy';
    const UNFLAG = 'unflag';
    const UNSPAM = 'unspam';
    const DELETE = 'delete';
    const RESTORE = 'restore';
    const ARCHIVE = 'archive';
    const MARK_READ = 'mark_read';
    const MARK_UNREAD = 'mark_unread';
    const MARK_ALL_READ = 'mark_all_read';
    const MARK_ALL_UNREAD = 'mark_all_unread';
    // Selections
    const SELECT_ALL = 'all';
    const SELECT_NONE = 'none';
    const SELECT_READ = 'read';
    const SELECT_UNREAD = 'unread';
    const SELECT_FLAGGED = 'starred';
    const SELECT_UNFLAGGED = 'unstarred';
    // Options
    const ALL_MESSAGES = 'all_messages';
    const TO_FOLDER_ID = 'to_folder_id';
    const FROM_FOLDER_ID = 'from_folder_id';
    // Convert these action names
    const ACTION_CONVERSIONS = [
        'Add star' => 'flag',
        'Remove star' => 'unflag',
        'Move to Inbox' => 'restore',
        'Mark as unread' => 'mark_unread',
        'Mark all as read' => 'mark_all_read',
        'Mark all as unread' => 'mark_all_unread'
    ];
    // Lookup of actions to action classes
    const ACTION_CLASSES = [
        'flag' => 'App\Actions\Flag',
        'unflag' => 'App\Actions\Unflag',
        'delete' => 'App\Actions\Delete',
        'restore' => 'App\Actions\Restore',
        'archive' => 'App\Actions\Archive',
        'mark_read' => 'App\Actions\MarkRead',
        'mark_unread' => 'App\Actions\MarkUnread'
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
     */
    public function run()
    {
        $urlId = $this->param('url_id');
        $page = $this->param('page', '');
        $action = $this->param('action');
        $select = $this->param('select');
        $folderId = $this->param('folder_id');
        $messageIds = $this->param('message', []);
        $allMessageIds = $this->param('message_all', []);
        $moveTo = array_filter($this->param('move_to', []));
        $copyTo = array_filter($this->param('copy_to', []));

        // Some actions have names that need to be converted
        $action = $this->convertAction($action);

        // Prepare the options
        $options = [
            self::ALL_MESSAGES => $this->param('apply_to_all') == 1
        ];

        // If a selection was made, return to the previous page
        // with the key in the query params.
        if ($select) {
            Url::redirect('/?select='.strtolower($select));
        }

        Model::getDb()->beginTransaction();

        try {
            // If an action came in, route it to the child class
            $this->runAction($action, $messageIds, $allMessageIds, $options);
            // Copy and/or move any messages that were sent in
            $this->copyMessages($messageIds, $copyTo, $action);
            $this->moveMessages($messageIds, $moveTo, $folderId);
        }
        catch (Exception $e) {
            Model::getDb()->rollBack();

            throw $e;
        }

        Model::getDb()->commit();

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
        array $options = [])
    {
        if (self::MARK_ALL_READ === $action) {
            return (new MarkReadAction)->run($allMessageIds, $this->folders);
        }
        elseif (self::MARK_ALL_UNREAD === $action) {
            return (new MarkUnreadAction)->run($allMessageIds, $this->folders);
        }
        elseif (array_key_exists($action, self::ACTION_CLASSES)) {
            $class = self::ACTION_CLASSES[$action];
            $actionHandler = new $class;
            $ids = get($options, self::ALL_MESSAGES) === true
                ? $allMessageIds
                : $messageIds;
            $actionHandler->run($ids, $this->folders, $options);
        }
    }

    private function convertAction($action)
    {
        return str_replace(
            array_keys(self::ACTION_CONVERSIONS),
            array_values(self::ACTION_CONVERSIONS),
            $action);
    }

    /**
     * Copy messages to a set of folders. This will ignore any messages
     * (by message-id) that are already in the folders. If remove folders
     * was selected, then remove the folders from the selected messages.
     */
    private function copyMessages(array $messageIds, array $copyTo, string $action)
    {
        if (! $copyTo) {
            return;
        }

        // If remove folders was selected, then remove the folders
        // from the selected messages. Otherwise, add them.
        if ($action === self::UNCOPY) {
            $action = new DeleteAction;
            $param = self::FROM_FOLDER_ID;
        } else {
            $action = new CopyAction;
            $param = self::TO_FOLDER_ID;
        }

        foreach ($copyTo as $name) {
            $action->run($messageIds, $this->folders, [
                $param => $this->folders->findIdByName($name)
            ]);
        }
    }

    /**
     * Move messages to a set of folders. This will ignore any messages
     * (by message-id) that are already in the folders. Moving messages
     * is a copy followed by a delete of the original message.
     */
    private function moveMessages(array $messageIds, array $moveTo, int $fromFolderId)
    {
        if (! $moveTo) {
            return;
        }

        // First copy them
        $this->copyMessages($messageIds, $moveTo);

        // Then delete all of the messages
        $deleteAction = new DeleteAction;
        $deleteAction->run($messageIds, $this->folders, [
            self::FROM_FOLDER_ID => $fromFolderId
        ]);
    }

    private function deleteMessages(array $messageIds, int $fromFolderId)
    {
        $deleteAction = new DeleteAction;
        $deleteAction->run($messageIds, $this->folders, [
            self::FROM_FOLDER_ID => $fromFolderId
        ]);
    }
}
