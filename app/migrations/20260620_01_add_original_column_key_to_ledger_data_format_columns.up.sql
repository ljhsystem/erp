ALTER TABLE `ledger_data_format_columns`
    ADD COLUMN `original_column_key` VARCHAR(150) NULL COMMENT '원본 컬럼 식별키' AFTER `excel_column_name`;

UPDATE `ledger_data_format_columns`
SET `original_column_key` = CASE
    WHEN TRIM(COALESCE(`system_field_name`, '')) <> '' THEN TRIM(`system_field_name`)
    WHEN TRIM(COALESCE(`excel_column_name`, '')) <> '' THEN CONCAT('legacy_column_', LPAD(COALESCE(`column_order`, `excel_column_index`, 0), 3, '0'))
    ELSE CONCAT('legacy_column_', LPAD(`id`, 6, '0'))
END
WHERE TRIM(COALESCE(`original_column_key`, '')) = '';

ALTER TABLE `ledger_data_format_columns`
    MODIFY COLUMN `original_column_key` VARCHAR(150) NOT NULL COMMENT '원본 컬럼 식별키';

ALTER TABLE `ledger_data_format_columns`
    ADD UNIQUE KEY `uk_format_original_column` (`format_id`, `original_column_key`);
