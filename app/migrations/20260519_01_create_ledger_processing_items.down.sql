DROP INDEX IF EXISTS `idx_ledger_voucher_lines_processing_item`
    ON `ledger_voucher_lines`;

ALTER TABLE `ledger_voucher_lines`
    DROP COLUMN IF EXISTS `processing_item_id`;

DROP INDEX IF EXISTS `idx_ledger_transaction_lines_processing_item`
    ON `ledger_transaction_lines`;

ALTER TABLE `ledger_transaction_lines`
    DROP COLUMN IF EXISTS `processing_item_id`;

DROP INDEX IF EXISTS `idx_ledger_data_evidence_links_processing_item`
    ON `ledger_data_evidence_links`;

ALTER TABLE `ledger_data_evidence_links`
    DROP COLUMN IF EXISTS `processing_item_id`;

DROP TABLE IF EXISTS `ledger_processing_items`;
