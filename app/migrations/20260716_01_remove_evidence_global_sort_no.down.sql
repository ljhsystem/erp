-- 제거된 전체순번 값과 전역 유일성은 복원할 수 없다.
-- 구조 롤백이 필요한 경우 nullable 호환 컬럼만 복원한다.

ALTER TABLE IF EXISTS `ledger_evidence_bank` ADD COLUMN IF NOT EXISTS `evidence_sort_no` BIGINT UNSIGNED NULL AFTER `sort_no`;
ALTER TABLE IF EXISTS `ledger_evidence_bank_transaction` ADD COLUMN IF NOT EXISTS `evidence_sort_no` BIGINT UNSIGNED NULL AFTER `sort_no`;
ALTER TABLE IF EXISTS `ledger_evidence_tax_invoice` ADD COLUMN IF NOT EXISTS `evidence_sort_no` BIGINT UNSIGNED NULL AFTER `sort_no`;
ALTER TABLE IF EXISTS `ledger_evidence_tax_invoice_manual` ADD COLUMN IF NOT EXISTS `evidence_sort_no` BIGINT UNSIGNED NULL AFTER `sort_no`;
ALTER TABLE IF EXISTS `ledger_evidence_cash_receipt` ADD COLUMN IF NOT EXISTS `evidence_sort_no` BIGINT UNSIGNED NULL AFTER `sort_no`;
ALTER TABLE IF EXISTS `ledger_evidence_card_purchase` ADD COLUMN IF NOT EXISTS `evidence_sort_no` BIGINT UNSIGNED NULL AFTER `sort_no`;
ALTER TABLE IF EXISTS `ledger_evidence_card_hometax` ADD COLUMN IF NOT EXISTS `evidence_sort_no` BIGINT UNSIGNED NULL AFTER `sort_no`;
ALTER TABLE IF EXISTS `ledger_evidence_card_statement` ADD COLUMN IF NOT EXISTS `evidence_sort_no` BIGINT UNSIGNED NULL AFTER `sort_no`;
ALTER TABLE IF EXISTS `ledger_evidence_card_sales` ADD COLUMN IF NOT EXISTS `evidence_sort_no` BIGINT UNSIGNED NULL AFTER `sort_no`;
ALTER TABLE IF EXISTS `ledger_evidence_employee_expense` ADD COLUMN IF NOT EXISTS `evidence_sort_no` BIGINT UNSIGNED NULL AFTER `sort_no`;
ALTER TABLE IF EXISTS `ledger_evidence_payroll` ADD COLUMN IF NOT EXISTS `evidence_sort_no` BIGINT UNSIGNED NULL AFTER `sort_no`;
ALTER TABLE IF EXISTS `ledger_evidence_daily_worker` ADD COLUMN IF NOT EXISTS `evidence_sort_no` BIGINT UNSIGNED NULL AFTER `sort_no`;
ALTER TABLE IF EXISTS `ledger_evidence_business_income` ADD COLUMN IF NOT EXISTS `evidence_sort_no` BIGINT UNSIGNED NULL AFTER `sort_no`;
ALTER TABLE IF EXISTS `ledger_evidence_cash_sales` ADD COLUMN IF NOT EXISTS `evidence_sort_no` BIGINT UNSIGNED NULL AFTER `sort_no`;
ALTER TABLE IF EXISTS `ledger_evidence_business_data` ADD COLUMN IF NOT EXISTS `evidence_sort_no` BIGINT UNSIGNED NULL AFTER `sort_no`;
