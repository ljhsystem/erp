SET NAMES utf8mb4;

ALTER TABLE institution_regular_employment_incomes
    MODIFY COLUMN withholding_date DATE NULL COMMENT '원천징수일';

ALTER TABLE institution_daily_employment_incomes
    MODIFY COLUMN withholding_date DATE NULL COMMENT '원천징수일';

ALTER TABLE institution_business_incomes
    MODIFY COLUMN withholding_date DATE NULL COMMENT '원천징수일';

ALTER TABLE ledger_evidence_salary_report
    MODIFY COLUMN raw_withholding_date DATE NULL COMMENT '상용근로소득 원천징수일';

ALTER TABLE ledger_evidence_daily_employment_income
    MODIFY COLUMN raw_withholding_date DATE NULL COMMENT '일용근로소득 원천징수일';

ALTER TABLE ledger_evidence_business_income
    MODIFY COLUMN raw_withholding_date DATE NULL COMMENT '사업소득 원천징수일';
