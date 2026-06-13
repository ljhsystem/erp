SET @has_page_key_column := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'auth_permissions'
      AND COLUMN_NAME = 'page_key'
);

SET @add_page_key_column_sql := IF(
    @has_page_key_column = 0,
    'ALTER TABLE `auth_permissions` ADD COLUMN `page_key` VARCHAR(100) NULL AFTER `category`',
    'SELECT 1'
);

PREPARE stmt_add_page_key_column FROM @add_page_key_column_sql;
EXECUTE stmt_add_page_key_column;
DEALLOCATE PREPARE stmt_add_page_key_column;

SET @has_page_key_index := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'auth_permissions'
      AND INDEX_NAME = 'idx_auth_permissions_page_key'
);

SET @add_page_key_index_sql := IF(
    @has_page_key_index = 0,
    'ALTER TABLE `auth_permissions` ADD KEY `idx_auth_permissions_page_key` (`page_key`)',
    'SELECT 1'
);

PREPARE stmt_add_page_key_index FROM @add_page_key_index_sql;
EXECUTE stmt_add_page_key_index;
DEALLOCATE PREPARE stmt_add_page_key_index;
