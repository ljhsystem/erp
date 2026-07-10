ALTER TABLE `ledger_vouchers`
    DROP COLUMN IF EXISTS `summary_line_summary`,
    DROP COLUMN IF EXISTS `summary_employee_id`,
    DROP COLUMN IF EXISTS `summary_card_id`,
    DROP COLUMN IF EXISTS `summary_bank_account_id`,
    DROP COLUMN IF EXISTS `summary_project_id`,
    DROP COLUMN IF EXISTS `summary_client_id`,
    DROP COLUMN IF EXISTS `summary_account_id`,
    DROP COLUMN IF EXISTS `line_count`,
    DROP COLUMN IF EXISTS `credit_total`,
    DROP COLUMN IF EXISTS `debit_total`;
