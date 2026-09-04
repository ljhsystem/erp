INSERT INTO system_codes
    (id,sort_no,code_group,group_name,code,code_name,note,is_active,created_at,created_by,updated_at,updated_by)
VALUES
    ('20260828-0701-4000-8000-000000000001',6,'INCOME_ACTUAL_APPLICATION_SOURCE','소득 실제 적용원천',
     'MANUAL_INTERIM_GROUP','일용 Group 임시 수동판정',
     '현장 계약관리 도입 전 일용근로소득 Group 보험 적용판정',1,NOW(),'SYSTEM:MIGRATION',NOW(),'SYSTEM:MIGRATION');

ALTER TABLE institution_employment_contracts
    ADD COLUMN employment_insurance_application_status_code VARCHAR(30) NULL AFTER employment_category,
    ADD COLUMN employment_insurance_exclusion_reason VARCHAR(500) NULL AFTER employment_insurance_application_status_code,
    ADD COLUMN industrial_accident_application_status_code VARCHAR(30) NULL AFTER employment_insurance_exclusion_reason,
    ADD COLUMN industrial_accident_exclusion_reason VARCHAR(500) NULL AFTER industrial_accident_application_status_code,
    ADD CONSTRAINT ck_employment_contract_employment_insurance CHECK (COALESCE(
        (employment_insurance_application_status_code='APPLICABLE' AND employment_insurance_exclusion_reason IS NULL)
        OR (employment_insurance_application_status_code='EXCLUDED'
            AND employment_insurance_exclusion_reason IS NOT NULL
            AND CHAR_LENGTH(employment_insurance_exclusion_reason) BETWEEN 1 AND 500
            AND BINARY employment_insurance_exclusion_reason=BINARY TRIM(employment_insurance_exclusion_reason))
        OR (employment_insurance_application_status_code IS NULL AND employment_insurance_exclusion_reason IS NULL),
        FALSE
    )),
    ADD CONSTRAINT ck_employment_contract_industrial_accident CHECK (COALESCE(
        (industrial_accident_application_status_code='APPLICABLE' AND industrial_accident_exclusion_reason IS NULL)
        OR (industrial_accident_application_status_code='EXCLUDED'
            AND industrial_accident_exclusion_reason IS NOT NULL
            AND CHAR_LENGTH(industrial_accident_exclusion_reason) BETWEEN 1 AND 500
            AND BINARY industrial_accident_exclusion_reason=BINARY TRIM(industrial_accident_exclusion_reason))
        OR (industrial_accident_application_status_code IS NULL AND industrial_accident_exclusion_reason IS NULL),
        FALSE
    ));

