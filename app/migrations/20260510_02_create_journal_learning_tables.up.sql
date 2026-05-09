CREATE TABLE IF NOT EXISTS `ledger_journal_learning_events` (
  `id` CHAR(36) NOT NULL,
  `transaction_id` CHAR(36) NOT NULL,
  `voucher_id` CHAR(36) NOT NULL,
  `voucher_line_id` CHAR(36) NULL DEFAULT NULL,
  `client_id` CHAR(36) NULL DEFAULT NULL,
  `project_id` CHAR(36) NULL DEFAULT NULL,
  `business_unit` VARCHAR(30) NULL DEFAULT NULL,
  `transaction_type` VARCHAR(30) NULL DEFAULT NULL,
  `transaction_direction` VARCHAR(30) NULL DEFAULT NULL,
  `import_type` VARCHAR(30) NULL DEFAULT NULL,
  `client_type` VARCHAR(30) NULL DEFAULT NULL,
  `line_no` INT NOT NULL DEFAULT 0,
  `line_type` VARCHAR(10) NOT NULL,
  `recommended_line_type` VARCHAR(10) NULL DEFAULT NULL,
  `final_line_type` VARCHAR(10) NULL DEFAULT NULL,
  `recommended_account_id` CHAR(36) NULL DEFAULT NULL,
  `final_account_id` CHAR(36) NULL DEFAULT NULL,
  `recommended_amount` DECIMAL(18,2) NOT NULL DEFAULT 0.00,
  `final_amount` DECIMAL(18,2) NOT NULL DEFAULT 0.00,
  `recommend_source` VARCHAR(30) NULL DEFAULT NULL,
  `recommend_confidence` TINYINT UNSIGNED NULL DEFAULT NULL,
  `journal_rule_id` CHAR(36) NULL DEFAULT NULL,
  `recommend_reason` VARCHAR(255) NULL DEFAULT NULL,
  `is_user_modified` TINYINT(1) NOT NULL DEFAULT 0,
  `failure_type` VARCHAR(100) NULL DEFAULT NULL,
  `source_payload` JSON NULL DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_by` VARCHAR(100) NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_ljle_transaction_id` (`transaction_id`),
  KEY `idx_ljle_voucher_id` (`voucher_id`),
  KEY `idx_ljle_client_context` (`client_id`, `transaction_direction`, `import_type`),
  KEY `idx_ljle_rule_id` (`journal_rule_id`),
  KEY `idx_ljle_modified` (`is_user_modified`, `failure_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `ledger_client_account_patterns` (
  `id` CHAR(36) NOT NULL,
  `client_id` CHAR(36) NOT NULL,
  `transaction_direction` VARCHAR(30) NOT NULL,
  `line_type` VARCHAR(10) NOT NULL,
  `account_id` CHAR(36) NOT NULL,
  `usage_count` INT UNSIGNED NOT NULL DEFAULT 0,
  `recent_score` DECIMAL(14,4) NOT NULL DEFAULT 0.0000,
  `last_used_at` DATETIME NULL DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_lcap_client_direction_line_account` (`client_id`, `transaction_direction`, `line_type`, `account_id`),
  KEY `idx_lcap_client_rank` (`client_id`, `usage_count`, `last_used_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `ledger_recent_journal_patterns` (
  `id` CHAR(36) NOT NULL,
  `pattern_hash` CHAR(40) NOT NULL,
  `client_id` CHAR(36) NULL DEFAULT NULL,
  `transaction_direction` VARCHAR(30) NOT NULL,
  `debit_account_id` CHAR(36) NOT NULL,
  `credit_account_id` CHAR(36) NOT NULL,
  `vat_account_id` CHAR(36) NULL DEFAULT NULL,
  `project_id` CHAR(36) NULL DEFAULT NULL,
  `usage_count` INT UNSIGNED NOT NULL DEFAULT 0,
  `last_used_at` DATETIME NULL DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_lrjp_pattern_hash` (`pattern_hash`),
  KEY `idx_lrjp_client_direction` (`client_id`, `transaction_direction`, `usage_count`, `last_used_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET @add_journal_rule_usage_count = (
    SELECT IF(
        COUNT(*) = 0,
        'ALTER TABLE `ledger_journal_rules` ADD COLUMN `usage_count` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT ''Confirmed usage count'' AFTER `vat_account_id`',
        'SELECT 1'
    )
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'ledger_journal_rules'
      AND COLUMN_NAME = 'usage_count'
);
PREPARE stmt FROM @add_journal_rule_usage_count;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @add_journal_rule_last_used_at = (
    SELECT IF(
        COUNT(*) = 0,
        'ALTER TABLE `ledger_journal_rules` ADD COLUMN `last_used_at` DATETIME NULL DEFAULT NULL COMMENT ''Last confirmed usage time'' AFTER `usage_count`',
        'SELECT 1'
    )
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'ledger_journal_rules'
      AND COLUMN_NAME = 'last_used_at'
);
PREPARE stmt FROM @add_journal_rule_last_used_at;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @add_journal_rule_confidence_score = (
    SELECT IF(
        COUNT(*) = 0,
        'ALTER TABLE `ledger_journal_rules` ADD COLUMN `confidence_score` DECIMAL(5,2) NOT NULL DEFAULT 0.00 COMMENT ''Rule confidence from confirmed usage'' AFTER `last_used_at`',
        'SELECT 1'
    )
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'ledger_journal_rules'
      AND COLUMN_NAME = 'confidence_score'
);
PREPARE stmt FROM @add_journal_rule_confidence_score;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
