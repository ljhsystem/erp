SET NAMES utf8mb4;

ALTER TABLE institution_regular_employment_incomes
    ADD COLUMN withholding_date DATE NULL COMMENT '원천징수일(실제 급여 지급 기준일)' AFTER income_year_month,
    ADD KEY idx_regular_income_withholding_date (withholding_date),
    ALGORITHM=INPLACE, LOCK=NONE;

ALTER TABLE institution_daily_employment_incomes
    ADD COLUMN withholding_date DATE NULL COMMENT '원천징수일(실제 일용근로소득 지급 기준일)' AFTER income_year_month,
    ADD KEY idx_daily_income_withholding_date (withholding_date),
    ALGORITHM=INPLACE, LOCK=NONE;

ALTER TABLE institution_business_incomes
    ADD COLUMN withholding_date DATE NULL COMMENT '원천징수일(실제 사업소득 지급 기준일)' AFTER income_year_month,
    ADD KEY idx_business_income_withholding_date (withholding_date),
    ALGORITHM=INPLACE, LOCK=NONE;

ALTER TABLE ledger_evidence_salary_report
    ADD COLUMN raw_withholding_date DATE NULL COMMENT '상용근로소득 원천징수일' AFTER raw_income_year_month,
    ALGORITHM=INPLACE, LOCK=NONE;

ALTER TABLE ledger_evidence_daily_employment_income
    ADD COLUMN raw_withholding_date DATE NULL COMMENT '일용근로소득 원천징수일' AFTER raw_income_year_month,
    ALGORITHM=INPLACE, LOCK=NONE;

ALTER TABLE ledger_evidence_business_income
    ADD COLUMN raw_withholding_date DATE NULL COMMENT '사업소득 원천징수일' AFTER raw_income_year_month,
    ALGORITHM=INPLACE, LOCK=NONE;
