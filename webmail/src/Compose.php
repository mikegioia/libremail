<?php

namespace App;

use Exception;
use App\Model\Outbox;
use App\Model\Account;
use App\Model\Message;
use App\Actions\Queue as QueueAction;
use App\Exceptions\ValidationException;

class Compose
{
    /**
     * @var Account
     */
    private $account;

    /**
     * @var Folders
     */
    private $folders;

    public function __construct(Account $account)
    {
        $this->account = $account;
        $this->folders = new Folders($this->account, []);
    }

    public function draft(bool $isPreview)
    {
        $draftId = $this->folders->getDraftsId();

        try {
            Model::getDb()->beginTransaction();

            $outbox = new Outbox($this->account);
            $outbox->setPostData($_POST)->save($isPreview);

            // Update the draft if it exists, and optionally create
            // a new one if this message is a draft
            (new Message)->createOrUpdateDraft($outbox, $draftId);
            Session::notify('Draft message saved.', Session::SUCCESS);

            Model::getDb()->commit();

            if ($isPreview && ! Session::hasErrors()) {
                Url::redirectRaw(Url::preview($outbox->id));
            } else {
                Url::redirectRaw(Url::edit($outbox->id));
            }
        } catch (ValidationException $e) {
            Model::getDb()->rollback();
            // Store the POST data back in the session and set the
            // errors in the session for the page to display
            Session::formErrors($e->getErrors());
            Session::formData($_POST);

            if (isset($outbox->id) && $outbox->exists()) {
                Url::redirectRaw(Url::edit($outbox->id));
            } else {
                Url::redirectRaw(Url::compose());
            }
        } catch (Exception $e) {
            Model::getDb()->rollback();

            throw $e;
        }
    }

    public function send(int $id, bool $edit)
    {
        $outboxMessage = (new Outbox($this->account))->getById($id);

        if (! $outboxMessage->exists()) {
            Session::notify('Message not found!', Session::ERROR);
            Url::redirectRaw(Url::compose());
        }

        if (true === $edit) {
            Url::redirectRaw(Url::edit($id));
        }

        if (! $outboxMessage->isDraft()) {
            Session::notify('Message not available to send!', Session::ERROR);
            Url::redirectBack('/compose');
        }

        $draftMessage = (new Message)->getByOutboxId(
            $outboxMessage->id,
            $this->folders->getDraftsId()
        );

        if (! $draftMessage->exists()) {
            Session::notify(
                'Draft message not found! This is a grave error and '.
                'means you will need to delete this message and create '.
                'a new one!',
                Session::ERROR);
            Url::redirectRaw(Url::compose());
        }

        if (true === $queue) {
            Model::getDb()->beginTransaction();

            try {
                (new QueueAction)->run(
                    [$draftMessage->id],
                    $this->folders,
                    [Actions::OUTBOX_MESSAGE => $outboxMessage]
                );
            } catch (Exception $e) {
                Model::getDb()->rollback();

                throw $e;
            }

            Model::getDb()->commit();
            Session::notify(
                'Your message was queued for delivery! You still have '.
                'time to review it and make any changes.');
            Url::redirectRaw(Url::outbox());
        }

        Session::notify('No message delivery type specified!', Session::ERROR);
        Url::redirectRaw(Url::preview(Url::postParam('id')));
    }

    /**
     * @throws NotFoundException
     */
    public function reply(int $parentId)
    {
        $parent = (new Message)->getById($parentId);
        $draftId = $this->folders->getDraftsId();
        $data = $this->getReplyData($parent, $_POST);

        try {
            Model::getDb()->beginTransaction();

            $outbox = new Outbox($this->account, $parent);
            $outbox->setPostData($data)->save(true);

            // Update the draft if it exists, and optionally create
            // a new one if this message is a draft
            (new Message)->createOrUpdateDraft($outbox, $draftId, $parent);
            Session::notify('Draft message saved.', Session::SUCCESS);

            Model::getDb()->commit();

            if (! Session::hasErrors()) {
                Url::redirectRaw(Url::preview($outbox->id));
            } else {
                Url::redirectRaw(Url::edit($outbox->id));
            }
        } catch (ValidationException $e) {
            Model::getDb()->rollback();
            // Store the POST data back in the session and set the
            // errors in the session for the page to display
            Session::formErrors($e->getErrors());
            Session::formData($data);

            if (isset($outbox->id) && $outbox->exists()) {
                Url::redirectRaw(Url::edit($outbox->id));
            } else {
                Url::redirectRaw(Url::compose());
            }
        } catch (Exception $e) {
            Model::getDb()->rollback();

            throw $e;
        }
    }

    private function getReplyData(Message $parent, array $data)
    {
        $email = $this->account->email;
        $to = $parent->getReplyAllAddresses(false, $email, ['to', 'from'], true);
        $cc = $parent->getReplyAllAddresses(false, $email, ['cc'], true);

        // Add "Re:" to the subject but only if there isn't one
        $subject = 0 === strpos(trim($parent->subject), 're:')
            ? $parent->subject
            : 'Re: '.$parent->subject;

        return [
            'to' => $to,
            'cc' => $cc,
            'subject' => $subject,
            'text_plain' => $data['reply_all'][$parent->id] ?? ''
        ];
    }
}
