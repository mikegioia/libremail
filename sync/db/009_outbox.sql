CREATE TABLE IF NOT EXISTS `outbox` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `account_id` int(10) unsigned NOT NULL,
  `parent_id` int(10) unsigned DEFAULT NULL,
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
  `deleted` tinyint(1) unsigned NOT NULL DEFAULT 0,
  `attempts` tinyint(1) unsigned NOT NULL DEFAULT 0,
  `send_after` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `update_history` text COLLATE utf8mb4_unicode_ci,
  PRIMARY KEY (`id`),
  INDEX (`account_id`),
  INDEX (`sent`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
