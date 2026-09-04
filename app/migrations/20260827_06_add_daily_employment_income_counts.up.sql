SET NAMES utf8mb4;

DELIMITER $$
CREATE PROCEDURE migrate_20260827_06_daily_income_counts()
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
          AND COLUMN_NAME IN ('worker_count', 'work_team_count')
    ) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = '일용근로소득 인원·작업팀 집계 컬럼이 이미 존재합니다.';
    END IF;

    ALTER TABLE institution_daily_employment_incomes
        ADD COLUMN worker_count INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '작업자 수(거래처)' AFTER document_title,
        ADD COLUMN work_team_count INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '소속팀 수(팀)' AFTER worker_count;

    UPDATE institution_daily_employment_incomes h
    SET h.worker_count = (
            SELECT COUNT(DISTINCT i.worker_client_id)
            FROM institution_daily_employment_income_items i
            WHERE i.daily_employment_income_id = h.id
        ),
        h.work_team_count = (
            SELECT COUNT(DISTINCT i.work_team_id)
            FROM institution_daily_employment_income_items i
            WHERE i.daily_employment_income_id = h.id
        );
END$$
DELIMITER ;

CALL migrate_20260827_06_daily_income_counts();
DROP PROCEDURE migrate_20260827_06_daily_income_counts;
