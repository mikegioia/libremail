CREATE TABLE IF NOT EXISTS `meta` (
  `key` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL,
  `value` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `meta`
  (`key`, `value`, `updated_at`)
VALUES
  ('asleep', 0, NOW()),
  ('running', 0, NOW()),
  ('heartbeat', '', NOW()),
  ('start_time', '', NOW()),
  ('folder_stats', '', NOW()),
  ('active_folder', '', NOW()),
  ('active_account', '', NOW());