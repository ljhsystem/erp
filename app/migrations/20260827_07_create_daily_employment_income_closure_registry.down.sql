SET NAMES utf8mb4;

DELIMITER $$
CREATE PROCEDURE rollback_20260827_07_daily_income_closure_registry()
BEGIN
    IF EXISTS (SELECT 1 FROM institution_daily_employment_income_closures LIMIT 1)
       OR EXISTS (SELECT 1 FROM institution_daily_employment_income_accounting_links LIMIT 1) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='일용근로소득 Closure 업무데이터가 있어 Down할 수 없습니다.';
    END IF;
    IF EXISTS (SELECT 1 FROM institution_daily_employment_incomes WHERE status_code='WITHDRAWN' LIMIT 1) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='회수 상태 문서가 있어 Down할 수 없습니다.';
    END IF;
    DROP TABLE institution_daily_employment_income_accounting_links;
    DROP TABLE institution_daily_employment_income_closures;
    ALTER TABLE institution_daily_employment_incomes DROP CONSTRAINT ck_daily_income_status;
    ALTER TABLE institution_daily_employment_incomes ADD CONSTRAINT ck_daily_income_status CHECK (status_code IN ('DRAFT','PENDING','APPROVED','REJECTED'));
END$$
DELIMITER ;

CALL rollback_20260827_07_daily_income_closure_registry();
DROP PROCEDURE rollback_20260827_07_daily_income_closure_registry;
