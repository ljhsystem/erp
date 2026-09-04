SET NAMES utf8mb4;

DELIMITER $$
CREATE PROCEDURE migrate_20260903_20_remove_business_income_calculation_note()
BEGIN
    IF EXISTS (SELECT 1 FROM institution_business_income_work_lines)
       OR EXISTS (SELECT 1 FROM ledger_evidence_business_income_work_lines) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='기존 사업소득 작업내역이 있어 산정내역 컬럼을 자동 제거할 수 없습니다.';
    END IF;

    ALTER TABLE ledger_evidence_business_income_work_lines
        DROP COLUMN raw_calculation_note,
        ALGORITHM=INSTANT, LOCK=NONE;

    ALTER TABLE institution_business_income_work_lines
        DROP COLUMN calculation_note,
        ALGORITHM=INSTANT, LOCK=NONE;
END$$
DELIMITER ;

CALL migrate_20260903_20_remove_business_income_calculation_note();
DROP PROCEDURE migrate_20260903_20_remove_business_income_calculation_note;
