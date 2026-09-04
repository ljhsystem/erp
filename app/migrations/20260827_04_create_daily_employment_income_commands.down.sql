SET NAMES utf8mb4;

DELIMITER $$
CREATE PROCEDURE rollback_20260827_04_daily_income_commands()
BEGIN
    IF EXISTS (SELECT 1 FROM institution_daily_employment_income_commands LIMIT 1) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = '일용근로소득 저장명령이 있어 Down을 중단합니다.';
    END IF;
    DROP TABLE institution_daily_employment_income_commands;
END$$
DELIMITER ;

CALL rollback_20260827_04_daily_income_commands();
DROP PROCEDURE rollback_20260827_04_daily_income_commands;
