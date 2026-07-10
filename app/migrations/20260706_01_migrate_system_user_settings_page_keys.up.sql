START TRANSACTION;

SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;
SET collation_connection = 'utf8mb4_unicode_ci';

SET @has_user_id := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'system_user_settings'
      AND COLUMN_NAME = 'user_id'
);

SET @has_deleted_at := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'system_user_settings'
      AND COLUMN_NAME = 'deleted_at'
);

SET @legacy_scope_join := IF(
    @has_user_id > 0,
    ' AND ((canonical.`user_id` = legacy.`user_id`) OR (canonical.`user_id` IS NULL AND legacy.`user_id` IS NULL))',
    ''
);

SET @legacy_active_join := IF(
    @has_deleted_at > 0,
    ' AND canonical.`deleted_at` IS NULL',
    ''
);

SET @legacy_active_where := IF(
    @has_deleted_at > 0,
    ' AND legacy.`deleted_at` IS NULL',
    ''
);

SET @sql := CONCAT(
    'UPDATE `system_user_settings` legacy ',
    'LEFT JOIN `system_user_settings` canonical ',
    'ON canonical.`page_key` = ''account-subject-main'' ',
    'AND canonical.`setting_type` = legacy.`setting_type`',
    @legacy_scope_join,
    @legacy_active_join,
    ' SET legacy.`page_key` = ''account-subject-main'' ',
    'WHERE legacy.`page_key` = ''ledger-account''',
    @legacy_active_where,
    ' AND canonical.`page_key` IS NULL'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := CONCAT(
    'UPDATE `system_user_settings` legacy ',
    'LEFT JOIN `system_user_settings` canonical ',
    'ON canonical.`page_key` = ''account-subject-main'' ',
    'AND canonical.`setting_type` = legacy.`setting_type`',
    @legacy_scope_join,
    @legacy_active_join,
    ' SET legacy.`page_key` = ''account-subject-main'' ',
    'WHERE legacy.`page_key` = ''account-subject''',
    @legacy_active_where,
    ' AND canonical.`page_key` IS NULL'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := CONCAT(
    'UPDATE `system_user_settings` legacy ',
    'LEFT JOIN `system_user_settings` canonical ',
    'ON canonical.`page_key` = ''cover'' ',
    'AND canonical.`setting_type` = legacy.`setting_type`',
    @legacy_scope_join,
    @legacy_active_join,
    ' SET legacy.`page_key` = ''cover'' ',
    'WHERE legacy.`page_key` = ''cover-image''',
    @legacy_active_where,
    ' AND canonical.`page_key` IS NULL'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

DELETE FROM `system_user_settings`
WHERE `page_key` IN ('ledger-account', 'account-subject', 'cover-image');

COMMIT;
