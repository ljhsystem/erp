ALTER TABLE `ledger_journal_rules`
    ADD COLUMN IF NOT EXISTS `cost_type` VARCHAR(30) NULL DEFAULT NULL COMMENT '원가분류(COST_TYPE)' AFTER `transaction_direction`;

DROP INDEX IF EXISTS `idx_ledger_journal_rules_conditions`
    ON `ledger_journal_rules`;

CREATE INDEX IF NOT EXISTS `idx_ledger_journal_rules_conditions`
    ON `ledger_journal_rules` (`business_unit`, `transaction_type`, `import_type`, `transaction_direction`, `cost_type`, `is_active`);
