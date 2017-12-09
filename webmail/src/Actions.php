<?php

/**
 * Actions class for processing all actions in the
 * webmail client. This will update flags, copy, delete,
 * and move messages.
 */

namespace App;

use App\Url;
use App\Actions\Flag as FlagAction;
use App\Actions\Unflag as UnflagAction;
use App\Actions\Delete as DeleteAction;
use App\Actions\Restore as RestoreAction;
use App\Actions\Archive as ArchiveAction;
use App\Actions\MarkRead as MarkReadAction;
use App\Actions\MarkUnread as MarkUnreadAction;

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
    // Convert these action names
    const ACTION_CONVERSIONS = [
        'Add star' => 'flag',
        'Remove star' => 'unflag',
        'Mark as unread' => 'mark_unread',
        'Mark all as read' => 'mark_all_read'
    ];

    public function __construct()
    {
        $this->params = $_POST + $_GET;
    }

    /**
     * Returns a param from the request.
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public function param( $key, $default = NULL )
    {
        return ( isset( $this->params[ $key ] ) )
            ? $this->params[ $key ]
            : $default;
    }

    /**
     * Parses the POST data and runs the requested actions.
     * This will also route the user to the next page.
     */
    public function run()
    {
        $action = $this->param( 'action' );
        $select = $this->param( 'select' );
        $messageIds = $this->param( 'message', [] );
        $allMessageIds = $this->param( 'message_all', [] );
        $moveTo = array_filter( $this->param( 'move_to', [] ) );
        $copyTo = array_filter( $this->param( 'copy_to', [] ) );

        // Some actions have names that need to be converted
        $action = $this->convertAction( $action );

        // If a selection was made, return to the previous page
        // with the key in the query params.
        if ( $select ) {
            Url::redirect( '/?select='. strtolower( $select ) );
        }

        // If an action came in, route it to the child class
        $this->handleAction( $action, $messageIds, $allMessageIds );

        // If we got here, redirect
        Url::redirect( '/' );
    }

    /**
     * Processes a requested action.
     * @param string $action
     * @param array $messageIds
     * @param array $allMessageIds
     */
    public function handleAction( $action, array $messageIds, array $allMessageIds )
    {
        switch ( $action ) {
            case self::FLAG:
                (new FlagAction)->run( $messageIds );
                break;
            case self::UNFLAG:
                (new UnflagAction)->run( $messageIds );
                break;
            case self::DELETE:
                (new DeleteAction)->run( $messageIds );
                break;
            case self::RESTORE:
                (new RestoreAction)->run( $messageIds );
                break;
            case self::ARCHIVE:
                (new ArchiveAction)->run( $messageIds );
                break;
            case self::MARK_READ:
                (new MarkReadAction)->run( $messageIds );
                break;
            case self::MARK_UNREAD:
                (new MarkUnreadAction)->run( $messageIds );
                break;
            case self::MARK_ALL_READ:
                (new MarkReadAction)->run( $allMessageIds );
                break;
        }
    }

    private function convertAction( $action )
    {
        return str_replace(
            array_keys( self::ACTION_CONVERSIONS ),
            array_values( self::ACTION_CONVERSIONS ),
            $action );
    }
}