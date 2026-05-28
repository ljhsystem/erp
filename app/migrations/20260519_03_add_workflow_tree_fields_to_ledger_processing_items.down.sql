DROP INDEX IF EXISTS `idx_ledger_processing_items_lineage`
    ON `ledger_processing_items`;

DROP INDEX IF EXISTS `idx_ledger_processing_items_current`
    ON `ledger_processing_items`;

DROP INDEX IF EXISTS `idx_ledger_processing_items_domain_source`
    ON `ledger_processing_items`;

ALTER TABLE `ledger_processing_items`
    DROP COLUMN IF EXISTS `is_current`,
    DROP COLUMN IF EXISTS `lineage_root_id`,
    DROP COLUMN IF EXISTS `source_domain`;
