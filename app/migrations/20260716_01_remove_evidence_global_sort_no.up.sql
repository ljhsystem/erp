-- 생성센터 폐기 후 자료유형을 가로지르는 증빙 전체순번을 제거한다.
-- 각 증빙원본 테이블 내부의 정렬 SSOT는 sort_no를 유지한다.

ALTER TABLE IF EXISTS `ledger_evidence_bank` DROP COLUMN IF EXISTS `evidence_sort_no`;
ALTER TABLE IF EXISTS `ledger_evidence_bank_transaction` DROP COLUMN IF EXISTS `evidence_sort_no`;
ALTER TABLE IF EXISTS `ledger_evidence_tax_invoice` DROP COLUMN IF EXISTS `evidence_sort_no`;
ALTER TABLE IF EXISTS `ledger_evidence_tax_invoice_manual` DROP COLUMN IF EXISTS `evidence_sort_no`;
ALTER TABLE IF EXISTS `ledger_evidence_cash_receipt` DROP COLUMN IF EXISTS `evidence_sort_no`;
ALTER TABLE IF EXISTS `ledger_evidence_card_purchase` DROP COLUMN IF EXISTS `evidence_sort_no`;
ALTER TABLE IF EXISTS `ledger_evidence_card_hometax` DROP COLUMN IF EXISTS `evidence_sort_no`;
ALTER TABLE IF EXISTS `ledger_evidence_card_statement` DROP COLUMN IF EXISTS `evidence_sort_no`;
ALTER TABLE IF EXISTS `ledger_evidence_card_sales` DROP COLUMN IF EXISTS `evidence_sort_no`;
ALTER TABLE IF EXISTS `ledger_evidence_employee_expense` DROP COLUMN IF EXISTS `evidence_sort_no`;
ALTER TABLE IF EXISTS `ledger_evidence_payroll` DROP COLUMN IF EXISTS `evidence_sort_no`;
ALTER TABLE IF EXISTS `ledger_evidence_daily_worker` DROP COLUMN IF EXISTS `evidence_sort_no`;
ALTER TABLE IF EXISTS `ledger_evidence_business_income` DROP COLUMN IF EXISTS `evidence_sort_no`;
ALTER TABLE IF EXISTS `ledger_evidence_cash_sales` DROP COLUMN IF EXISTS `evidence_sort_no`;
ALTER TABLE IF EXISTS `ledger_evidence_business_data` DROP COLUMN IF EXISTS `evidence_sort_no`;
