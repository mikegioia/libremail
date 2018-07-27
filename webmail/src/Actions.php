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

class Actions
{
    private $params;

    // Actions
    const FLAG = 'flag';
    const UNFLAG = 'unflag';
    const DELETE = 'delete';
    const RESTORE = 'restore';
    const ARCHIVE = 'archive';
    const MARK_READ = 'mark_read';
    const MARK_UNREAD = 'mark_unread';
    const MARK_ALL_READ = 'mark_all_read';
    // Selections
    const SELECT_ALL = 'all';
    const SELECT_NONE = 'none';
    const SELECT_READ = 'read';
    const SELECT_UNREAD = 'unread';
    const SELECT_FLAGGED = 'starred';
    const SELECT_UNFLAGGED = 'unstarred';
    // Options
    const TO_FOLDER_ID = 'to_folder_id';
    const FROM_FOLDER_ID = 'from_folder_id';
    // Convert these action names
    const ACTION_CONVERSIONS = [
        'Add star' => 'flag',
        'Remove star' => 'unflag',
        'Move to Inbox' => 'restore',
        'Mark as unread' => 'mark_unread',
        'Mark all as read' => 'mark_all_read'
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
     * @param string $key
     * @param mixed $default
     *
     * @return mixed
     */
    public function param($key, $default = null)
    {
        return (isset($this->params[$key]))
            ? $this->params[$key]
            : $default;
    }

    /**
     * Parses the POST data and runs the requested actions.
     * This will also route the user to the next page.
     */
    public function run()
    {
        $page = $this->param('page');
        $urlId = $this->param('url_id');
        $action = $this->param('action');
        $select = $this->param('select');
        $folderId = $this->param('folder_id');
        $messageIds = $this->param('message', []);
        $allMessageIds = $this->param('message_all', []);
        $moveTo = array_filter($this->param('move_to', []));
        $copyTo = array_filter($this->param('copy_to', []));

        // Some actions have names that need to be converted
        $action = $this->convertAction($action);

        // If a selection was made, return to the previous page
        // with the key in the query params.
        if ($select) {
            Url::redirect('/?select='.strtolower($select));
        }

        Model::getDb()->beginTransaction();

        try {
            // If an action came in, route it to the child class
            $this->handleAction($action, $messageIds, $allMessageIds);
            // Copy and/or move any messages that were sent in
            $this->copyMessages($messageIds, $copyTo);
            $this->moveMessages($messageIds, $moveTo, $folderId);
        }
        catch (Exception $e) {
            Model::getDb()->rollBack();

            throw $e;
        }

        Model::getDb()->commit();

        // If we got here, redirect
        Url::actionRedirect($urlId, $folderId, $page);
    }

    /**
     * Processes a requested action.
     *
     * @param string $action
     * @param array $messageIds
     * @param array $allMessageIds
     * @param array $options
     *
     * @throws Exception
     */
    public function handleAction(
        $action,
        array $messageIds,
        array $allMessageIds,
        array $options = []
    ) {
        if (self::MARK_ALL_READ === $action) {
            (new MarkReadAction)->run($allMessageIds, $this->folders);
        }
        elseif (array_key_exists($action, self::ACTION_CLASSES)) {
            $class = self::ACTION_CLASSES[$action];
            $actionHandler = new $class;
            $actionHandler->run($messageIds, $this->folders, $options);
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
     * (by message-id) that are already in the folders.
     *
     * @param array $messageIds
     * @param array $copyTo
     */
    private function copyMessages(array $messageIds, array $copyTo)
    {
        if (! $copyTo) {
            return;
        }

        $copyAction = new CopyAction;

        foreach ($copyTo as $name) {
            $copyAction->run($messageIds, $this->folders, [
                self::TO_FOLDER_ID => $this->folders->findIdByName($name)
            ]);
        }
    }

    /**
     * Move messages to a set of folders. This will ignore any messages
     * (by message-id) that are already in the folders. Moving messages
     * is a copy followed by a delete of the original message.
     *
     * @param array $messageIds
     * @param array $moveTo
     * @param int $fromFolderId
     */
    private function moveMessages(array $messageIds, array $moveTo, $fromFolderId)
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
}
