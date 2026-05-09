DROP INDEX IF EXISTS `idx_ledger_transactions_source_type`
    ON `ledger_transactions`;

ALTER TABLE `ledger_transactions`
    DROP COLUMN IF EXISTS `source_type`;
