DROP INDEX IF EXISTS `idx_ledger_vouchers_transaction_id`
    ON `ledger_vouchers`;

ALTER TABLE `ledger_vouchers`
    DROP COLUMN IF EXISTS `transaction_id`;
