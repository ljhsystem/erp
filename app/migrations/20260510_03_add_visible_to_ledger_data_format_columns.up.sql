ALTER TABLE `ledger_data_format_columns`
    ADD COLUMN `is_visible` TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'Default visibility in data status table' AFTER `is_required`;
