ALTER TABLE ledger_evidence_business_income
    DROP COLUMN raw_withholding_date,
    ALGORITHM=INPLACE, LOCK=NONE;

ALTER TABLE ledger_evidence_daily_employment_income
    DROP COLUMN raw_withholding_date,
    ALGORITHM=INPLACE, LOCK=NONE;

ALTER TABLE ledger_evidence_salary_report
    DROP COLUMN raw_withholding_date,
    ALGORITHM=INPLACE, LOCK=NONE;

ALTER TABLE institution_business_incomes
    DROP INDEX idx_business_income_withholding_date,
    DROP COLUMN withholding_date,
    ALGORITHM=INPLACE, LOCK=NONE;

ALTER TABLE institution_daily_employment_incomes
    DROP INDEX idx_daily_income_withholding_date,
    DROP COLUMN withholding_date,
    ALGORITHM=INPLACE, LOCK=NONE;

ALTER TABLE institution_regular_employment_incomes
    DROP INDEX idx_regular_income_withholding_date,
    DROP COLUMN withholding_date,
    ALGORITHM=INPLACE, LOCK=NONE;
