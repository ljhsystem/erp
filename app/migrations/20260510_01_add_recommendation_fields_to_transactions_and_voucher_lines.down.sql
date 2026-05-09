DROP INDEX IF EXISTS `idx_ledger_transactions_recommendation`
    ON `ledger_transactions`;

ALTER TABLE `ledger_transactions`
    DROP COLUMN IF EXISTS `import_type`,
    DROP COLUMN IF EXISTS `transaction_direction`;

ALTER TABLE `ledger_voucher_lines`
    DROP COLUMN IF EXISTS `is_user_modified`,
    DROP COLUMN IF EXISTS `recommend_reason`,
    DROP COLUMN IF EXISTS `journal_rule_id`,
    DROP COLUMN IF EXISTS `recommend_confidence`,
    DROP COLUMN IF EXISTS `recommend_source`;
