ALTER TABLE `institution_regular_employment_income_audits`
  DROP CONSTRAINT `chk_regular_income_audit_action`,
  ADD CONSTRAINT `chk_regular_income_audit_action` CHECK (`action_code` IN ('CREATE','CALCULATE','RECALCULATE','ADJUST','SUBMIT','WITHDRAW','APPROVE','ACCOUNTING_MATERIALIZE','CORRECT','CANCEL'));

ALTER TABLE `institution_regular_employment_incomes`
  DROP CONSTRAINT `chk_regular_income_payment_override`,
  DROP COLUMN `payment_date_override_reason`,
  DROP COLUMN `proposed_payment_date`,
  DROP COLUMN `nominal_payment_date`,
  DROP COLUMN `payroll_period_end_date`,
  DROP COLUMN `payroll_period_start_date`;

ALTER TABLE `institution_employment_contracts`
  DROP CONSTRAINT `chk_employment_contract_payment_calendar`,
  DROP CONSTRAINT `chk_employment_contract_payment_non_business`,
  DROP CONSTRAINT `chk_employment_contract_payment_missing_day`,
  DROP CONSTRAINT `chk_employment_contract_payroll_period_end`,
  DROP CONSTRAINT `chk_employment_contract_payroll_period_start`,
  DROP CONSTRAINT `chk_employment_contract_payroll_period_basis`,
  DROP INDEX `idx_employment_contract_payroll_policy`,
  DROP COLUMN `payment_calendar_policy_code`,
  DROP COLUMN `payment_non_business_day_policy_code`,
  DROP COLUMN `payment_missing_day_policy_code`,
  DROP COLUMN `payroll_period_end_policy_code`,
  DROP COLUMN `payroll_period_start_policy_code`,
  DROP COLUMN `payroll_period_basis_code`;

DROP TABLE `institution_payroll_policies`;

DELETE FROM `system_codes` WHERE `code_group` IN ('PAYROLL_PERIOD_BASIS','PAYROLL_PERIOD_START_POLICY','PAYROLL_PERIOD_END_POLICY','PAYMENT_MISSING_DAY_POLICY','PAYMENT_NON_BUSINESS_DAY_POLICY','PAYMENT_BUSINESS_CALENDAR_POLICY');
