SET NAMES utf8mb4;

DELIMITER $$
CREATE PROCEDURE rollback_20260827_14_daily_income_group_description()
BEGIN
    IF EXISTS (SELECT 1 FROM institution_daily_employment_income_groups LIMIT 1) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='일용근로소득 근무그룹이 있어 그룹 작업내용을 무손실로 제거할 수 없습니다.';
    END IF;

    ALTER TABLE institution_daily_employment_income_groups
        DROP CONSTRAINT ck_daily_income_group_description,
        DROP COLUMN work_description;
END$$
DELIMITER ;

CALL rollback_20260827_14_daily_income_group_description();
DROP PROCEDURE rollback_20260827_14_daily_income_group_description;
