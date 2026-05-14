DROP INDEX IF EXISTS `idx_ledger_transaction_lines_type`
    ON `ledger_transaction_lines`;

ALTER TABLE `ledger_transaction_lines`
    DROP COLUMN IF EXISTS `amount`,
    DROP COLUMN IF EXISTS `line_type`;

ALTER TABLE `ledger_transactions`
    DROP COLUMN IF EXISTS `adjustment_amount`,
    DROP COLUMN IF EXISTS `base_amount`;
