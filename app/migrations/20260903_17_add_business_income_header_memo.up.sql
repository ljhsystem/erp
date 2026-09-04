SET NAMES utf8mb4;

DELIMITER $$
CREATE PROCEDURE migrate_20260903_17_add_business_income_header_memo()
BEGIN
    IF EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA=DATABASE()
          AND TABLE_NAME='institution_business_incomes'
          AND COLUMN_NAME='memo'
    ) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='사업소득 Header 메모 컬럼이 이미 존재합니다.';
    END IF;

    ALTER TABLE institution_business_incomes
        ADD COLUMN memo TEXT NULL COMMENT '메모' AFTER description,
        ALGORITHM=INSTANT, LOCK=NONE;
END$$
DELIMITER ;

CALL migrate_20260903_17_add_business_income_header_memo();
DROP PROCEDURE migrate_20260903_17_add_business_income_header_memo;
