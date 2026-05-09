ALTER TABLE `ledger_data_formats`
    ADD COLUMN `deleted_at` DATETIME NULL COMMENT '삭제일시' AFTER `created_by`,
    ADD COLUMN `deleted_by` VARCHAR(100) NULL COMMENT '삭제자' AFTER `deleted_at`;

CREATE INDEX `idx_ledger_data_formats_deleted_at`
    ON `ledger_data_formats` (`deleted_at`);
