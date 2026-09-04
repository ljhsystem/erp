DELIMITER $$
CREATE PROCEDURE migrate_20260831_01_up()
BEGIN
    IF EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='system_statutory_standards'
          AND COLUMN_NAME IN ('policy_component_code','employment_type_code','work_scope_code','additional_dimension_data','additional_dimension_key')
    ) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='보험 법정기준 Dimension 컬럼이 이미 존재합니다.';
    END IF;
    IF (SELECT COUNT(*) FROM system_statutory_standards WHERE standard_type_code='INSURANCE_ELIGIBILITY') <> 22
       OR (SELECT COUNT(*) FROM system_statutory_standard_sources source_row JOIN system_statutory_standards standard_row ON standard_row.id=source_row.standard_id WHERE standard_row.standard_type_code='INSURANCE_ELIGIBILITY') <> 22 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='기존 가입자격 Revision 또는 Source 기준선이 다릅니다.';
    END IF;

    ALTER TABLE system_statutory_standards
      ADD COLUMN policy_component_code VARCHAR(20) NULL AFTER standard_type_code,
      ADD COLUMN employment_type_code VARCHAR(20) NULL AFTER policy_component_code,
      ADD COLUMN work_scope_code VARCHAR(30) NULL AFTER employment_type_code,
      ADD COLUMN additional_dimension_data LONGTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NULL AFTER work_scope_code,
      ADD COLUMN additional_dimension_key CHAR(64) NULL AFTER additional_dimension_data,
      ADD KEY idx_statutory_standard_component_resolve(standard_type_code,policy_component_code,employment_type_code,work_scope_code,additional_dimension_key,effective_from,effective_to),
      ADD CONSTRAINT ck_statutory_standard_policy_component CHECK(policy_component_code IS NULL OR policy_component_code IN('PREMIUM','ELIGIBILITY')),
      ADD CONSTRAINT ck_statutory_standard_employment_type CHECK(employment_type_code IS NULL OR employment_type_code IN('ALL','REGULAR','DAILY')),
      ADD CONSTRAINT ck_statutory_standard_work_scope CHECK(work_scope_code IS NULL OR work_scope_code IN('ALL','HEAD_OFFICE','CONSTRUCTION_SITE')),
      ADD CONSTRAINT ck_statutory_standard_additional_dimension_json CHECK(additional_dimension_data IS NULL OR JSON_VALID(additional_dimension_data)),
      ADD CONSTRAINT ck_statutory_standard_additional_dimension_key CHECK(additional_dimension_key IS NULL OR additional_dimension_key REGEXP '^[0-9a-f]{64}$');

    UPDATE system_statutory_standards
       SET policy_component_code='PREMIUM', employment_type_code='ALL', work_scope_code='ALL',
           additional_dimension_data=JSON_OBJECT(), additional_dimension_key=SHA2('{}',256)
     WHERE standard_type_code IN('NATIONAL_PENSION','HEALTH_INSURANCE','LONG_TERM_CARE','EMPLOYMENT_INSURANCE','INDUSTRIAL_ACCIDENT');

    INSERT INTO system_statutory_standards(
        id,sort_no,standard_type_code,policy_component_code,employment_type_code,work_scope_code,additional_dimension_data,additional_dimension_key,
        effective_from,effective_to,value_data,note,created_at,created_by,updated_at,updated_by
    )
    SELECT CONCAT('20260831-10',RIGHT(SUBSTRING_INDEX(SUBSTRING_INDEX(legacy.id,'-',2),'-',-1),2),'-4000-8000-',RIGHT(legacy.id,12)),
           legacy.sort_no,
           JSON_UNQUOTE(JSON_EXTRACT(legacy.value_data,'$.insurance_type_code')),
           'ELIGIBILITY',
           JSON_UNQUOTE(JSON_EXTRACT(legacy.value_data,'$.employment_type_code')),
           JSON_UNQUOTE(JSON_EXTRACT(legacy.value_data,'$.work_scope_code')),
           IF(JSON_EXTRACT(legacy.value_data,'$.transition.required_status_code') IS NULL
              OR JSON_TYPE(JSON_EXTRACT(legacy.value_data,'$.transition.required_status_code'))='NULL',
              JSON_OBJECT(),
              JSON_OBJECT('transition_status_code',JSON_UNQUOTE(JSON_EXTRACT(legacy.value_data,'$.transition.required_status_code')))),
           SHA2(CAST(IF(JSON_EXTRACT(legacy.value_data,'$.transition.required_status_code') IS NULL
              OR JSON_TYPE(JSON_EXTRACT(legacy.value_data,'$.transition.required_status_code'))='NULL',
              JSON_OBJECT(),
              JSON_OBJECT('transition_status_code',JSON_UNQUOTE(JSON_EXTRACT(legacy.value_data,'$.transition.required_status_code')))) AS CHAR),256),
           legacy.effective_from,legacy.effective_to,legacy.value_data,
           CONCAT('[보험별 가입자격 이관] ',COALESCE(legacy.note,'')),
           legacy.created_at,legacy.created_by,legacy.updated_at,legacy.updated_by
      FROM system_statutory_standards legacy
     WHERE legacy.standard_type_code='INSURANCE_ELIGIBILITY'
     ORDER BY legacy.sort_no,legacy.id;

    UPDATE system_statutory_standard_sources source_row
    JOIN system_statutory_standards legacy ON legacy.id=source_row.standard_id
       SET source_row.standard_id=CONCAT('20260831-10',RIGHT(SUBSTRING_INDEX(SUBSTRING_INDEX(legacy.id,'-',2),'-',-1),2),'-4000-8000-',RIGHT(legacy.id,12))
     WHERE legacy.standard_type_code='INSURANCE_ELIGIBILITY';

    IF (SELECT COUNT(*) FROM system_statutory_standards WHERE policy_component_code='ELIGIBILITY' AND standard_type_code IN('NATIONAL_PENSION','HEALTH_INSURANCE','LONG_TERM_CARE')) <> 22
       OR (SELECT COUNT(*) FROM system_statutory_standard_sources source_row JOIN system_statutory_standards standard_row ON standard_row.id=source_row.standard_id WHERE standard_row.policy_component_code='ELIGIBILITY') <> 22
       OR (SELECT COUNT(*) FROM system_statutory_standard_sources source_row JOIN system_statutory_standards standard_row ON standard_row.id=source_row.standard_id WHERE standard_row.standard_type_code='INSURANCE_ELIGIBILITY') <> 0 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='보험별 가입자격 Revision 또는 Source 이관 건수가 다릅니다.';
    END IF;
END$$
CALL migrate_20260831_01_up()$$
DROP PROCEDURE migrate_20260831_01_up$$
DELIMITER ;
