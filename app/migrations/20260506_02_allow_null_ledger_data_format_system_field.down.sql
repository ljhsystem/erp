UPDATE `ledger_data_format_columns`
SET `system_field_name` = ''
WHERE `system_field_name` IS NULL;

ALTER TABLE `ledger_data_format_columns`
    MODIFY COLUMN `system_field_name` VARCHAR(100) NOT NULL COMMENT '시스템 필드명';
