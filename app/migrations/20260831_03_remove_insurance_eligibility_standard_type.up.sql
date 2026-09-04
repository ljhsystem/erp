DELIMITER $$
CREATE PROCEDURE migrate_20260831_03_up()
BEGIN
    IF (SELECT COUNT(*) FROM system_statutory_standards WHERE standard_type_code='INSURANCE_ELIGIBILITY') <> 22
       OR (SELECT COUNT(*) FROM system_statutory_standards WHERE policy_component_code='ELIGIBILITY') <> 22
       OR (SELECT COUNT(*) FROM system_statutory_standard_sources source_row JOIN system_statutory_standards standard_row ON standard_row.id=source_row.standard_id WHERE standard_row.policy_component_code='ELIGIBILITY') <> 22 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='구형 Type 삭제 전 Revision·Source 이관 기준선이 다릅니다.';
    END IF;
    IF EXISTS (
        SELECT 1 FROM institution_daily_employment_income_calculation_results result_row
        JOIN system_statutory_standards standard_row ON standard_row.id=result_row.eligibility_revision_id
        WHERE standard_row.standard_type_code='INSURANCE_ELIGIBILITY'
    ) OR EXISTS (
        SELECT 1 FROM system_statutory_standard_sources source_row
        JOIN system_statutory_standards standard_row ON standard_row.id=source_row.standard_id
        WHERE standard_row.standard_type_code='INSURANCE_ELIGIBILITY'
    ) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='구형 가입자격 Revision을 참조하는 자료가 남아 있습니다.';
    END IF;

    DELETE FROM system_statutory_standards WHERE standard_type_code='INSURANCE_ELIGIBILITY';
    IF ROW_COUNT() <> 22 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='구형 가입자격 Revision 22건 삭제에 실패했습니다.';
    END IF;
    DELETE FROM system_codes WHERE code_group='STATUTORY_STANDARD_TYPE' AND code='INSURANCE_ELIGIBILITY';
    IF ROW_COUNT() <> 1 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='구형 가입자격 Type 코드 삭제에 실패했습니다.';
    END IF;

    IF EXISTS (SELECT 1 FROM system_statutory_standards WHERE standard_type_code='INSURANCE_ELIGIBILITY')
       OR EXISTS (SELECT 1 FROM system_codes WHERE code_group='STATUTORY_STANDARD_TYPE' AND code='INSURANCE_ELIGIBILITY') THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='구형 가입자격 Type 물리 삭제가 완료되지 않았습니다.';
    END IF;
END$$
CALL migrate_20260831_03_up()$$
DROP PROCEDURE migrate_20260831_03_up$$
DELIMITER ;
