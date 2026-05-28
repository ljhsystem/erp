ALTER TABLE `ledger_processing_items`
    ADD COLUMN IF NOT EXISTS `source_domain` VARCHAR(30) NULL DEFAULT NULL AFTER `source_type`,
    ADD COLUMN IF NOT EXISTS `lineage_root_id` VARCHAR(36) NULL DEFAULT NULL AFTER `source_item_id`,
    ADD COLUMN IF NOT EXISTS `is_current` TINYINT(1) NOT NULL DEFAULT 1 AFTER `lineage_root_id`;

UPDATE `ledger_processing_items`
SET `source_domain` = CASE
        WHEN `source_table` = 'ledger_data_evidences' THEN 'EVIDENCE'
        WHEN `source_table` = 'ledger_bank_transactions' THEN 'BANK'
        ELSE `source_domain`
    END,
    `lineage_root_id` = COALESCE(`lineage_root_id`, `source_item_id`, `id`),
    `is_current` = CASE
        WHEN `item_status` IN ('SPLIT', 'MERGED', 'INACTIVE', 'DELETED', 'IGNORED') THEN 0
        ELSE `is_current`
    END
WHERE `deleted_at` IS NULL;

CREATE INDEX IF NOT EXISTS `idx_ledger_processing_items_domain_source`
    ON `ledger_processing_items` (`source_domain`, `source_type`, `source_id`);

CREATE INDEX IF NOT EXISTS `idx_ledger_processing_items_current`
    ON `ledger_processing_items` (`is_current`, `item_status`, `deleted_at`);

CREATE INDEX IF NOT EXISTS `idx_ledger_processing_items_lineage`
    ON `ledger_processing_items` (`lineage_root_id`);
