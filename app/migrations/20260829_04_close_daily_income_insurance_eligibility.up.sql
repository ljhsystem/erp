ALTER TABLE institution_daily_employment_income_calculation_results
    ADD COLUMN eligibility_revision_id VARCHAR(36) NULL COMMENT '가입자격 Revision' AFTER statutory_standard_id,
    ADD COLUMN eligibility_snapshot LONGTEXT NULL COMMENT '가입자격 판정 불변 Snapshot' AFTER calculation_basis_snapshot,
    ADD KEY idx_daily_calc_result_eligibility(eligibility_revision_id),
    ADD CONSTRAINT fk_daily_calc_result_eligibility FOREIGN KEY(eligibility_revision_id) REFERENCES system_statutory_standards(id) ON DELETE RESTRICT ON UPDATE CASCADE,
    ADD CONSTRAINT ck_daily_calc_result_eligibility_snapshot CHECK(eligibility_snapshot IS NULL OR JSON_VALID(eligibility_snapshot));
