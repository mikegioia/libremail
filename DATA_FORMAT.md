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
  `service` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `name` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `password` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `imap_host` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `imap_port` mediumint(5) DEFAULT NULL,
  `imap_flags` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_active` tinyint(1) unsigned DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

- `id` Unique integer identifying the account.
- `service` Optional string referencing the IMAP service. For example, this
   could say 'gmail', 'outlook', 'yahoo', etc.
- `name` Display name (human name) for the email account. This is used in the
   from address when sending mail.
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
  `name` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `count` int(10) unsigned DEFAULT '0',
  `synced` int(10) unsigned DEFAULT '0',
  `deleted` tinyint(1) unsigned DEFAULT '0',
  `ignored` tinyint(1) unsigned DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE( `account_id`, `name` )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

- `id` Unique integer identifying the folder.
- `account_id` Foreign key referencing the account from the `accounts` table.
- `name` Full global name of the folder as saved on the IMAP server. For
   example, this would be 'Accounts/Listserv/LibreMail' instead of 'LibreMail'.
- `count` Total number of messages in the folder.
- `synced` Number of messages that have been downloaded for this folder.
- `deleted` Boolean flag denoting if the folder was deleted on the IMAP server.
   Deleted folders should not be synced.
- `ignored` Boolean flag denoting if the folder should be ignored from sycning
   locally. Any folder with this flag set to 1 should not have its messages
   downloaded.
- `created_at` Timestamp denoting when the folder was added to the database.

## Messages

```SQL
CREATE TABLE IF NOT EXISTS `messages` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `account_id` int(10) unsigned NOT NULL,
  `folder_id` int(10) unsigned NOT NULL,
  `unique_id` int(10) unsigned DEFAULT NULL,
  `thread_id` int(10) unsigned DEFAULT NULL,
  `date_str` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `charset` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `subject` varchar(270) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `message_id` varchar(250) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `in_reply_to` varchar(250) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `recv_str` varchar(250) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `size` int(10) unsigned DEFAULT NULL,
  `message_no` int(10) unsigned DEFAULT NULL,
  `to` text COLLATE utf8mb4_unicode_ci,
  `from` text COLLATE utf8mb4_unicode_ci,
  `cc` text COLLATE utf8mb4_unicode_ci,
  `bcc` text COLLATE utf8mb4_unicode_ci,
  `reply_to` text COLLATE utf8mb4_unicode_ci,
  `text_plain` longtext COLLATE utf8mb4_unicode_ci,
  `text_html` longtext COLLATE utf8mb4_unicode_ci,
  `references` text COLLATE utf8mb4_unicode_ci,
  `attachments` text COLLATE utf8mb4_unicode_ci,
  `raw_headers` longtext COLLATE utf8mb4_unicode_ci,
  `raw_content` longtext COLLATE utf8mb4_unicode_ci,
  `seen` tinyint(1) unsigned DEFAULT NULL,
  `draft` tinyint(1) unsigned DEFAULT NULL,
  `recent` tinyint(1) unsigned DEFAULT NULL,
  `flagged` tinyint(1) unsigned DEFAULT NULL,
  `deleted` tinyint(1) unsigned DEFAULT NULL,
  `answered` tinyint(1) unsigned DEFAULT NULL,
  `synced` tinyint(1) unsigned DEFAULT NULL,
  `purge` tinyint(1) unsigned DEFAULT NULL,
  `date` datetime DEFAULT NULL,
  `date_recv` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX (`date`),
  INDEX (`seen`),
  INDEX (`synced`),
  INDEX (`deleted`),
  INDEX (`flagged`),
  INDEX (`folder_id`),
  INDEX (`unique_id`),
  INDEX (`thread_id`),
  INDEX (`account_id`),
  INDEX (`message_id`(16)),
  INDEX (`in_reply_to`(16)),
  FULLTEXT KEY subject_text_plain (subject,text_plain)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

