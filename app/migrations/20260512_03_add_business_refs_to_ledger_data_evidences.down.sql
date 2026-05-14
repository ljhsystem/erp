DROP INDEX IF EXISTS `idx_ledger_data_evidences_card_id` ON `ledger_data_evidences`;
DROP INDEX IF EXISTS `idx_ledger_data_evidences_bank_account_id` ON `ledger_data_evidences`;
DROP INDEX IF EXISTS `idx_ledger_data_evidences_employee_id` ON `ledger_data_evidences`;
DROP INDEX IF EXISTS `idx_ledger_data_evidences_project_id` ON `ledger_data_evidences`;
DROP INDEX IF EXISTS `idx_ledger_data_evidences_client_id` ON `ledger_data_evidences`;

ALTER TABLE `ledger_data_evidences`
    DROP COLUMN IF EXISTS `card_name`,
    DROP COLUMN IF EXISTS `bank_account_name`,
    DROP COLUMN IF EXISTS `employee_name`,
    DROP COLUMN IF EXISTS `project_name`,
    DROP COLUMN IF EXISTS `client_name`,
    DROP COLUMN IF EXISTS `card_id`,
    DROP COLUMN IF EXISTS `bank_account_id`,
    DROP COLUMN IF EXISTS `employee_id`;
