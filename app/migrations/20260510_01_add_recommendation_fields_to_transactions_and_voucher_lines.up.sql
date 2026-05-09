SET @add_transaction_direction_column = (
    SELECT IF(
        COUNT(*) = 0,
        'ALTER TABLE `ledger_transactions` ADD COLUMN `transaction_direction` VARCHAR(30) NULL DEFAULT NULL COMMENT ''Transaction direction: PURCHASE, SALES, IN, OUT'' AFTER `transaction_type`',
        'SELECT 1'
    )
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'ledger_transactions'
      AND COLUMN_NAME = 'transaction_direction'
);
PREPARE stmt FROM @add_transaction_direction_column;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @add_import_type_column = (
    SELECT IF(
        COUNT(*) = 0,
        'ALTER TABLE `ledger_transactions` ADD COLUMN `import_type` VARCHAR(30) NULL DEFAULT NULL COMMENT ''Source import type from seed rows'' AFTER `transaction_direction`',
        'SELECT 1'
    )
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'ledger_transactions'
      AND COLUMN_NAME = 'import_type'
);
PREPARE stmt FROM @add_import_type_column;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @create_transaction_recommendation_index = (
    SELECT IF(
        COUNT(*) = 0,
        'CREATE INDEX `idx_ledger_transactions_recommendation` ON `ledger_transactions` (`business_unit`, `transaction_type`, `transaction_direction`, `import_type`, `client_id`)',
        'SELECT 1'
    )
    FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'ledger_transactions'
      AND INDEX_NAME = 'idx_ledger_transactions_recommendation'
);
PREPARE stmt FROM @create_transaction_recommendation_index;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @add_recommend_source_column = (
    SELECT IF(
        COUNT(*) = 0,
        'ALTER TABLE `ledger_voucher_lines` ADD COLUMN `recommend_source` VARCHAR(30) NULL DEFAULT NULL COMMENT ''Recommendation source'' AFTER `line_summary`',
        'SELECT 1'
    )
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'ledger_voucher_lines'
      AND COLUMN_NAME = 'recommend_source'
);
PREPARE stmt FROM @add_recommend_source_column;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @add_recommend_confidence_column = (
    SELECT IF(
        COUNT(*) = 0,
        'ALTER TABLE `ledger_voucher_lines` ADD COLUMN `recommend_confidence` TINYINT UNSIGNED NULL DEFAULT NULL COMMENT ''Recommendation confidence 0-100'' AFTER `recommend_source`',
        'SELECT 1'
    )
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'ledger_voucher_lines'
      AND COLUMN_NAME = 'recommend_confidence'
);
PREPARE stmt FROM @add_recommend_confidence_column;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @add_journal_rule_id_column = (
    SELECT IF(
        COUNT(*) = 0,
        'ALTER TABLE `ledger_voucher_lines` ADD COLUMN `journal_rule_id` CHAR(36) NULL DEFAULT NULL COMMENT ''Matched journal rule ID'' AFTER `recommend_confidence`',
        'SELECT 1'
    )
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'ledger_voucher_lines'
      AND COLUMN_NAME = 'journal_rule_id'
);
PREPARE stmt FROM @add_journal_rule_id_column;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @add_recommend_reason_column = (
    SELECT IF(
        COUNT(*) = 0,
        'ALTER TABLE `ledger_voucher_lines` ADD COLUMN `recommend_reason` VARCHAR(255) NULL DEFAULT NULL COMMENT ''Recommendation reason'' AFTER `journal_rule_id`',
        'SELECT 1'
    )
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'ledger_voucher_lines'
      AND COLUMN_NAME = 'recommend_reason'
);
PREPARE stmt FROM @add_recommend_reason_column;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @add_is_user_modified_column = (
    SELECT IF(
        COUNT(*) = 0,
        'ALTER TABLE `ledger_voucher_lines` ADD COLUMN `is_user_modified` TINYINT(1) NOT NULL DEFAULT 0 COMMENT ''User changed recommended line'' AFTER `recommend_reason`',
        'SELECT 1'
    )
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'ledger_voucher_lines'
      AND COLUMN_NAME = 'is_user_modified'
);
PREPARE stmt FROM @add_is_user_modified_column;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
