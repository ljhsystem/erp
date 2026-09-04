ALTER TABLE institution_regular_employment_income_line_items
  DROP CONSTRAINT chk_regular_income_line_final,
  DROP CONSTRAINT chk_regular_income_line_adjustment;

UPDATE institution_regular_employment_income_line_items
SET calculated_amount = final_amount,
    adjustment_amount = 0,
    adjustment_reason = NULL
WHERE calculated_amount IS NULL;

UPDATE institution_regular_employment_income_line_items
SET adjustment_amount = 0
WHERE adjustment_amount IS NULL;

ALTER TABLE institution_regular_employment_income_line_items
  MODIFY COLUMN calculated_amount DECIMAL(18,2) NOT NULL DEFAULT 0 COMMENT '자동계산금액',
  MODIFY COLUMN adjustment_amount DECIMAL(18,2) NOT NULL DEFAULT 0 COMMENT '관리자조정금액',
  ADD CONSTRAINT chk_regular_income_line_final CHECK (final_amount = calculated_amount + adjustment_amount),
  ADD CONSTRAINT chk_regular_income_line_adjustment CHECK (
    adjustment_amount = 0
    OR (adjustment_reason IS NOT NULL AND CHAR_LENGTH(TRIM(adjustment_reason)) > 0)
  );

ALTER TABLE institution_regular_employment_income_items
  DROP CONSTRAINT chk_regular_income_employment_basis_snapshot,
  DROP CONSTRAINT chk_regular_income_health_basis_snapshot,
  DROP CONSTRAINT chk_regular_income_pension_basis_snapshot,
  DROP COLUMN employment_insurance_basis_snapshot,
  DROP COLUMN health_insurance_basis_snapshot,
  DROP COLUMN national_pension_basis_snapshot;
