DELIMITER $$
CREATE PROCEDURE migrate_20260827_18_up()
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.TABLES
        WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='institution_daily_employment_income_workdays'
    ) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='일용근로소득 Workday 테이블이 없습니다.';
    END IF;
    IF EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='institution_daily_employment_income_workdays'
          AND COLUMN_NAME='actual_work_minutes'
    ) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='actual_work_minutes 컬럼이 이미 존재합니다.';
    END IF;

    ALTER TABLE institution_daily_employment_income_workdays
        ADD COLUMN actual_work_minutes SMALLINT UNSIGNED NULL
            COMMENT '실제 근로시간(분), NULL은 과거자료 미확인' AFTER work_date,
        ADD CONSTRAINT ck_daily_workday_actual_minutes
            CHECK (actual_work_minutes IS NULL OR actual_work_minutes BETWEEN 1 AND 1440);
END$$
CALL migrate_20260827_18_up()$$
DROP PROCEDURE migrate_20260827_18_up$$
DELIMITER ;
