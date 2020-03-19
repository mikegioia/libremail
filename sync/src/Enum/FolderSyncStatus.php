<?php

namespace App\Enum;

class FolderSyncStatus {

    const __default = self::NOT_SYNCED;

    /**
     * Initial state, when folder just added to database and syncing process has never processed.
     */
    const NOT_SYNCED = 'not_synced';

    /**
     * This folder is syncing now.
     */
    const SYNCING = 'syncing';

    /**
     * This folder is syncing now, and we received one or more request for resync after syncing process started.
     * Example: syncing process started and user delete / change flag of message, which already synced.
     * We can not miss this event and resyncing needed.
     */
    const SYNCING_NEED_RESYNC = 'syncing_need_resync';

    /**
     * This folder is just synced, another folder of the same account is syncing now.
     * That means, that sync is running now for this account.
     * After account sync is complete we need to resync this folder, because watcher tell us, that folder is changed.
     */
    const SYNCED_NEED_RESYNC = 'synced_need_resync';

    /**
     * Sync process completed and there is no new events received from imap server ("mail" / "update" / "purge").
     */
    const SYNCED = 'synced';

    /**
     * There was some error, during sync process.
     */
    const ERROR = 'error';

    protected static $choices = [
        self::NOT_SYNCED  => 'Not synced',
        self::SYNCING   => 'Syncing',
        self::SYNCING_NEED_RESYNC  => 'Syncing but need resync',
        self::SYNCED_NEED_RESYNC  => 'Synced but need resync',
        self::SYNCED => 'Synced',
        self::ERROR => 'Error'
    ];


    public static function getValues(): array
    {
        return \array_keys(static::$choices);
    }
}