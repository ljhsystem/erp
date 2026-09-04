SET NAMES utf8mb4;
DROP PROCEDURE IF EXISTS rollback_20260826_06;
DELIMITER $$
CREATE PROCEDURE rollback_20260826_06()
BEGIN
    IF (SELECT COUNT(*) FROM ledger_evidence_daily_employment_income) > 0
       OR (SELECT COUNT(*) FROM institution_daily_employment_income_lines) > 0
       OR (SELECT COUNT(*) FROM institution_daily_employment_income_workdays) > 0
       OR (SELECT COUNT(*) FROM institution_daily_employment_income_items) > 0
       OR (SELECT COUNT(*) FROM institution_daily_employment_incomes) > 0
       OR (SELECT COUNT(*) FROM institution_daily_worker_social_insurance_coverages) > 0
       OR (SELECT COUNT(*) FROM institution_social_insurance_workplaces) > 0
       OR (SELECT COUNT(*) FROM system_work_team_assignments) > 0 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = '일용근로소득 운영자료가 있어 Down을 중단합니다.';
    END IF;
END$$
DELIMITER ;
CALL rollback_20260826_06();
DROP PROCEDURE rollback_20260826_06;
DROP TABLE ledger_evidence_daily_employment_income;
DROP TABLE institution_daily_employment_income_lines;
DROP TABLE institution_daily_employment_income_workdays;
DROP TABLE institution_daily_employment_income_items;
DROP TABLE institution_daily_employment_incomes;
DROP TABLE institution_daily_worker_social_insurance_coverages;
DROP TABLE institution_social_insurance_workplaces;
DROP TABLE system_work_team_assignments;
