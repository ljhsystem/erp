SET @drop_default_account_index = (
    SELECT IF(
        COUNT(*) > 0,
        'DROP INDEX `idx_system_clients_default_account_id` ON `system_clients`',
        'SELECT 1'
    )
    FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'system_clients'
      AND INDEX_NAME = 'idx_system_clients_default_account_id'
);
PREPARE stmt FROM @drop_default_account_index;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @drop_default_account_column = (
    SELECT IF(
        COUNT(*) > 0,
        'ALTER TABLE `system_clients` DROP COLUMN `default_account_id`',
        'SELECT 1'
    )
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'system_clients'
      AND COLUMN_NAME = 'default_account_id'
);
PREPARE stmt FROM @drop_default_account_column;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
