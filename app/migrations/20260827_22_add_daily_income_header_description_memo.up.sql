DELIMITER $$
CREATE PROCEDURE migrate_20260827_22_up()
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.TABLES
        WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='institution_daily_employment_incomes'
    ) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='일용근로소득 Header 테이블이 없습니다.';
    END IF;
    IF EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='institution_daily_employment_incomes'
          AND COLUMN_NAME IN ('description','memo')
    ) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='일용근로소득 비고 또는 메모 컬럼이 이미 존재합니다.';
    END IF;

    ALTER TABLE institution_daily_employment_incomes
        ADD COLUMN description VARCHAR(500) NULL COMMENT '비고' AFTER document_title,
        ADD COLUMN memo TEXT NULL COMMENT '메모' AFTER description;
END$$
CALL migrate_20260827_22_up()$$
DROP PROCEDURE migrate_20260827_22_up$$
DELIMITER ;
