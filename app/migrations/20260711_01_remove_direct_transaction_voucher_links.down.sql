-- This migration is intentionally data-irreversible. A rollback restores only
-- compatibility columns; retired direct-link rows cannot be reconstructed.

ALTER TABLE `ledger_vouchers`
    ADD COLUMN IF NOT EXISTS `transaction_id` VARCHAR(36) NULL DEFAULT NULL
        COMMENT 'Legacy direct transaction relation';

CREATE INDEX IF NOT EXISTS `idx_ledger_vouchers_transaction_id`
    ON `ledger_vouchers` (`transaction_id`);

ALTER TABLE `ledger_transactions`
    ADD COLUMN IF NOT EXISTS `evidence_id` VARCHAR(36) NULL DEFAULT NULL
        COMMENT 'Legacy direct evidence relation',
    ADD COLUMN IF NOT EXISTS `match_status` VARCHAR(30) NULL DEFAULT NULL
        COMMENT 'Legacy transaction-voucher match status';

-- The retired ledger_data_evidences table is not recreated by rollback.
