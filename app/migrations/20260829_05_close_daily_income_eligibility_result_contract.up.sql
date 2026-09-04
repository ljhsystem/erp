DELIMITER $$
CREATE PROCEDURE migrate_20260829_05_up()
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM system_statutory_standards
        WHERE id='20260829-0315-4000-8000-000000000015'
          AND JSON_UNQUOTE(JSON_EXTRACT(value_data,'$.insurance_type_code'))='HEALTH_INSURANCE'
    ) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='건강보험 2020-01-01 가입자격 Revision이 없습니다.';
    END IF;

    UPDATE system_statutory_standards
    SET value_data=JSON_SET(
        value_data,
        '$.employment_period.minimum_continuous_months',1,
        '$.monthly_conditions.minimum_work_days',8,
        '$.monthly_conditions.minimum_work_minutes',NULL,
        '$.requirements.employment_start_date',TRUE,
        '$.requirements.employment_end_date_or_open_status',TRUE,
        '$.requirements.continuous_employment_confirmed',TRUE,
        '$.requirements.monthly_work_days',TRUE,
        '$.requirements.monthly_work_minutes',FALSE
    ),note=CONCAT(note,' / 20260829_05 Seed 위치 교정')
    WHERE id='20260829-0315-4000-8000-000000000015';

    ALTER TABLE institution_daily_employment_income_calculation_results
      MODIFY calculation_basis_amount DECIMAL(18,2) NULL,
      MODIFY automatic_employee_amount DECIMAL(18,2) NULL,
      MODIFY automatic_employer_amount DECIMAL(18,2) NULL,
      MODIFY confirmed_employee_amount DECIMAL(18,2) NULL,
      MODIFY confirmed_employer_amount DECIMAL(18,2) NULL,
      MODIFY statutory_standard_id CHAR(36) NULL,
      ADD COLUMN daily_employment_income_item_id VARCHAR(36) NULL AFTER worker_client_id,
      ADD COLUMN eligibility_status_code VARCHAR(30) NULL AFTER status_code,
      ADD COLUMN eligibility_reason_code VARCHAR(100) NULL AFTER eligibility_status_code,
      ADD COLUMN missing_inputs LONGTEXT NULL AFTER eligibility_reason_code,
      ADD COLUMN snapshot_schema_version VARCHAR(60) NULL AFTER eligibility_snapshot,
      ADD KEY idx_daily_calc_result_item(daily_employment_income_item_id),
      ADD CONSTRAINT ck_daily_calc_result_eligibility_status CHECK(
        eligibility_status_code IS NULL OR eligibility_status_code IN('ELIGIBLE','NOT_ELIGIBLE','CONFIRMATION_REQUIRED')
      ),
      ADD CONSTRAINT ck_daily_calc_result_missing_inputs CHECK(missing_inputs IS NULL OR JSON_VALID(missing_inputs)),
      ADD CONSTRAINT ck_daily_calc_result_confirmation_amount CHECK(
        eligibility_status_code<>'CONFIRMATION_REQUIRED'
        OR (calculation_basis_amount IS NULL AND automatic_employee_amount IS NULL
            AND automatic_employer_amount IS NULL AND confirmed_employee_amount IS NULL
            AND confirmed_employer_amount IS NULL)
      );
END$$
CALL migrate_20260829_05_up()$$
DROP PROCEDURE migrate_20260829_05_up$$
DELIMITER ;
