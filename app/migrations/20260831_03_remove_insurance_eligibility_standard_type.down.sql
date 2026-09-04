DELIMITER $$
CREATE PROCEDURE migrate_20260831_03_down()
BEGIN
    IF EXISTS (SELECT 1 FROM system_codes WHERE code_group='STATUTORY_STANDARD_TYPE' AND code='INSURANCE_ELIGIBILITY')
       OR EXISTS (SELECT 1 FROM system_statutory_standards WHERE standard_type_code='INSURANCE_ELIGIBILITY') THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='구형 가입자격 Type 또는 Revision이 이미 존재합니다.';
    END IF;
    IF (SELECT COUNT(*) FROM system_statutory_standards WHERE id LIKE '20260831-10%' AND policy_component_code='ELIGIBILITY') <> 22 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='구형 가입자격 Revision 복원 원본 22건이 없습니다.';
    END IF;

    INSERT INTO system_codes(id,sort_no,code_group,group_name,code,code_name,note,is_active,extra_data,created_at,created_by,updated_at,updated_by)
    SELECT '20260829-0300-4000-8000-000000000001',COALESCE(MAX(sort_no),0)+1,'STATUTORY_STANDARD_TYPE','법정기준 종류','INSURANCE_ELIGIBILITY','사회보험 가입자격','보험료율 계산 전 근로형태·근무범위별 가입자격 판정 SSOT',1,
           JSON_OBJECT('fields',JSON_ARRAY(),'preserve_schema_in_value',TRUE,'allow_dimension_overlap',TRUE),NOW(),'SYSTEM:MIGRATION',NOW(),'SYSTEM:MIGRATION'
      FROM system_codes;

    INSERT INTO system_statutory_standards(id,sort_no,standard_type_code,policy_component_code,employment_type_code,work_scope_code,additional_dimension_data,additional_dimension_key,effective_from,effective_to,value_data,note,created_at,created_by,updated_at,updated_by)
    SELECT CONCAT('20260829-03',RIGHT(SUBSTRING_INDEX(SUBSTRING_INDEX(integrated.id,'-',2),'-',-1),2),'-4000-8000-',RIGHT(integrated.id,12)),
           integrated.sort_no,
           'INSURANCE_ELIGIBILITY',NULL,NULL,NULL,NULL,NULL,
           integrated.effective_from,integrated.effective_to,integrated.value_data,
           TRIM(LEADING '[보험별 가입자격 이관] ' FROM integrated.note),
           integrated.created_at,integrated.created_by,integrated.updated_at,integrated.updated_by
      FROM system_statutory_standards integrated
     WHERE integrated.id LIKE '20260831-10%' AND integrated.policy_component_code='ELIGIBILITY'
     ORDER BY integrated.id;

    IF (SELECT COUNT(*) FROM system_statutory_standards WHERE standard_type_code='INSURANCE_ELIGIBILITY') <> 22 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='구형 가입자격 Revision 22건 복원에 실패했습니다.';
    END IF;
END$$
CALL migrate_20260831_03_down()$$
DROP PROCEDURE migrate_20260831_03_down$$
DELIMITER ;
