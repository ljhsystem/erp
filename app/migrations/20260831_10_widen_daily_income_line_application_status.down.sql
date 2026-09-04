DELIMITER $$
CREATE PROCEDURE migrate_20260831_10_down()
BEGIN
    IF EXISTS (
        SELECT 1 FROM institution_daily_employment_income_lines
        WHERE application_status_code NOT IN ('APPLICABLE','EXCLUDED','NOT_APPLICABLE')
        LIMIT 1
    ) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='확인 필요 적용상태 Line이 있어 Down할 수 없습니다.';
    END IF;
    ALTER TABLE institution_daily_employment_income_lines DROP CONSTRAINT ck_daily_line_application;
    ALTER TABLE institution_daily_employment_income_lines
        MODIFY COLUMN application_status_code VARCHAR(20) NULL COMMENT '적용상태',
        ADD CONSTRAINT ck_daily_line_application CHECK (
            application_status_code IS NULL
            OR application_status_code IN ('APPLICABLE','EXCLUDED','NOT_APPLICABLE')
        );
END$$
CALL migrate_20260831_10_down()$$
DROP PROCEDURE migrate_20260831_10_down$$
DELIMITER ;
