CREATE TABLE IF NOT EXISTS `accounts` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `service` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `name` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `password` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `imap_host` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `imap_port` mediumint(5) DEFAULT NULL,
  `imap_flags` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `smtp_host` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `smtp_port` mediumint(5) DEFAULT NULL,
  `is_active` tinyint(1) unsigned DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;