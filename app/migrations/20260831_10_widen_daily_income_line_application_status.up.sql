DELIMITER $$
CREATE PROCEDURE migrate_20260831_10_up()
BEGIN
    IF (SELECT COUNT(*) FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='institution_daily_employment_income_lines'
          AND COLUMN_NAME='application_status_code' AND COLUMN_TYPE='varchar(20)' AND IS_NULLABLE='YES') <> 1 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='일용근로소득 Line 적용상태 기준선이 다릅니다.';
    END IF;
    ALTER TABLE institution_daily_employment_income_lines DROP CONSTRAINT ck_daily_line_application;
    ALTER TABLE institution_daily_employment_income_lines
        MODIFY COLUMN application_status_code VARCHAR(30) NULL COMMENT '적용상태',
        ADD CONSTRAINT ck_daily_line_application CHECK (
            application_status_code IS NULL
            OR application_status_code IN ('APPLICABLE','EXCLUDED','NOT_APPLICABLE','CONFIRMATION_REQUIRED')
        );
END$$
CALL migrate_20260831_10_up()$$
DROP PROCEDURE migrate_20260831_10_up$$
DELIMITER ;
