ALTER TABLE institution_daily_employment_income_groups
    DROP FOREIGN KEY fk_daily_group_industrial_accident_source,
    DROP FOREIGN KEY fk_daily_group_employment_insurance_source,
    DROP CONSTRAINT ck_daily_group_industrial_accident_decision,
    DROP CONSTRAINT ck_daily_group_employment_insurance_decision,
    DROP INDEX idx_daily_group_industrial_accident_source,
    DROP INDEX idx_daily_group_employment_insurance_source,
    DROP COLUMN industrial_accident_decision_source_code_id,
    DROP COLUMN industrial_accident_decision_reason,
    DROP COLUMN industrial_accident_application_status_code,
    DROP COLUMN employment_insurance_decision_source_code_id,
    DROP COLUMN employment_insurance_decision_reason,
    DROP COLUMN employment_insurance_application_status_code;

ALTER TABLE institution_employment_contracts
    DROP CONSTRAINT ck_employment_contract_industrial_accident,
    DROP CONSTRAINT ck_employment_contract_employment_insurance,
    DROP COLUMN industrial_accident_exclusion_reason,
    DROP COLUMN industrial_accident_application_status_code,
    DROP COLUMN employment_insurance_exclusion_reason,
    DROP COLUMN employment_insurance_application_status_code;

DELETE FROM system_codes
WHERE id='20260828-0701-4000-8000-000000000001'
  AND code_group='INCOME_ACTUAL_APPLICATION_SOURCE'
  AND code='MANUAL_INTERIM_GROUP';
