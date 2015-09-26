<?php

namespace App\Sync;

use PhpImap\Mailbox
  , App\Models\Folder as FolderModel
  , App\Models\Account as AccountModel
  , App\Models\Messages as MessagesModel;

class Messages
{
    private $account;

    function __construct( AccountModel $account )
    {
        $this->account = $account;
    }

    function getMessageIds( Mailbox $mailbox )
    {
        $mailsIds = $mailbox->searchMailBox( 'ALL' );
        print_r($mailsIds);exit;
        $mail = $mailbox->getMailsInfo([115830]);
        var_dump($mail);
        exit;
        return $mailbox->searchMailBox( 'ALL' );
    }

    function saveMessages()
    {
        return TRUE; // stub
    }

    function markDeleted()
    {
        return TRUE; // stub
    }
}