ALTER TABLE institution_regular_employment_incomes
    ADD COLUMN nominal_payment_date DATE NULL COMMENT '휴일보정 전 명목 지급일',
    ADD COLUMN proposed_payment_date DATE NULL COMMENT '계약정책 자동제안 지급일',
    ADD COLUMN payment_date DATE NULL COMMENT '폐기된 지급예정일',
    ADD COLUMN payment_date_override_reason VARCHAR(500) NULL COMMENT '자동제안 지급일 변경사유',
    ADD KEY idx_institution_regular_employment_income_payment_date (payment_date),
    ADD CONSTRAINT chk_regular_income_payment_override CHECK (proposed_payment_date IS NULL OR payment_date=proposed_payment_date OR CHAR_LENGTH(TRIM(payment_date_override_reason))>0);

ALTER TABLE institution_daily_employment_incomes
    ADD COLUMN payment_date DATE NULL COMMENT '폐기된 지급예정일';

ALTER TABLE institution_daily_employment_income_calculation_results
    DROP INDEX uq_daily_calc_result_grain,
    DROP INDEX idx_daily_calc_result_worker,
    ADD COLUMN payment_date DATE NULL COMMENT '폐기된 지급예정일',
    ADD UNIQUE KEY uq_daily_calc_result_grain (
        calculation_revision_id,result_type_code,worker_client_id,daily_employment_income_item_id,
        workplace_scope_key,workday_scope_key,application_from,application_to,payment_date,payment_sequence
    ),
    ADD KEY idx_daily_calc_result_worker (worker_client_id,result_type_code,payment_date);

ALTER TABLE institution_business_income_groups
    ADD COLUMN payment_date DATE NULL COMMENT '폐기된 지급예정일';

ALTER TABLE institution_business_income_items
    DROP COLUMN transaction_date;

ALTER TABLE ledger_evidence_salary_report
    ADD COLUMN raw_payment_date DATE NULL COMMENT '폐기된 지급예정일 원천',
    ADD KEY idx_salary_report_payment_date (raw_payment_date);

ALTER TABLE ledger_evidence_daily_employment_income
    ADD COLUMN payment_date DATE NULL COMMENT '폐기된 지급예정일',
    ADD COLUMN raw_payment_date DATE NULL COMMENT '폐기된 지급예정일 원천';

ALTER TABLE ledger_evidence_business_income
    DROP INDEX idx_business_income_evidence_list,
    ADD COLUMN raw_payment_date DATE NULL COMMENT '폐기된 지급예정일 원천',
    ADD KEY idx_business_income_evidence_list (raw_income_year_month,raw_payment_date,sort_no,id);
