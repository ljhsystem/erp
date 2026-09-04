DROP PROCEDURE IF EXISTS `migrate_20260825_06_salary_report_business_context`;
DELIMITER $$
CREATE PROCEDURE `migrate_20260825_06_salary_report_business_context`()
BEGIN
    IF (SELECT COUNT(*) FROM information_schema.TABLES
         WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='ledger_evidence_salary_report') <> 1 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='급여(신고) 증빙원본 테이블을 찾을 수 없습니다.';
    END IF;
    IF (SELECT COUNT(*) FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='ledger_evidence_salary_report'
           AND COLUMN_NAME IN ('client_id','employee_id','project_id','bank_account_id','card_id','team_id')) <> 0 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='급여(신고) 업무 Context가 이미 적용됐거나 부분 적용된 상태입니다.';
    END IF;

    ALTER TABLE `ledger_evidence_salary_report`
        ADD COLUMN `client_id` varchar(36) NULL COMMENT '거래처 식별자' AFTER `operation_type`,
        ADD COLUMN `employee_id` varchar(36) NULL COMMENT '직원 식별자' AFTER `client_id`,
        ADD COLUMN `project_id` varchar(36) NULL COMMENT '프로젝트 식별자' AFTER `employee_id`,
        ADD COLUMN `bank_account_id` varchar(36) NULL COMMENT '계좌 식별자' AFTER `project_id`,
        ADD COLUMN `card_id` varchar(36) NULL COMMENT '카드 식별자' AFTER `bank_account_id`,
        ADD COLUMN `team_id` varchar(36) NULL COMMENT '팀 식별자' AFTER `card_id`;
END$$
DELIMITER ;
CALL `migrate_20260825_06_salary_report_business_context`();
DROP PROCEDURE `migrate_20260825_06_salary_report_business_context`;
