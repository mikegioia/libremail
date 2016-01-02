# LibreMail Data Format

This document outlines the structure of the data as saved by the syncing engine.
The purpose is to both give an outline for the data strutures used in all parts
of this project, as well as give you a reference for writing your own syncing
engine.

LibreMail comes with a PHP application that will download your mail through
IMAP. It saves data into SQL in the format outlined in this document. The mail
clients in this project all interact with the SQL data so if you would prefer
to use a different application that downloads email, but would still like to
use the LibreMail clients, than you must adhere to these data structures.

## Accounts

```SQL
CREATE TABLE IF NOT EXISTS `accounts` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `service` varchar(20) COLLATE utf8_unicode_ci DEFAULT NULL,
  `email` varchar(100) COLLATE utf8_unicode_ci DEFAULT NULL,
  `password` varchar(100) COLLATE utf8_unicode_ci DEFAULT NULL,
  `imap_host` varchar(50) COLLATE utf8_unicode_ci DEFAULT NULL,
  `imap_port` mediumint(5) DEFAULT NULL,
  `imap_flags` varchar(50) COLLATE utf8_unicode_ci DEFAULT NULL,
  `is_active` tinyint(1) unsigned DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
```

- `id` Unique integer identifying the account
- `service` Optional string referencing the IMAP service. For example, this
   could say 'gmail', 'outlook', 'yahoo', etc.
- `email` String containing the email address for the IMAP account
- `password` IMAP password for the account. This is stored in plain text. It's
   advised that the user stores an access key or application password.
- `imap_host` String containing the hostname for connecting to the IMAP server.
   This is usually of the form `imap.mail-server.com`.
- `imap_port` Optional integer specifying what port to use. The applications
   should default to using 993 for SSL connections.
- `imap_flags` Optional string containing additional flags for connecting to
   the IMAP server. This could be `/imap/ssl` which would be appended to the
   connection string. As of now the sync engine does not use this at all.
- `is_active` Boolean flag denoting if the account is active. The sync engine
   should skip accounts with a value of 1.
- `created_at` Timestamp denoting when the account was added to the database.

## Folders

```SQL
CREATE TABLE IF NOT EXISTS `folders` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `account_id` int(10) unsigned NULL,
  `name` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `deleted` tinyint(1) unsigned DEFAULT '0',
  `ignored` tinyint(1) unsigned DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE( `account_id`, `name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
```

- `id` Unique integer identifying the folder
- `account_id` Foreign key referencing the account from the `accounts` table.
- `name` Full global name of the folder as saved on the IMAP server. For
   example, this would be 'Accounts/Listserv/Libremail' instead of 'Libremail'.
- `deleted` Boolean flag denoting if the folder was deleted on the IMAP server.
   Deleted folders should not be synced.
- `ignored` Boolean flag denoting if the folder should be ignored from sycning
   locally. Any folder with this flag set to 1 should not have its messages
   downloaded.
- `created_at` Timestamp denoting when the account was added to the database.

## Messages

```SQL
CREATE TABLE IF NOT EXISTS `messages` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `account_id` int(10) unsigned NOT NULL,
  `folder_id` int(10) unsigned NOT NULL,
  `unique_id` int(10) unsigned DEFAULT NULL,
  `date_str` varchar(100) COLLATE utf8_unicode_ci DEFAULT NULL,
  `charset` varchar(100) COLLATE utf8_unicode_ci DEFAULT NULL,
  `subject` varchar(270) COLLATE utf8_unicode_ci DEFAULT NULL,
  `message_id` varchar(250) COLLATE utf8_unicode_ci DEFAULT NULL,
  `in_reply_to` varchar(250) COLLATE utf8_unicode_ci DEFAULT NULL,
  `size` int(10) unsigned DEFAULT NULL,
  `message_no` int(10) unsigned DEFAULT NULL,
  `to` text COLLATE utf8_unicode_ci,
  `from` text COLLATE utf8_unicode_ci,
  `cc` text COLLATE utf8_unicode_ci,
  `reply_to` text COLLATE utf8_unicode_ci,
  `text_plain` text COLLATE utf8_unicode_ci,
  `text_html` text COLLATE utf8_unicode_ci,
  `references` text COLLATE utf8_unicode_ci,
  `attachments` text COLLATE utf8_unicode_ci,
  `seen` tinyint(1) unsigned DEFAULT NULL,
  `draft` tinyint(1) unsigned DEFAULT NULL,
  `recent` tinyint(1) unsigned DEFAULT NULL,
  `flagged` tinyint(1) unsigned DEFAULT NULL,
  `deleted` tinyint(1) unsigned DEFAULT NULL,
  `answered` tinyint(1) unsigned DEFAULT NULL,
  `synced` tinyint(1) unsigned DEFAULT NULL,
  `date` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX (`account_id`),
  INDEX (`folder_id`),
  INDEX (`unique_id`),
  INDEX (`date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

```