- `id` Unique integer identifying the message.
- `account_id` Foreign key referencing the account from the `accounts` table.
- `folder_id` Foreign key referencing the folder from the `folders` table.
- `unique_id` Unique IMAP mail ID as given from the mail server. The unique ID
   differs from the `message_no` in that it is an unchanging ID issued from the
   mail server. This unique ID (or uid) is used in determining which messages
   are new or marked for deletion in the syncing process.
- `thread_id` An identifier common to all messages within a thread. A thread is
   computed using the `message_id`, `references`, and any addresses in the `to`,
   `cc`, `from`, `bcc`, and `reply_to` fields.
- `date_str` The date string as stored in the mail header. This can take
   many different formats and sometimes not even be a valid date string. It
   should be stored here regardless. The `date` field is a cleansed version of
   this (see below).
- `charset` The character set as stored in the mail header. This is to be
   used when decoding the plain text if the plain text part is encoded in a
   non UTF-8 format.
- `subject` The subject of the message.
- `message_id` The message ID as stored in the mail header. This usually
   takes a form of `<591d...4140a@example.org>` and is returned from the
   mail server.
- `in_reply_to` Optional string containing the `message_id` of the message that
   this email is replying to. This value comes from the mail header.
- `recv_str` Date the message was received by the account's mail server.
- `size` Integer denoting the size in bytes of the message.
- `message_no` The positional message number returned from the mail server.
   **This value can change** if messages are moved within a folder and is only
   used when fetching a message or other information from the IMAP server.
- `to` String containing the entire `To` mail header value. This is usually of
   the form "Full name <fullname@example.org>".
- `from` String containing the entire `From` mail header value.
- `cc` String containing the entire `Cc` mail header value.
- `bcc` String containing the entire `Bcc` mail header value.
- `reply_to` String containing the entire `Reply-To` or `Return-Path` mail
   header value.
- `text_plain` The full string text of the `text/plain` part of the message.
   This can sometimes contain concatenated plain text mail parts.
- `text_html` The full string text of the `text/html` part of the message.
- `references` Optional string containing the entire `References` mail header.
   References are any other `message_id`s that may be included in any way
   within the message.
- `attachments` **@TODO JSON encode this field or move to another table**
   Serialized array of the attachment information. This is an array of objects
   containing the name, filename, path on disk, mime-type, and the original
   file name and name fields (which may be empty).
- `raw_headers` Raw message headers as stored on the mail server.
- `raw_content` Raw message content as stored on the mail server. This includes
   all mail parts as one contiguous string.
- `seen` Boolean value, 1 if the `\Seen` flag exists on the message.
- `draft` Boolean value, 1 if the `\Draft` flag exists on the message.
- `recent` Boolean value, 1 if the `\Recent` flag exists on the message.
- `flagged` Boolean value, 1 if the `\Flagged` flag exists on the message.
- `deleted` Boolean value denoting if the message was deleted on the server.
- `answered` Boolean value, 1 if the `\Answered` flag exists on the message.
- `synced` Boolean value denoting if the message has been synced with the IMAP
   server. This is a placeholder for now but if the syncing process was multi-
   stage and only the headers were saved in this table, then this value would
   remain 0 until the text, html, attachments, and other mail parts were synced.
- `purge` Boolean value for internal use, 1 if the message should be deleted from
   the database on the next sync cleanup operation.
- `date` Date-time field representing the processed `date_str` from the message.
   This field is in the format `YYYY-MM-DD HH-MM-SS`.
- `date_rcv` Date-time fields represending the processed `recv_str` from the
   message.
- `created_at` Timestamp denoting when the message was added to the database.

## Tasks

