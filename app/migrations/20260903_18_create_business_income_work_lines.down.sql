SET NAMES utf8mb4;

DELIMITER $$
CREATE PROCEDURE rollback_20260903_18_create_business_income_work_lines()
BEGIN
    IF EXISTS (SELECT 1 FROM ledger_evidence_business_income_work_lines)
       OR EXISTS (SELECT 1 FROM institution_business_income_work_lines) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='사업소득 작업내역 데이터가 있어 자동 제거할 수 없습니다.';
    END IF;

    DROP TABLE ledger_evidence_business_income_work_lines;
    DROP TABLE institution_business_income_work_lines;

    ALTER TABLE ledger_evidence_business_income
        DROP COLUMN raw_other_deduction_reason,
        ALGORITHM=INSTANT, LOCK=NONE;

    ALTER TABLE institution_business_income_items
        DROP COLUMN other_deduction_reason,
        ALGORITHM=INSTANT, LOCK=NONE;
END$$
DELIMITER ;

CALL rollback_20260903_18_create_business_income_work_lines();
DROP PROCEDURE rollback_20260903_18_create_business_income_work_lines;
