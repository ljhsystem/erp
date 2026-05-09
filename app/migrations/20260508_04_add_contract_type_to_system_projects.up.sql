SET @add_project_contract_type_column = (
    SELECT IF(
        COUNT(*) = 0,
        'ALTER TABLE `system_projects` ADD COLUMN `contract_type` VARCHAR(50) NULL AFTER `site_agent`',
        'SELECT 1'
    )
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'system_projects'
      AND COLUMN_NAME = 'contract_type'
);
PREPARE stmt FROM @add_project_contract_type_column;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

UPDATE `system_projects`
SET `contract_type` = `contract_work_type`
WHERE (`contract_type` IS NULL OR TRIM(`contract_type`) = '')
  AND `contract_work_type` IS NOT NULL
  AND TRIM(`contract_work_type`) <> '';
