DROP INDEX IF EXISTS `idx_ledger_vouchers_journal_status`
    ON `ledger_vouchers`;

ALTER TABLE `ledger_vouchers`
    DROP COLUMN IF EXISTS `journal_status`;
