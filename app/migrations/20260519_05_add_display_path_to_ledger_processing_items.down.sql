DROP INDEX `idx_ledger_processing_items_display_path`
    ON `ledger_processing_items`;

ALTER TABLE `ledger_processing_items`
    DROP COLUMN `display_path`;
