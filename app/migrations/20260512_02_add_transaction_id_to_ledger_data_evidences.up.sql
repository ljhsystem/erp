ALTER TABLE `ledger_data_evidences`
    ADD COLUMN IF NOT EXISTS `transaction_id` VARCHAR(36) NULL DEFAULT NULL
        COMMENT 'Created transaction ID'
        AFTER `error_message`;

CREATE INDEX IF NOT EXISTS `idx_ledger_data_evidences_transaction_id`
    ON `ledger_data_evidences` (`transaction_id`);
