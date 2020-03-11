<?php

namespace App\Enum;

class FolderSyncStatus {

    const __default = self::NotSynced;

    /**
     * Initial state, when folder just added to database and syncing process has never processed.
     */
    const NotSynced = 'not_synced';

    /**
     * This folder is syncing now.
     */
    const Syncing = 'syncing';

    /**
     * This folder is syncing now, and we received one or more request for resync after syncing process started.
     * Example: syncing process started and user delete / change flag of message, which already synced.
     * We can not miss this event and resyncing needed.
     */
    const SyncingNeedResync = 'syncing_need_resync';

    /**
     * Sync process completed and there is no new events received from imap server ("mail" / "update" / "purge").
     */
    const Synced = 'synced';

    /**
     * There was some error, during sync process.
     */
    const Error = 'error';

    protected static $choices = [
        self::NotSynced  => 'Not synced',
        self::Syncing   => 'Syncing',
        self::SyncingNeedResync  => 'Syncing but need resync',
        self::Synced => 'Synced',
        self::Error => 'Error'
    ];


    public static function getValues(): array
    {
        return \array_keys(static::$choices);
    }
}