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