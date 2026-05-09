ALTER TABLE `ledger_data_format_columns`
    MODIFY COLUMN `system_field_name` VARCHAR(100) NULL COMMENT '시스템 필드명';

UPDATE `ledger_data_format_columns`
SET `system_field_name` = NULL
WHERE TRIM(COALESCE(`system_field_name`, '')) = '';
