SET NAMES utf8mb4;

DELIMITER $$
CREATE PROCEDURE migrate_20260827_03_daily_income_sort_no()
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.TABLES
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'institution_daily_employment_incomes'
    ) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = '일용근로소득 헤더 테이블을 찾을 수 없습니다.';
    END IF;

    IF EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'institution_daily_employment_incomes'
          AND COLUMN_NAME = 'sort_no'
    ) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = '일용근로소득 순번 컬럼이 이미 존재합니다.';
    END IF;

    ALTER TABLE institution_daily_employment_incomes
        ADD COLUMN sort_no BIGINT UNSIGNED NOT NULL COMMENT '순번' AFTER id,
        ADD UNIQUE KEY uk_daily_employment_income_sort_no (sort_no);
END$$
DELIMITER ;

CALL migrate_20260827_03_daily_income_sort_no();
DROP PROCEDURE migrate_20260827_03_daily_income_sort_no;