ALTER TABLE institution_daily_employment_income_groups
    ADD COLUMN employment_insurance_application_status_code VARCHAR(30) NULL AFTER work_description,
    ADD COLUMN employment_insurance_decision_reason VARCHAR(500) NULL AFTER employment_insurance_application_status_code,
    ADD COLUMN employment_insurance_decision_source_code_id VARCHAR(36) NULL AFTER employment_insurance_decision_reason,
    ADD COLUMN industrial_accident_application_status_code VARCHAR(30) NULL AFTER employment_insurance_decision_source_code_id,
    ADD COLUMN industrial_accident_decision_reason VARCHAR(500) NULL AFTER industrial_accident_application_status_code,
    ADD COLUMN industrial_accident_decision_source_code_id VARCHAR(36) NULL AFTER industrial_accident_decision_reason,
    ADD INDEX idx_daily_group_employment_insurance_source (employment_insurance_decision_source_code_id),
    ADD INDEX idx_daily_group_industrial_accident_source (industrial_accident_decision_source_code_id),
    ADD CONSTRAINT fk_daily_group_employment_insurance_source FOREIGN KEY (employment_insurance_decision_source_code_id)
        REFERENCES system_codes(id) ON UPDATE RESTRICT ON DELETE RESTRICT,
    ADD CONSTRAINT fk_daily_group_industrial_accident_source FOREIGN KEY (industrial_accident_decision_source_code_id)
        REFERENCES system_codes(id) ON UPDATE RESTRICT ON DELETE RESTRICT,
    ADD CONSTRAINT ck_daily_group_employment_insurance_decision CHECK (COALESCE(
        (employment_insurance_application_status_code IS NULL
            AND employment_insurance_decision_reason IS NULL
            AND employment_insurance_decision_source_code_id IS NULL)
        OR (employment_insurance_application_status_code='CONFIRMATION_REQUIRED'
            AND employment_insurance_decision_source_code_id IS NULL
            AND employment_insurance_decision_reason IS NOT NULL
            AND CHAR_LENGTH(employment_insurance_decision_reason) BETWEEN 1 AND 500
            AND BINARY employment_insurance_decision_reason=BINARY TRIM(employment_insurance_decision_reason))
        OR (employment_insurance_application_status_code='APPLICABLE'
            AND employment_insurance_decision_source_code_id IS NOT NULL
            AND (employment_insurance_decision_reason IS NULL
                OR (CHAR_LENGTH(employment_insurance_decision_reason) BETWEEN 1 AND 500
                    AND BINARY employment_insurance_decision_reason=BINARY TRIM(employment_insurance_decision_reason))))
        OR (employment_insurance_application_status_code IN ('EXCLUDED','CONFIRMATION_REQUIRED')
            AND employment_insurance_decision_source_code_id IS NOT NULL
            AND employment_insurance_decision_reason IS NOT NULL
            AND CHAR_LENGTH(employment_insurance_decision_reason) BETWEEN 1 AND 500
            AND BINARY employment_insurance_decision_reason=BINARY TRIM(employment_insurance_decision_reason)),
        FALSE
    )),
    ADD CONSTRAINT ck_daily_group_industrial_accident_decision CHECK (COALESCE(
        (industrial_accident_application_status_code IS NULL
            AND industrial_accident_decision_reason IS NULL
            AND industrial_accident_decision_source_code_id IS NULL)
        OR (industrial_accident_application_status_code='CONFIRMATION_REQUIRED'
            AND industrial_accident_decision_source_code_id IS NULL
            AND industrial_accident_decision_reason IS NOT NULL
            AND CHAR_LENGTH(industrial_accident_decision_reason) BETWEEN 1 AND 500
            AND BINARY industrial_accident_decision_reason=BINARY TRIM(industrial_accident_decision_reason))
        OR (industrial_accident_application_status_code='APPLICABLE'
            AND industrial_accident_decision_source_code_id IS NOT NULL
            AND (industrial_accident_decision_reason IS NULL
                OR (CHAR_LENGTH(industrial_accident_decision_reason) BETWEEN 1 AND 500
                    AND BINARY industrial_accident_decision_reason=BINARY TRIM(industrial_accident_decision_reason))))
        OR (industrial_accident_application_status_code IN ('EXCLUDED','CONFIRMATION_REQUIRED')
            AND industrial_accident_decision_source_code_id IS NOT NULL
            AND industrial_accident_decision_reason IS NOT NULL
            AND CHAR_LENGTH(industrial_accident_decision_reason) BETWEEN 1 AND 500
            AND BINARY industrial_accident_decision_reason=BINARY TRIM(industrial_accident_decision_reason)),
        FALSE
    ));

UPDATE institution_daily_employment_income_groups g
JOIN system_codes c ON c.code_group='BUSINESS_UNIT' AND c.code=g.business_unit
SET g.employment_insurance_application_status_code='CONFIRMATION_REQUIRED',
    g.employment_insurance_decision_reason='기존 문서 고용보험 적용 여부 확인 필요',
    g.employment_insurance_decision_source_code_id=NULL,
    g.industrial_accident_application_status_code='CONFIRMATION_REQUIRED',
    g.industrial_accident_decision_reason='기존 문서 산재보험 적용 여부 확인 필요',
    g.industrial_accident_decision_source_code_id=NULL,
    g.updated_at=g.updated_at,
    g.updated_by=g.updated_by
WHERE g.employment_insurance_application_status_code IS NULL
  AND g.industrial_accident_application_status_code IS NULL
  AND g.project_id IS NOT NULL
  AND JSON_UNQUOTE(JSON_EXTRACT(c.extra_data,'$.daily_employment_income.uses_project'))='true';
