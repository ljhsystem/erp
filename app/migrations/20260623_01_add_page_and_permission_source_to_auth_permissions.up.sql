SET @has_page_column := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'auth_permissions'
      AND COLUMN_NAME = 'page'
);

SET @add_page_column_sql := IF(
    @has_page_column = 0,
    'ALTER TABLE `auth_permissions` ADD COLUMN `page` VARCHAR(191) NULL AFTER `page_key`',
    'SELECT 1'
);

PREPARE stmt_add_page_column FROM @add_page_column_sql;
EXECUTE stmt_add_page_column;
DEALLOCATE PREPARE stmt_add_page_column;

SET @has_permission_source_column := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'auth_permissions'
      AND COLUMN_NAME = 'permission_source'
);

SET @add_permission_source_column_sql := IF(
    @has_permission_source_column = 0,
    'ALTER TABLE `auth_permissions` ADD COLUMN `permission_source` VARCHAR(20) NULL AFTER `page`',
    'SELECT 1'
);

PREPARE stmt_add_permission_source_column FROM @add_permission_source_column_sql;
EXECUTE stmt_add_permission_source_column;
DEALLOCATE PREPARE stmt_add_permission_source_column;

UPDATE `auth_permissions` `ap`
LEFT JOIN `system_page_registry` `spr`
    ON `spr`.`page_key` = `ap`.`page_key`
SET
    `ap`.`page` = CASE
        WHEN COALESCE(NULLIF(TRIM(`ap`.`page`), ''), '') <> '' THEN `ap`.`page`
        WHEN COALESCE(NULLIF(TRIM(`spr`.`page_label`), ''), '') <> '' THEN `spr`.`page_label`
        WHEN COALESCE(NULLIF(TRIM(`ap`.`permission_name`), ''), '') <> '' THEN `ap`.`permission_name`
        ELSE NULL
    END,
    `ap`.`permission_source` = CASE
        WHEN LOWER(TRIM(COALESCE(`ap`.`permission_source`, ''))) IN ('web', 'api') THEN LOWER(TRIM(`ap`.`permission_source`))
        WHEN `ap`.`permission_key` LIKE 'web.%' THEN 'web'
        ELSE 'api'
    END;
