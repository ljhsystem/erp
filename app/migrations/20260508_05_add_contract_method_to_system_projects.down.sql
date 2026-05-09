SET @drop_project_contract_method_column = (
    SELECT IF(
        COUNT(*) > 0,
        'ALTER TABLE `system_projects` DROP COLUMN `contract_method`',
        'SELECT 1'
    )
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'system_projects'
      AND COLUMN_NAME = 'contract_method'
);
PREPARE stmt FROM @drop_project_contract_method_column;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
