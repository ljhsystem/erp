SET @drop_status_index = (
    SELECT IF(
        COUNT(*) > 0,
        'DROP INDEX `idx_ledger_accounts_status` ON `ledger_accounts`',
        'SELECT 1'
    )
    FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'ledger_accounts'
      AND INDEX_NAME = 'idx_ledger_accounts_status'
);
PREPARE stmt FROM @drop_status_index;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @drop_tree_sort_index = (
    SELECT IF(
        COUNT(*) > 0,
        'DROP INDEX `idx_ledger_accounts_tree_sort` ON `ledger_accounts`',
        'SELECT 1'
    )
    FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'ledger_accounts'
      AND INDEX_NAME = 'idx_ledger_accounts_tree_sort'
);
PREPARE stmt FROM @drop_tree_sort_index;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @drop_parent_index = (
    SELECT IF(
        COUNT(*) > 0,
        'DROP INDEX `idx_ledger_accounts_parent` ON `ledger_accounts`',
        'SELECT 1'
    )
    FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'ledger_accounts'
      AND INDEX_NAME = 'idx_ledger_accounts_parent'
);
PREPARE stmt FROM @drop_parent_index;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @drop_tree_sort_column = (
    SELECT IF(COUNT(*) > 0, 'ALTER TABLE `ledger_accounts` DROP COLUMN `tree_sort`', 'SELECT 1')
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ledger_accounts' AND COLUMN_NAME = 'tree_sort'
);
PREPARE stmt FROM @drop_tree_sort_column;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @drop_path_ids_column = (
    SELECT IF(COUNT(*) > 0, 'ALTER TABLE `ledger_accounts` DROP COLUMN `path_ids`', 'SELECT 1')
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ledger_accounts' AND COLUMN_NAME = 'path_ids'
);
PREPARE stmt FROM @drop_path_ids_column;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @drop_full_path_column = (
    SELECT IF(COUNT(*) > 0, 'ALTER TABLE `ledger_accounts` DROP COLUMN `full_path`', 'SELECT 1')
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ledger_accounts' AND COLUMN_NAME = 'full_path'
);
PREPARE stmt FROM @drop_full_path_column;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @drop_status_column = (
    SELECT IF(COUNT(*) > 0, 'ALTER TABLE `ledger_accounts` DROP COLUMN `status`', 'SELECT 1')
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ledger_accounts' AND COLUMN_NAME = 'status'
);
PREPARE stmt FROM @drop_status_column;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @drop_is_postable_column = (
    SELECT IF(COUNT(*) > 0, 'ALTER TABLE `ledger_accounts` DROP COLUMN `is_postable`', 'SELECT 1')
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ledger_accounts' AND COLUMN_NAME = 'is_postable'
);
PREPARE stmt FROM @drop_is_postable_column;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @drop_account_level_column = (
    SELECT IF(COUNT(*) > 0, 'ALTER TABLE `ledger_accounts` DROP COLUMN `account_level`', 'SELECT 1')
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ledger_accounts' AND COLUMN_NAME = 'account_level'
);
PREPARE stmt FROM @drop_account_level_column;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @drop_account_category_column = (
    SELECT IF(COUNT(*) > 0, 'ALTER TABLE `ledger_accounts` DROP COLUMN `account_category`', 'SELECT 1')
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ledger_accounts' AND COLUMN_NAME = 'account_category'
);
PREPARE stmt FROM @drop_account_category_column;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
