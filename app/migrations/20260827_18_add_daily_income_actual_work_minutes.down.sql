DELIMITER $$
CREATE PROCEDURE migrate_20260827_18_down()
BEGIN
    IF EXISTS (
        SELECT 1 FROM institution_daily_employment_income_workdays
        WHERE actual_work_minutes IS NOT NULL LIMIT 1
    ) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='확정 근로시간 자료가 있어 actual_work_minutes를 제거할 수 없습니다.';
    END IF;
    ALTER TABLE institution_daily_employment_income_workdays
        DROP CONSTRAINT ck_daily_workday_actual_minutes,
        DROP COLUMN actual_work_minutes;
END$$
CALL migrate_20260827_18_down()$$
DROP PROCEDURE migrate_20260827_18_down$$
DELIMITER ;
