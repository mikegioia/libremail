<?php

namespace App;

use App\Model\Meta;
use App\Model\Outbox;
use App\Model\Account;
use App\Model\Contact;
use App\Model\Message;
use App\Model\Settings;
use App\Exceptions\ClientException;
use App\Exceptions\ServerException;
use App\Exceptions\NotFoundException;
use App\Exceptions\ValidationException;
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
        session_start();

        (new Actions(
            new Folders($this->account, getConfig('colors')),
            $_POST + $_GET
        ))->run();
    }

    public function action()
    {
        session_start();
        Session::validateToken();

        (new Actions(
            new Folders($this->account, getConfig('colors')),
            $_GET + ['url_id' => THREAD]
        ))->run();
    }

    public function undo(int $batchId)
    {
        session_start();
        (new Rollback)->run($batchId);
    }

    public function getStar(string $type, string $theme, int $id, string $state)
    {
        header('Content-Type: text/html');
        header('Cache-Control: max-age=86400'); // one day

        (new View)->render('/star', [
            'id' => $id,
            'type' => $type,
            'theme' => $theme,
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
            ], [
                Url::postParam('id', 0)
            ], [
                Message::ALL_SIBLINGS => MAILBOX === $type
            ]
        );

        (new View)->render('/star', [
            'id' => Url::postParam('id', 0),
            'type' => Url::postParam('type'),
            'theme' => Url::postParam('theme'),
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
        $thread = new Thread($folders, $threadId, $folderId, $this->account->id);

        // Mark this thread as read
        (new MarkReadAction)->run([$threadId], $folders);

        // Re-compute the un-read totals, as this may be changed now
        // Render the message thread
        $this->page('thread', [
            'thread' => $thread,
            'folders' => $folders,
            'folderId' => $folderId,
            'totals' => (new Message)->getSizeCounts($this->account->id)
        ]);
    }

    public function original(int $messageId)
    {
        header('Content-Type: text/plain');

        // Load the message, this will throw an exception if not found
        $message = (new Message)->getById($messageId, true);

        (new View)->raw($message->getOriginal());
    }

    public function account()
    {
        $this->page('account');
    }

    public function updateAccount()
    {
        session_start();

        $name = $_POST['name'] ?? '';
        $port = $_POST['port'] ?? 993;
        $email = $_POST['email'] ?? '';
        $password = $_POST['password'] ?? '';
        $host = $_POST['host'] ?? 'imap.gmail.com';

        try {
            (new Imap)->connect($email, $password, $host, $port);

            // Save the new account info
            $this->account->update($email, $password, $name, $host, $port);

            // @TODO Restart the sync process via pkill
            Session::notify(
                'Your account configuration has been updated! You will'.
                'most likely need to restart the sync process.',
                Session::SUCCESS
            );
        } catch (ServerException $e) {
            Session::notify(
                'There was a problem with your account configuration. '.
                $e->getMessage(),
                Session::ERROR
            );
        }

        Url::redirectBack('/account');
    }

    public function settings()
    {
        $this->page('settings');
    }

    public function updateSettings()
    {
        session_start();
        Meta::update($_POST);
        Session::notify('Your preferences have been saved!', Session::SUCCESS);
        Url::redirectBack('/settings');
    }

    /**
     * @throws NotFoundException Sends back a 404 if message
     *   is sent or if it doesn't exist
     */
    public function compose(int $outboxId = null)
    {
        $message = new Outbox($this->account);

        if ($outboxId) {
            $message = $message->getById($outboxId);

            if (! $message->exists() || $message->sent) {
                throw new NotFoundException;
            }
        }

        $this->page('compose', [
            'previewing' => false,
            'message' => $message,
            'contacts' => Contact::getByAccount($this->account->id)
        ]);
    }

    public function preview(int $outboxId)
    {
        $message = (new Outbox($this->account))->getById($outboxId);

        if (! $message->exists() || $message->sent) {
            throw new NotFoundException;
        }
        exit('preview');
    }

    public function outbox()
    {
        $this->page('outbox', [
            'folderId' => OUTBOX,
            'messages' => []
        ]);
    }

    public function deleteDraft()
    {
        (new Outbox($this->account))
            ->getById(intval($_POST['id'] ?? 0))
            ->delete();

        Session::notify('Draft message deleted.', Session::SUCCESS);
        Url::redirect('/outbox');
    }

    public function send()
    {
        session_start();

        try {
            $outbox = new Outbox($this->account);
            $outbox->setPostData($_POST);
            $outbox->save();

            if ($outbox->draft) {
                Session::notify('Draft message saved.', Session::SUCCESS);
                Url::redirectRaw(Url::make('/compose/%s', $outbox->id));
            } else {
                Url::redirectRaw(Url::make('/preview/%s', $outbox->id));
            }
        } catch (ValidationException $e) {
            // Store the POST data back in the session and set the
            // errors in the session for the page to display
            Session::formErrors($e->getErrors());
            Session::formData($_POST);

            if (isset($outbox->id) && $outbox->exists()) {
                Url::redirectRaw(Url::make('/compose/%s', $outbox->id));
            } else {
                Url::redirectRaw('/compose');
            }
        }
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
                Message::ONLY_FLAGGED => STARRED === $id,
                Message::INCLUDE_DELETED => $folderId === $folders->getTrashId()
            ]);

        $view->htmlHeaders();

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
            'alert' => Session::get(Session::ALERT)
        ]);
    }

    private function page(string $viewPath, array $data = [])
    {
        $view = new View;

        if (! isset($data['folders'])) {
            $data['folders'] = new Folders($this->account, getConfig('colors'));
        }

        $view->htmlHeaders();
        $view->render(
            $viewPath,
            array_merge([
                'view' => $view,
                'meta' => Meta::getAll(),
                'account' => $this->account,
                'alert' => Session::get(Session::ALERT),
                'formData' => Session::get(Session::FORM_DATA, []),
                'formErrors' => Session::get(Session::FORM_ERRORS, []),
                'notifications' => Session::get(Session::NOTIFICATIONS, [])
            ], $data)
        );
    }
}
