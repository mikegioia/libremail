<?php

namespace App;

use App\Actions\MarkRead as MarkReadAction;
use App\Exceptions\ClientException;
use App\Exceptions\NotFoundException;
use App\Exceptions\ServerException;
use App\Model\Account;
use App\Model\Contact;
use App\Model\Message;
use App\Model\Meta;
use App\Model\Outbox;

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

    public function outbox()
    {
        $select = Url::getParam('select');

        $this->page('outbox', [
            'select' => $select,
            'messages' => (new Outbox($this->account))->getActive(),
            'totals' => (new Message)->getSizeCounts($this->account->id)
        ]);
    }

    public function starred(string $page)
    {
        $this->mailbox(STARRED, $page);
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

    public function closeJsAlert()
    {
        session_start();
        Session::flag(Session::FLAG_HIDE_JS_ALERT, true);
        Url::redirectBack();
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

    public function setup()
    {
        $view = new View;

        $view->htmlHeaders();
        $view->render('setup', [
            'view' => $view,
            'account' => new Account(Session::get(Session::FORM_DATA)),
            'notifications' => Session::get(Session::NOTIFICATIONS, [])
        ]);
    }

    public function createAccount()
    {
        session_start();

        $name = Url::postParam('name', '');
        $port = Url::postParam('port', 993);
        $email = Url::postParam('email', '');
        $password = Url::postParam('password', '');
        $host = Url::postParam('host', 'imap.gmail.com');

        list($smtpHost, $smtpPort) = Config::getSmtpSettings($host);

        $this->account->setData([
            'name' => $name,
            'email' => $email,
            'password' => $password,
            'imap_host' => $host,
            'imap_port' => $port,
            'smtp_host' => $smtpHost,
            'smtp_port' => $smtpPort
        ]);

        try {
            (new Imap)->connect($email, $password, $host, (int) $port);

            $this->account->create();

            Session::notify(
                'Your account configuration has been saved! You can '.
                'now start the sync process.',
                Session::SUCCESS);

            Url::redirect('/account');
            // User sent to the account page now, script halted
        } catch (ServerException $e) {
            Session::formData($this->account->getData());
            Session::notify(
                'There was a problem with your account configuration. '.
                $e->getMessage(),
                Session::ERROR
            );
        }

        Url::redirectBack('/setup');
    }

    public function updateAccount()
    {
        session_start();

        $name = Url::postParam('name', '');
        $port = Url::postParam('port', 993);
        $email = Url::postParam('email', '');
        $password = Url::postParam('password', '');
        $host = Url::postParam('host', 'imap.gmail.com');

        list($smtpHost, $smtpPort) = Config::getSmtpSettings($host);

        try {
            (new Imap)->connect($email, $password, $host, $port);

            // Save the new account info
            $this->account->update(
                $email, $password, $name,
                $host, $port, $smtpHost, $smtpPort
            );

            Session::notify(
                'Your account configuration has been updated! You will '.
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
        $outboxMessage = (new Outbox($this->account))->getById($outboxId);

        if (! $outboxMessage->exists() || $outboxMessage->sent) {
            throw new NotFoundException;
        }

        $this->page('preview', [
            'message' => $outboxMessage
        ]);
    }

    public function reply(int $parentId)
    {
        $this->replyPage($parentId, false);
    }

    public function replyAll(int $parentId)
    {
        $this->replyPage($parentId, true);
    }

    private function replyPage(int $parentId, bool $replyAll)
    {
        $parentMessage = (new Message)->getById($parentId, true, true);
        $parent = Thread::constructFromMessage(
            $parentMessage,
            new Folders($this->account, [])
        )->updateMessage($parentMessage);

        $this->page('reply', [
            'ccAddresses' => $parent->getReplyCcAddresses($this->account->email),
            'contacts' => Contact::getByAccount($this->account->id),
            'parent' => $parent,
            'replyAll' => $replyAll,
            'toAddresses' => $replyAll
                ? $parent->getReplyToAddresses($this->account->email)
                : $parent->getReplyAddress(false)
        ]);
    }

    public function deleteDraft()
    {
        session_start();

        $id = intval(Url::postParam('id'));
        $folders = new Folders($this->account, []);
        $outboxMessage = (new Outbox($this->account))->getById($id);

        // First try to delete the draft, then delete the outbox message
        if ($outboxMessage->exists()) {
            $draftMessage = (new Message)->getByOutboxId(
                $id,
                $folders->getDraftsId()
            );

            if ($draftMessage->exists()) {
                $draftMessage->softDelete(true);
            }
        }

        // Throws not found if it doesn't exist
        try {
            $outboxMessage->softDelete();
            Session::notify('Draft message deleted!', Session::SUCCESS);
        } catch (NotFoundException $e) {
            Session::notify('Draft message not found!', Session::ERROR);
        }

        Url::redirect('/');
    }

    public function draft()
    {
        session_start();

        $sendPreview = array_key_exists('send_preview', $_POST);
        $compose = new Compose($this->account);
        $compose->draft($sendPreview);
    }

    public function send()
    {
        session_start();

        $id = intval(Url::postParam('id'));
        $edit = array_key_exists('edit', $_POST);
        $queue = array_key_exists('send_outbox', $_POST);

        $compose = new Compose($this->account);
        $compose->send($id, $edit, $queue);
    }

    public function update()
    {
        session_start();

        // Quick reply or reply-all can POST here
        // Editing a message will update the session and redirect
        if (is_numeric(Url::postParam('reply_preview'))) {
            (new Compose($this->account))->reply(
                Url::postParam('reply_preview'),
                false
            );
        } elseif (is_numeric(Url::postParam('reply_all_preview'))) {
            (new Compose($this->account))->reply(
                Url::postParam('reply_all_preview')
            );
        } elseif (is_numeric(Url::postParam('reply_edit'))) {
            (new Compose($this->account))->replyEdit(
                Url::postParam('reply_edit'),
                false
            );
        } elseif (is_numeric(Url::postParam('reply_all_edit'))) {
            (new Compose($this->account))->replyEdit(
                Url::postParam('reply_all_edit'),
                true
            );
        } else {
            (new Actions(
                new Folders($this->account, getConfig('colors')),
                $_POST + $_GET
            ))->run();
        }
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

    /**
     * Search all messages for a collection of thread IDs, and then
     * render those threads in a mailbox view (with paging, etc).
     */
    public function search()
    {
        // Load params
        $query = Url::getParam('q', '');
        $sortBy = Url::getParam('s', '');
        $page = (int) Url::getParam('p', 1);
        $folderId = (int) Url::getParam('f', 0);
        // Set up libraries
        $colors = getConfig('colors');
        $select = Url::getParam('select');
        $folders = new Folders($this->account, $colors);
        $messages = new Messages($this->account, $folders);

        // Get the message data
        list($flagged, $unflagged, $paging, $totals) = $messages->getThreadsBySearch(
            $query,
            $folderId,
            max($page, 1), // disallow negatives
            25, // page limit
            $sortBy, [
                Message::ONLY_FLAGGED => false,
                Message::SPLIT_FLAGGED => false,
                Message::INCLUDE_DELETED => false
            ]);

        // Render the search page
        $this->page('mailbox', [
            'page' => $page,
            'query' => $query,
            'urlId' => SEARCH,
            'paging' => $paging,
            'select' => $select,
            'totals' => $totals,
            'showPaging' => true,
            'flagged' => $flagged,
            'folders' => $folders,
            'folderId' => $folderId,
            'unflagged' => $unflagged,
            'mainHeading' => 'Search Results',
        ]);
    }

    /**
     * Helper function to render a mailbox page.
     */
    private function mailbox(string $id, int $page = 1, int $limit = 25)
    {
        // Set up libraries
        $colors = getConfig('colors');
        $select = Url::getParam('select');
        $folders = new Folders($this->account, $colors);
        $messages = new Messages($this->account, $folders);
        $folderId = INBOX === $id || STARRED === $id
            ? $folders->getInboxId()
            : intval($id);
        $folder = $folders->getById($folderId);

        if (! $folder) {
            throw new ClientException("Folder #$id not found!");
        }

        // Get the message data
        list($flagged, $unflagged, $paging, $totals) = $messages->getThreadsByFolder(
            $folderId,
            max($page, 1), // disallow negatives
            $limit, [
                Message::SPLIT_FLAGGED => INBOX === $id,
                Message::ONLY_FLAGGED => STARRED === $id,
                Message::IS_DRAFTS => $folderId === $folders->getDraftsId(),
                Message::INCLUDE_DELETED => $folderId === $folders->getTrashId()
            ]);

        // Render the inbox
        $this->page('mailbox', [
            'urlId' => $id,
            'page' => $page,
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
                : $folder->name
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
                'notifications' => Session::get(Session::NOTIFICATIONS, []),
                'hideJsAlert' => Session::getFlag(Session::FLAG_HIDE_JS_ALERT, false)
            ], $data)
        );
    }

    public function error404()
    {
        throw new NotFoundException;
    }

    public function errorNoFolders()
    {
        View::showError(
            View::HTTP_200,
            'No Folders Found',
            'Start the sync engine to begin downloading your email. '.
            'Once the sync runs, you will be able to see your mail. ',
            [
                'showSettingsMenu' => true
            ]
        );
    }
}
