DELIMITER $$
CREATE PROCEDURE migrate_20260829_08_up()
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA=DATABASE()
          AND TABLE_NAME='institution_daily_employment_income_workdays'
          AND COLUMN_NAME='adjustment_amount'
    ) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='일용근로 Workday 조정금액 컬럼이 존재하지 않습니다.';
    END IF;

    IF EXISTS (
        SELECT 1 FROM institution_daily_employment_income_workdays
        WHERE adjustment_amount<>0
    ) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='0원이 아닌 Workday 조정금액이 있어 삭제할 수 없습니다.';
    END IF;

    IF EXISTS (
        SELECT 1 FROM institution_daily_employment_income_lines
        WHERE line_type_code='PAY'
          AND line_code='PAY_ADJUSTMENT'
          AND (COALESCE(calculated_amount,0)<>0 OR COALESCE(final_amount,0)<>0)
    ) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='0원이 아닌 지급 조정 Line이 있어 삭제할 수 없습니다.';
    END IF;

    IF EXISTS (
        SELECT 1 FROM information_schema.TABLES
        WHERE TABLE_SCHEMA=DATABASE()
          AND TABLE_NAME='institution_daily_employment_income_line_backfill_audits'
    ) THEN
        DELETE audit_row
        FROM institution_daily_employment_income_line_backfill_audits audit_row
        JOIN institution_daily_employment_income_lines line_row
          ON line_row.id=audit_row.daily_employment_income_line_id
        WHERE line_row.line_type_code='PAY' AND line_row.line_code='PAY_ADJUSTMENT';
    END IF;

    DELETE FROM institution_daily_employment_income_lines
    WHERE line_type_code='PAY' AND line_code='PAY_ADJUSTMENT';

    ALTER TABLE institution_daily_employment_income_workdays
        DROP COLUMN adjustment_amount;
END$$
CALL migrate_20260829_08_up()$$
DROP PROCEDURE migrate_20260829_08_up$$
DELIMITER ;
