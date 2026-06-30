SET @has_permission_source_column := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'auth_permissions'
      AND COLUMN_NAME = 'permission_source'
);

SET @drop_permission_source_column_sql := IF(
    @has_permission_source_column > 0,
    'ALTER TABLE `auth_permissions` DROP COLUMN `permission_source`',
    'SELECT 1'
);

PREPARE stmt_drop_permission_source_column FROM @drop_permission_source_column_sql;
EXECUTE stmt_drop_permission_source_column;
DEALLOCATE PREPARE stmt_drop_permission_source_column;

SET @has_page_column := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'auth_permissions'
      AND COLUMN_NAME = 'page'
);

SET @drop_page_column_sql := IF(
    @has_page_column > 0,
    'ALTER TABLE `auth_permissions` DROP COLUMN `page`',
    'SELECT 1'
);

PREPARE stmt_drop_page_column FROM @drop_page_column_sql;
EXECUTE stmt_drop_page_column;
DEALLOCATE PREPARE stmt_drop_page_column;
