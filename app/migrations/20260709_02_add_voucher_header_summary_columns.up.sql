ALTER TABLE `ledger_vouchers`
    ADD COLUMN IF NOT EXISTS `debit_total` DECIMAL(18,2) NOT NULL DEFAULT 0.00 COMMENT '차변합계' AFTER `summary`,
    ADD COLUMN IF NOT EXISTS `credit_total` DECIMAL(18,2) NOT NULL DEFAULT 0.00 COMMENT '대변합계' AFTER `debit_total`,
    ADD COLUMN IF NOT EXISTS `line_count` INT NOT NULL DEFAULT 0 COMMENT '분개라인수' AFTER `credit_total`,
    ADD COLUMN IF NOT EXISTS `summary_account_id` CHAR(36) NULL DEFAULT NULL COMMENT '대표 계정과목' AFTER `line_count`,
    ADD COLUMN IF NOT EXISTS `summary_client_id` CHAR(36) NULL DEFAULT NULL COMMENT '대표 거래처' AFTER `summary_account_id`,
    ADD COLUMN IF NOT EXISTS `summary_project_id` CHAR(36) NULL DEFAULT NULL COMMENT '대표 프로젝트' AFTER `summary_client_id`,
    ADD COLUMN IF NOT EXISTS `summary_bank_account_id` CHAR(36) NULL DEFAULT NULL COMMENT '대표 은행계좌' AFTER `summary_project_id`,
    ADD COLUMN IF NOT EXISTS `summary_card_id` CHAR(36) NULL DEFAULT NULL COMMENT '대표 카드' AFTER `summary_bank_account_id`,
    ADD COLUMN IF NOT EXISTS `summary_employee_id` CHAR(36) NULL DEFAULT NULL COMMENT '대표 직원' AFTER `summary_card_id`,
    ADD COLUMN IF NOT EXISTS `summary_line_summary` VARCHAR(255) NULL DEFAULT NULL COMMENT '대표 라인적요' AFTER `summary_employee_id`;
