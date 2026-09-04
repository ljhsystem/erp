DELIMITER $$
CREATE PROCEDURE migrate_20260829_09_up()
BEGIN
    IF NOT EXISTS (
        SELECT 1
        FROM information_schema.REFERENTIAL_CONSTRAINTS rc
        JOIN information_schema.KEY_COLUMN_USAGE kcu
          ON kcu.CONSTRAINT_SCHEMA=rc.CONSTRAINT_SCHEMA
         AND kcu.CONSTRAINT_NAME=rc.CONSTRAINT_NAME
         AND kcu.TABLE_NAME=rc.TABLE_NAME
        WHERE rc.CONSTRAINT_SCHEMA=DATABASE()
          AND rc.TABLE_NAME='institution_daily_employment_income_line_backfill_audits'
          AND rc.CONSTRAINT_NAME='fk_daily_income_line_backfill_line'
          AND rc.REFERENCED_TABLE_NAME='institution_daily_employment_income_lines'
          AND rc.DELETE_RULE='RESTRICT'
          AND rc.UPDATE_RULE='CASCADE'
          AND kcu.COLUMN_NAME='daily_employment_income_line_id'
          AND kcu.REFERENCED_COLUMN_NAME='id'
    ) THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT='분리 대상 일용근로소득 Line 백필 감사 FK 계약이 일치하지 않습니다.';
    END IF;

    ALTER TABLE institution_daily_employment_income_line_backfill_audits
        DROP FOREIGN KEY fk_daily_income_line_backfill_line;
END$$
CALL migrate_20260829_09_up()$$
DROP PROCEDURE migrate_20260829_09_up$$
DELIMITER ;
