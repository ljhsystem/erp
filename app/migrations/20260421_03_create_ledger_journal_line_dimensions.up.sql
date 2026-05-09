CREATE TABLE IF NOT EXISTS `ledger_journal_line_dimensions` (
  `id` char(36) NOT NULL,
  `journal_line_id` char(36) NOT NULL,
  `sub_account_type` varchar(30) NOT NULL COMMENT 'partner, project, custom',
  `ref_id` char(36) NOT NULL,
  `ref_code` varchar(50) DEFAULT NULL,
  `ref_name` varchar(100) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_ljld_line_id` (`journal_line_id`),
  KEY `idx_ljld_type` (`sub_account_type`),
  KEY `idx_ljld_ref_id` (`ref_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
