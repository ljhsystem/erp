DROP PROCEDURE IF EXISTS migrate_20260829_05_down;
DELIMITER $$
CREATE PROCEDURE migrate_20260829_05_down()
BEGIN
    IF EXISTS(
        SELECT 1 FROM institution_daily_employment_income_calculation_results
        WHERE daily_employment_income_item_id IS NOT NULL OR eligibility_status_code IS NOT NULL
           OR eligibility_reason_code IS NOT NULL OR missing_inputs IS NOT NULL OR snapshot_schema_version IS NOT NULL
        LIMIT 1
    ) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='가입자격 Result가 있어 20260829_05 Down을 중단합니다.';
    END IF;

    ALTER TABLE institution_daily_employment_income_calculation_results
      DROP CONSTRAINT ck_daily_calc_result_eligibility_status,
      DROP CONSTRAINT ck_daily_calc_result_missing_inputs,
      DROP CONSTRAINT ck_daily_calc_result_confirmation_amount,
      DROP INDEX idx_daily_calc_result_item,
      DROP COLUMN snapshot_schema_version,
      DROP COLUMN missing_inputs,
      DROP COLUMN eligibility_reason_code,
      DROP COLUMN eligibility_status_code,
      DROP COLUMN daily_employment_income_item_id,
      MODIFY statutory_standard_id CHAR(36) NOT NULL,
      MODIFY confirmed_employer_amount DECIMAL(18,2) NOT NULL DEFAULT 0,
      MODIFY confirmed_employee_amount DECIMAL(18,2) NOT NULL DEFAULT 0,
      MODIFY automatic_employer_amount DECIMAL(18,2) NOT NULL DEFAULT 0,
      MODIFY automatic_employee_amount DECIMAL(18,2) NOT NULL DEFAULT 0,
      MODIFY calculation_basis_amount DECIMAL(18,2) NOT NULL DEFAULT 0;

    UPDATE system_statutory_standards
    SET value_data=JSON_SET(
        value_data,
        '$.employment_period.minimum_continuous_months',NULL,
        '$.monthly_conditions.minimum_work_days',1,
        '$.monthly_conditions.minimum_work_minutes',8,
        '$.requirements.employment_start_date',FALSE,
        '$.requirements.employment_end_date_or_open_status',FALSE,
        '$.requirements.continuous_employment_confirmed',FALSE,
        '$.requirements.monthly_work_days',TRUE,
        '$.requirements.monthly_work_minutes',TRUE
    ),note=REPLACE(note,' / 20260829_05 Seed 위치 교정','')
    WHERE id='20260829-0315-4000-8000-000000000015';
END$$
CALL migrate_20260829_05_down()$$
DROP PROCEDURE migrate_20260829_05_down$$
DELIMITER ;
