ALTER TABLE institution_regular_employment_income_items
  ADD COLUMN national_pension_basis_snapshot DECIMAL(18,2) NULL COMMENT '해당 귀속월 급여 계산에 사용한 국민연금 기준소득월액 Snapshot' AFTER dependent_count_snapshot,
  ADD COLUMN health_insurance_basis_snapshot DECIMAL(18,2) NULL COMMENT '해당 귀속월 급여 계산에 사용한 건강보험 보수월액 Snapshot' AFTER national_pension_basis_snapshot,
  ADD COLUMN employment_insurance_basis_snapshot DECIMAL(18,2) NULL COMMENT '해당 귀속월 급여 계산에 사용한 고용보험 산정대상 보수 Snapshot' AFTER health_insurance_basis_snapshot,
  ADD CONSTRAINT chk_regular_income_pension_basis_snapshot CHECK (national_pension_basis_snapshot IS NULL OR national_pension_basis_snapshot >= 0),
  ADD CONSTRAINT chk_regular_income_health_basis_snapshot CHECK (health_insurance_basis_snapshot IS NULL OR health_insurance_basis_snapshot >= 0),
  ADD CONSTRAINT chk_regular_income_employment_basis_snapshot CHECK (employment_insurance_basis_snapshot IS NULL OR employment_insurance_basis_snapshot >= 0);

ALTER TABLE institution_regular_employment_income_line_items
  DROP CONSTRAINT chk_regular_income_line_final,
  DROP CONSTRAINT chk_regular_income_line_adjustment,
  MODIFY COLUMN calculated_amount DECIMAL(18,2) NULL DEFAULT NULL COMMENT '법정기준 자동계산값; 계산 불가 시 NULL',
  MODIFY COLUMN adjustment_amount DECIMAL(18,2) NULL DEFAULT NULL COMMENT '실제값과 자동계산값의 차이; 계산 불가 시 NULL',
  ADD CONSTRAINT chk_regular_income_line_final CHECK (
    (calculated_amount IS NULL AND adjustment_amount IS NULL)
    OR
    (calculated_amount IS NOT NULL AND final_amount = calculated_amount + COALESCE(adjustment_amount, 0))
  ),
  ADD CONSTRAINT chk_regular_income_line_adjustment CHECK (
    adjustment_amount IS NULL
    OR adjustment_amount = 0
    OR (adjustment_reason IS NOT NULL AND CHAR_LENGTH(TRIM(adjustment_reason)) > 0)
  );
