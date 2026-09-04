SET NAMES utf8mb4;

ALTER TABLE institution_regular_employment_incomes
    MODIFY COLUMN withholding_date DATE NULL COMMENT '원천징수일(기관 신고 및 법정기준 적용일)',
    ALGORITHM=INPLACE, LOCK=NONE;

ALTER TABLE institution_daily_employment_incomes
    MODIFY COLUMN withholding_date DATE NULL COMMENT '원천징수일(기관 신고 및 법정기준 적용일)',
    ALGORITHM=INPLACE, LOCK=NONE;

ALTER TABLE institution_business_incomes
    MODIFY COLUMN withholding_date DATE NULL COMMENT '원천징수일(기관 신고 및 법정기준 적용일)',
    ALGORITHM=INPLACE, LOCK=NONE;

ALTER TABLE ledger_evidence_salary_report
    MODIFY COLUMN raw_withholding_date DATE NULL COMMENT '승인 당시 원천징수일(기관 신고 및 법정기준 적용일)',
    ALGORITHM=INPLACE, LOCK=NONE;

ALTER TABLE ledger_evidence_daily_employment_income
    MODIFY COLUMN raw_withholding_date DATE NULL COMMENT '승인 당시 원천징수일(기관 신고 및 법정기준 적용일)',
    ALGORITHM=INPLACE, LOCK=NONE;

ALTER TABLE ledger_evidence_business_income
    MODIFY COLUMN raw_withholding_date DATE NULL COMMENT '승인 당시 원천징수일(기관 신고 및 법정기준 적용일)',
    ALGORITHM=INPLACE, LOCK=NONE;
