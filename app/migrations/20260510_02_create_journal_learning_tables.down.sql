ALTER TABLE `ledger_journal_rules`
    DROP COLUMN IF EXISTS `confidence_score`,
    DROP COLUMN IF EXISTS `last_used_at`,
    DROP COLUMN IF EXISTS `usage_count`;

DROP TABLE IF EXISTS `ledger_recent_journal_patterns`;
DROP TABLE IF EXISTS `ledger_client_account_patterns`;
DROP TABLE IF EXISTS `ledger_journal_learning_events`;
