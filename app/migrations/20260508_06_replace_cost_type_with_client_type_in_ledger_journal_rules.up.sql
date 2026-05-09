SET @drop_journal_rule_conditions_index = (
    SELECT IF(
        COUNT(*) > 0,
        'DROP INDEX `idx_ledger_journal_rules_conditions` ON `ledger_journal_rules`',
        'SELECT 1'
    )
    FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'ledger_journal_rules'
      AND INDEX_NAME = 'idx_ledger_journal_rules_conditions'
);
PREPARE stmt FROM @drop_journal_rule_conditions_index;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @add_journal_rule_business_unit_column = (
    SELECT IF(
        COUNT(*) = 0,
        'ALTER TABLE `ledger_journal_rules` ADD COLUMN `business_unit` VARCHAR(30) NOT NULL DEFAULT ''CONSTRUCTION'' COMMENT ''사업구분(BUSINESS_UNIT)'' AFTER `rule_name`',
        'SELECT 1'
    )
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'ledger_journal_rules'
      AND COLUMN_NAME = 'business_unit'
);
PREPARE stmt FROM @add_journal_rule_business_unit_column;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @add_journal_rule_transaction_type_column = (
    SELECT IF(
        COUNT(*) = 0,
        'ALTER TABLE `ledger_journal_rules` ADD COLUMN `transaction_type` VARCHAR(30) NOT NULL DEFAULT ''GENERAL'' COMMENT ''거래유형(TRANSACTION_TYPE)'' AFTER `business_unit`',
        'SELECT 1'
    )
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'ledger_journal_rules'
      AND COLUMN_NAME = 'transaction_type'
);
PREPARE stmt FROM @add_journal_rule_transaction_type_column;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @add_journal_rule_client_type_column = (
    SELECT IF(
        COUNT(*) = 0,
        'ALTER TABLE `ledger_journal_rules` ADD COLUMN `client_type` VARCHAR(30) NULL DEFAULT NULL COMMENT ''거래처구분(CLIENT_TYPE)'' AFTER `transaction_direction`',
        'SELECT 1'
    )
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'ledger_journal_rules'
      AND COLUMN_NAME = 'client_type'
);
PREPARE stmt FROM @add_journal_rule_client_type_column;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @drop_journal_rule_cost_type_column = (
    SELECT IF(
        COUNT(*) > 0,
        'ALTER TABLE `ledger_journal_rules` DROP COLUMN `cost_type`',
        'SELECT 1'
    )
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'ledger_journal_rules'
      AND COLUMN_NAME = 'cost_type'
);
PREPARE stmt FROM @drop_journal_rule_cost_type_column;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @create_journal_rule_conditions_index = (
    SELECT IF(
        COUNT(*) = 0,
        'CREATE INDEX `idx_ledger_journal_rules_conditions` ON `ledger_journal_rules` (`business_unit`, `transaction_type`, `transaction_direction`, `client_type`, `import_type`, `is_active`)',
        'SELECT 1'
    )
    FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'ledger_journal_rules'
      AND INDEX_NAME = 'idx_ledger_journal_rules_conditions'
);
PREPARE stmt FROM @create_journal_rule_conditions_index;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
