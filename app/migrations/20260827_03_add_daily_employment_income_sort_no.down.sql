SET NAMES utf8mb4;

DELIMITER $$
CREATE PROCEDURE rollback_20260827_03_daily_income_sort_no()
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'institution_daily_employment_incomes'
          AND COLUMN_NAME = 'sort_no'
    ) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = '일용근로소득 순번 컬럼을 찾을 수 없습니다.';
    END IF;

    IF EXISTS (SELECT 1 FROM institution_daily_employment_incomes LIMIT 1) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = '일용근로소득 운영자료가 있어 순번 컬럼 Down을 중단합니다.';
    END IF;

    ALTER TABLE institution_daily_employment_incomes
        DROP INDEX uk_daily_employment_income_sort_no,
        DROP COLUMN sort_no;
END$$
DELIMITER ;

CALL rollback_20260827_03_daily_income_sort_no();
DROP PROCEDURE rollback_20260827_03_daily_income_sort_no;
