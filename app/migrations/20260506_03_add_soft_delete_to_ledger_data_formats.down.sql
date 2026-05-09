DROP INDEX `idx_ledger_data_formats_deleted_at`
    ON `ledger_data_formats`;

ALTER TABLE `ledger_data_formats`
    DROP COLUMN `deleted_by`,
    DROP COLUMN `deleted_at`;
