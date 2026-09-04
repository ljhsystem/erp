ALTER TABLE ledger_evidence_business_income
    ADD COLUMN income_date DATE NULL COMMENT '소득발생일' AFTER raw_net_payment_amount,
    ADD COLUMN provider_name VARCHAR(200) NULL COMMENT '공급자명' AFTER income_date,
    ADD COLUMN provider_reg_no VARCHAR(30) NULL COMMENT '공급자 등록번호' AFTER provider_name,
    ADD COLUMN supply_amount DECIMAL(18,2) NULL COMMENT '공급가액' AFTER provider_reg_no,
    ADD COLUMN vat_amount DECIMAL(18,2) NULL COMMENT '부가가치세액' AFTER supply_amount,
    ADD COLUMN service_amount DECIMAL(18,2) NULL COMMENT '봉사료' AFTER vat_amount,
    ADD COLUMN total_amount DECIMAL(18,2) NULL COMMENT '증빙 총금액' AFTER service_amount;

DROP TABLE ledger_evidence_salary_report_lines;
