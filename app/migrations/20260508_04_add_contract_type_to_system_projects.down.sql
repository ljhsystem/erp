SET @drop_project_contract_type_column = (
    SELECT IF(
        COUNT(*) > 0,
        'ALTER TABLE `system_projects` DROP COLUMN `contract_type`',
        'SELECT 1'
    )
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'system_projects'
      AND COLUMN_NAME = 'contract_type'
);
PREPARE stmt FROM @drop_project_contract_type_column;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