```SQL
CREATE TABLE IF NOT EXISTS `tasks` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `batch_id` int(10) unsigned NOT NULL,
  `account_id` int(10) unsigned NOT NULL,
  `message_id` int(10) unsigned NOT NULL,
  `type` varchar(8) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` tinyint(1) unsigned NOT NULL,
  `old_value` tinyint(1) unsigned DEFAULT NULL,
  `folder_id` int(10) unsigned DEFAULT NULL,
  `retries` tinyint(1) unsigned DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `reason` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  INDEX (`status`),
  INDEX (`batch_id`),
  INDEX (`account_id`),
  INDEX (`message_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

- `id` Unique integer identifying the task.
- `batch_id` Foreign key referencing the batch of tasks it's a part of.
- `account_id` Foreign key referencing the account from the `accounts` table.
- `message_id` Foreign key referencing the message this task affects.
- `type` String denoting the type of task it is.
- `status` The current state of the task. 0 new, 1 done, 2 error, 3 reverted.
- `old_value` The previous value before the task is performed. Used for rolling
   back any changes.
- `folder_id` Optional reference to a folder if the task applies to one.
- `retries` Optional number of retry attempts made.
- `created_at` Timestamp denoting when the task was added to the database.
- `reason` Optional description explaining the status.

## Batches

```SQL
CREATE TABLE IF NOT EXISTS `batches` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

```

- `id` Unique integer identifying the batch of one or more tasks. This is
  used for rolling back or undoing the most recent action. If that action
  contains more than one background task, the batch ID is used to reference
  all of those background tasks.
- `created_at` Timestamp denoting when the batch was added to the database.

## Contacts

```SQL
CREATE TABLE IF NOT EXISTS `contacts` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `account_id` int(10) unsigned NOT NULL,
  `name` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tally` int(10) unsigned DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `account_id_name` (`account_id`, `name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

```

- `id` Unique integer identifying the contact.
- `account_id` Foreign key referencing the account from the `accounts` table.
- `name` Name of the contact with email address
- `tally` Count of different messages this contact was a part of.
- `created_at` Timestamp denoting when the contact was added to the database.

## Outbox

```SQL
CREATE TABLE IF NOT EXISTS `outbox` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `account_id` int(10) unsigned NOT NULL,
  `parent_id` int(10) unsigned NOT NULL,
  `to` text COLLATE utf8mb4_unicode_ci,
  `from` text COLLATE utf8mb4_unicode_ci,
  `cc` text COLLATE utf8mb4_unicode_ci,
  `bcc` text COLLATE utf8mb4_unicode_ci,
  `reply_to` text COLLATE utf8mb4_unicode_ci,
  `subject` varchar(270) COLLATE utf8mb4_unicode_ci,
  `text_plain` longtext COLLATE utf8mb4_unicode_ci,
  `text_html` longtext COLLATE utf8mb4_unicode_ci,
  `draft` tinyint(1) unsigned NOT NULL DEFAULT 0,
  `sent` tinyint(1) unsigned NOT NULL DEFAULT 0,
  `locked` tinyint(1) unsigned NOT NULL DEFAULT 0,
  `attempts` tinyint(1) unsigned NOT NULL DEFAULT 0,
  `send_after` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `update_history` text COLLATE utf8mb4_unicode_ci,
  PRIMARY KEY (`id`),
  INDEX (`account_id`),
  INDEX (`sent`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

```

- `id` Unique integer identifying the outbox message.
- `account_id` Foreign key referencing the account from the `accounts` table.
- `account_id` Foreign key referencing the message that this message is replying
  to. Not used if the message is starting a new thread.
- `to` Comma separated list of addresses to send the message to.
- `from` Address used in the from header.
- `cc` Comma separated list of addresses for the cc.
- `bcc` Comma separated list of addresses for the bcc.
- `reply_to` Reply-To header string.
- `subject` Subject line of the message.
- `text_plain` String containing the plain text version of the message.
- `text_html` String containing the HTML formatted version of the message.
- `draft` Flag denoting if the message is a draft.
- `sent` Flag denoting if this message has been sent. No further action neeeded
  if it has been.
- `locked` Flag denoting if the message is locked. This is used so that two actions
  aren't performed on the same outbox message at once.
- `attempts` Integer counting the number of send attempts for this message.
- `send_after` Optional timestamp to delay the sending of a message.
- `created_at` Timestamp denoting when the message was added to the database.
- `updated_at` Timestamp denoting when the message was last updated.
- `update_history` String log of all actions performed on this message. Each action
  is separated by a new line.