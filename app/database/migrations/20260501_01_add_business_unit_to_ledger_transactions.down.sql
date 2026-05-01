DELETE FROM `system_codes`
WHERE `code_group` = 'BUSINESS_UNIT'
  AND `code` IN ('CONSTRUCTION', 'ECOMMERCE', 'HQ')
  AND (`created_by` = 'migration' OR `updated_by` = 'migration');

DROP INDEX IF EXISTS `idx_ledger_transactions_business_unit`
    ON `ledger_transactions`;

ALTER TABLE `ledger_transactions`
    DROP COLUMN IF EXISTS `business_unit`;
