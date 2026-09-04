DROP PROCEDURE IF EXISTS `rollback_20260825_06_salary_report_business_context`;
DELIMITER $$
CREATE PROCEDURE `rollback_20260825_06_salary_report_business_context`()
BEGIN
    IF (SELECT COUNT(*) FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='ledger_evidence_salary_report'
           AND COLUMN_NAME IN ('client_id','employee_id','project_id','bank_account_id','card_id','team_id')) <> 6 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='급여(신고) 업무 Context가 완전 적용된 상태가 아닙니다.';
    END IF;
    IF EXISTS (SELECT 1 FROM `ledger_evidence_salary_report`
                WHERE `client_id` IS NOT NULL OR `employee_id` IS NOT NULL OR `project_id` IS NOT NULL
                   OR `bank_account_id` IS NOT NULL OR `card_id` IS NOT NULL OR `team_id` IS NOT NULL) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='급여(신고) 업무 Context 사용 데이터가 존재하여 Down을 차단했습니다.';
    END IF;

    ALTER TABLE `ledger_evidence_salary_report`
        DROP COLUMN `team_id`,
        DROP COLUMN `card_id`,
        DROP COLUMN `bank_account_id`,
        DROP COLUMN `project_id`,
        DROP COLUMN `employee_id`,
        DROP COLUMN `client_id`;
END$$
DELIMITER ;
CALL `rollback_20260825_06_salary_report_business_context`();
DROP PROCEDURE `rollback_20260825_06_salary_report_business_context`;
