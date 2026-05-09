ALTER TABLE `ledger_data_format_columns`
    DROP INDEX `uk_format_system_field`;

UPDATE `ledger_data_format_columns`
SET `system_field_name` = ''
WHERE `system_field_name` IS NULL;

ALTER TABLE `ledger_data_format_columns`
    MODIFY COLUMN `system_field_name` VARCHAR(100) NOT NULL COMMENT '시스템 필드명';

ALTER TABLE `ledger_data_format_columns`
    DROP COLUMN `excel_column_index`;