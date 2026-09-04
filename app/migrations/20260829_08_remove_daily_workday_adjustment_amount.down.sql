DELIMITER $$
CREATE PROCEDURE migrate_20260829_08_down()
BEGIN
    IF EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA=DATABASE()
          AND TABLE_NAME='institution_daily_employment_income_workdays'
          AND COLUMN_NAME='adjustment_amount'
    ) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='일용근로 Workday 조정금액 컬럼이 이미 존재합니다.';
    END IF;

    ALTER TABLE institution_daily_employment_income_workdays
        ADD COLUMN adjustment_amount DECIMAL(18,2) NOT NULL DEFAULT 0
        AFTER allowance_amount;
END$$
CALL migrate_20260829_08_down()$$
DROP PROCEDURE migrate_20260829_08_down$$
DELIMITER ;
