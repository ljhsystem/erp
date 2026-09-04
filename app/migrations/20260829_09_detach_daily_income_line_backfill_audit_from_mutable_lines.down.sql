DELIMITER $$
CREATE PROCEDURE migrate_20260829_09_down()
BEGIN
    IF EXISTS (
        SELECT 1
        FROM information_schema.TABLE_CONSTRAINTS
        WHERE CONSTRAINT_SCHEMA=DATABASE()
          AND TABLE_NAME='institution_daily_employment_income_line_backfill_audits'
          AND CONSTRAINT_NAME='fk_daily_income_line_backfill_line'
          AND CONSTRAINT_TYPE='FOREIGN KEY'
    ) THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT='일용근로소득 Line 백필 감사 FK가 이미 존재합니다.';
    END IF;

    IF EXISTS (
        SELECT 1
        FROM institution_daily_employment_income_line_backfill_audits audit_row
        LEFT JOIN institution_daily_employment_income_lines line_row
          ON line_row.id=audit_row.daily_employment_income_line_id
        WHERE line_row.id IS NULL
    ) THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT='삭제된 원 Line을 참조하는 감사자료가 있어 Down할 수 없습니다.';
    END IF;

    ALTER TABLE institution_daily_employment_income_line_backfill_audits
        ADD CONSTRAINT fk_daily_income_line_backfill_line
        FOREIGN KEY (daily_employment_income_line_id)
        REFERENCES institution_daily_employment_income_lines(id)
        ON DELETE RESTRICT ON UPDATE CASCADE;
END$$
CALL migrate_20260829_09_down()$$
DROP PROCEDURE migrate_20260829_09_down$$
DELIMITER ;
