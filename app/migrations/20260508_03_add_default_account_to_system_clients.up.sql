SET @add_default_account_column = (
    SELECT IF(
        COUNT(*) = 0,
        'ALTER TABLE `system_clients` ADD COLUMN `default_account_id` CHAR(36) NULL COMMENT ''자동분개 추천 기본계정과목 ID'' AFTER `trade_category`',
        'SELECT 1'
    )
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'system_clients'
      AND COLUMN_NAME = 'default_account_id'
);
PREPARE stmt FROM @add_default_account_column;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @add_default_account_index = (
    SELECT IF(
        COUNT(*) = 0,
        'CREATE INDEX `idx_system_clients_default_account_id` ON `system_clients` (`default_account_id`)',
        'SELECT 1'
    )
    FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'system_clients'
      AND INDEX_NAME = 'idx_system_clients_default_account_id'
);
PREPARE stmt FROM @add_default_account_index;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
