<?php

namespace App;

use App\Model\Meta;
use App\Model\Account;
use App\Model\Message;
use App\Exceptions\ClientException;
use App\Esception\NotFoundException;
use App\Actions\MarkRead as MarkReadAction;

class Controller
{
    private $account;

    public function __construct(Account $account)
    {
        $this->account = $account;
    }

    public function inbox()
    {
        $this->mailbox(INBOX);
    }

    public function folder(int $id)
    {
        $this->mailbox($id);
    }

    public function folderPage(int $id, int $page)
    {
        $this->mailbox($id, $page);
    }

    public function starred(string $page)
    {
        $this->mailbox(STARRED, $page);
    }

    public function update()
    {
        $colors = getConfig('colors');
        $folders = new Folders($this->account, $colors);
        $actions = new Actions($folders, $_POST + $_GET);

        session_start();
        $actions->run();
    }

    public function undo(int $batchId)
    {
        session_start();
        (new Rollback)->run($batchId);
    }

    public function getStar(string $type, int $id, string $state)
    {
        header('Content-Type: text/html');
        header('Cache-Control: max-age=86400'); // one day

        (new View)->render('/star', [
            'id' => $id,
            'type' => $type,
            'flagged' => 'on' === $state
        ]);
    }

    public function setStar()
    {
        $type = Url::postParam('type', MAILBOX);
        $folders = new Folders($this->account, []);
        $actions = new Actions($folders, $_POST + $_GET);

        $actions->runAction(
            'on' === Url::postParam('state', 'on')
                ? Actions::FLAG
                : Actions::UNFLAG,
            [
                Url::postParam('id', 0)
            ], [], [
                Message::ALL_SIBLINGS => MAILBOX === $type
            ]);

        (new View)->render('/star', [
            'id' => Url::postParam('id', 0),
            'type' => Url::postParam('type'),
            'flagged' => 'on' === Url::postParam('state', 'on')
        ]);
    }

    public function thread(int $folderId, int $threadId)
    {
        // Set up libraries
        $view = new View;
        $colors = getConfig('colors');
        $select = Url::getParam('select');
        $folders = new Folders($this->account, $colors);
        // Load the thread object, this will throw an exception if
        // the thread is not found. Do this BEFORE we mark as read
        // so that we know which message to take the user to.
        $thread = new Thread($this->account, $folders, $threadId);

        // Mark this thread as read
        (new MarkReadAction)->run([$threadId], $folders);

        // Re-compute the un-read totals, as this may be changed now
        // Render the message thread
        session_start();
        $view->render('thread', [
            'view' => $view,
            'thread' => $thread,
            'folders' => $folders,
            'folderId' => $folderId,
            'meta' => Meta::getAll(),
            'alert' => Session::get('alert'),
            'totals' => (new Message)->getSizeCounts($this->account->id)
        ]);
    }

    public function original(int $messageId)
    {
        header('Content-Type: text/plain');

        // Load the message, this will throw an exception if not found
        $message = (new Message)->getById($messageId, true);

        (new View)->clean($message->getOriginal());
    }

    public function error404()
    {
        throw new NotFoundException;
    }

    /**
     * Helper function to render a mailbox page.
     */
    private function mailbox($id, $page = 1, $limit = 25)
    {
        // Set up libraries
        $view = new View;
        $meta = Meta::getAll();
        $colors = getConfig('colors');
        $select = Url::getParam('select');
        $folders = new Folders($this->account, $colors);
        $messages = new Messages($this->account, $folders);
        $folderId = INBOX === $id || STARRED === $id
            ? $folders->getInboxId()
            : $id;
        $folder = $folders->getById($folderId);

        if (! $folder) {
            throw new ClientException("Folder #$id not found!");
        }

        // Get the message data
        list($flagged, $unflagged, $paging, $totals) = $messages->getThreads(
            $folderId,
            $page,
            $limit, [
                Message::SPLIT_FLAGGED => INBOX === $id,
                Message::ONLY_FLAGGED => STARRED === $id
            ]);

        session_start();
        header('Content-Type: text/html');
        header('Cache-Control: private, max-age=0, no-cache, no-store');

        // Render the inbox
        $view->render('mailbox', [
            'urlId' => $id,
            'view' => $view,
            'page' => $page,
            'meta' => $meta,
            'paging' => $paging,
            'select' => $select,
            'totals' => $totals,
            'flagged' => $flagged,
            'folders' => $folders,
            'folderId' => $folderId,
            'unflagged' => $unflagged,
            'showPaging' => INBOX !== $id,
            'mainHeading' => INBOX === $id
                ? 'Everything else'
                : $folder->name,
            'alert' => Session::get('alert')
        ]);
    }
}
