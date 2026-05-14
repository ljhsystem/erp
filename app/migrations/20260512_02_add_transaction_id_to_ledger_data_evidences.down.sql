DROP INDEX IF EXISTS `idx_ledger_data_evidences_transaction_id`
    ON `ledger_data_evidences`;

ALTER TABLE `ledger_data_evidences`
    DROP COLUMN IF EXISTS `transaction_id`;
