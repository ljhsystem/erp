SET NAMES utf8mb4;

DELIMITER $$
CREATE PROCEDURE rollback_20260827_12_daily_income_item_description()
BEGIN
    IF EXISTS (SELECT 1 FROM institution_daily_employment_incomes LIMIT 1) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='일용근로소득 문서가 있어 문서번호·그룹 작업내용 구조로 Down할 수 없습니다.';
    END IF;

    ALTER TABLE institution_daily_employment_incomes
        ADD COLUMN document_number VARCHAR(50) NOT NULL AFTER payment_date,
        ADD UNIQUE KEY uq_daily_income_document_number (company_id,document_number);

    ALTER TABLE institution_daily_employment_income_groups
        ADD COLUMN work_description VARCHAR(500) NOT NULL AFTER work_team_id,
        ADD CONSTRAINT ck_daily_income_group_description CHECK (CHAR_LENGTH(TRIM(work_description)) > 0);

    ALTER TABLE institution_daily_employment_income_items
        DROP CONSTRAINT ck_daily_income_item_description,
        DROP INDEX idx_daily_income_item_work_type,
        DROP COLUMN work_description,
        DROP COLUMN work_type_code;
END$$
DELIMITER ;

CALL rollback_20260827_12_daily_income_item_description();
DROP PROCEDURE rollback_20260827_12_daily_income_item_description;
