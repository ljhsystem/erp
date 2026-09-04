ALTER TABLE institution_regular_employment_incomes
  DROP FOREIGN KEY fk_regular_income_correction,
  DROP INDEX idx_regular_income_correction,
  DROP CONSTRAINT chk_regular_income_revision,
  DROP INDEX uk_regular_income_request,
  DROP COLUMN correction_of_id,
  DROP COLUMN revision_no,
  DROP COLUMN request_key,
  DROP COLUMN snapshot_at;
