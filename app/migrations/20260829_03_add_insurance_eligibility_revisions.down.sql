DROP PROCEDURE IF EXISTS migrate_20260829_03_down;
DELIMITER $$
CREATE PROCEDURE migrate_20260829_03_down()
BEGIN
    IF EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='institution_daily_employment_income_calculation_results' AND COLUMN_NAME='eligibility_revision_id') THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='가입자격 Snapshot 또는 후속 Closure 구조가 있어 Down을 중단합니다.';
    END IF;
    DELETE FROM system_statutory_standard_sources WHERE standard_id IN (SELECT id FROM system_statutory_standards WHERE standard_type_code='INSURANCE_ELIGIBILITY');
    DELETE FROM system_statutory_standards WHERE standard_type_code='INSURANCE_ELIGIBILITY';
    DELETE FROM system_codes WHERE code_group='STATUTORY_STANDARD_TYPE' AND code='INSURANCE_ELIGIBILITY';
END$$
CALL migrate_20260829_03_down()$$
DROP PROCEDURE migrate_20260829_03_down$$
DELIMITER ;
