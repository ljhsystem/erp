DROP INDEX IF EXISTS `idx_ledger_journal_rules_conditions`
    ON `ledger_journal_rules`;

ALTER TABLE `ledger_journal_rules`
    DROP COLUMN IF EXISTS `transaction_type`,
    DROP COLUMN IF EXISTS `business_unit`;

DELETE FROM `system_codes`
WHERE `code_group` = 'TRANSACTION_DIRECTION'
  AND `code` IN ('PURCHASE', 'SALES', 'IN', 'OUT');
