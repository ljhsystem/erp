DROP INDEX IF EXISTS `idx_ledger_journal_rules_conditions`
    ON `ledger_journal_rules`;

CREATE INDEX IF NOT EXISTS `idx_ledger_journal_rules_conditions`
    ON `ledger_journal_rules` (`business_unit`, `transaction_type`, `import_type`, `transaction_direction`, `is_active`);

ALTER TABLE `ledger_journal_rules`
    DROP COLUMN IF EXISTS `cost_type`;
