CREATE TABLE IF NOT EXISTS `ledger_account_sub_policies` (
  `id` char(36) NOT NULL,
  `account_id` char(36) NOT NULL,
  `sub_account_type` varchar(30) NOT NULL COMMENT 'partner, project, custom',
  `is_required` tinyint(1) NOT NULL DEFAULT '0',
  `is_multiple` tinyint(1) NOT NULL DEFAULT '0',
  `sort_order` int NOT NULL DEFAULT '0',
  `custom_group_code` varchar(50) DEFAULT NULL,
  `note` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_by` varchar(100) DEFAULT NULL,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `updated_by` varchar(100) DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_lasp_account_id` (`account_id`),
  KEY `idx_lasp_type` (`sub_account_type`),
  UNIQUE KEY `uq_lasp_account_type_group` (`account_id`, `sub_account_type`, `custom_group_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
