DROP INDEX IF EXISTS `idx_ledger_vouchers_import_type`
    ON `ledger_vouchers`;

ALTER TABLE `ledger_vouchers`
    DROP COLUMN IF EXISTS `import_type`;
