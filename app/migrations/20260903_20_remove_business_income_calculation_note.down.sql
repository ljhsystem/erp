SET NAMES utf8mb4;

ALTER TABLE institution_business_income_work_lines
    ADD COLUMN calculation_note VARCHAR(1000) NULL COMMENT '산정내역' AFTER adjustment_reason,
    ALGORITHM=INSTANT, LOCK=NONE;

ALTER TABLE ledger_evidence_business_income_work_lines
    ADD COLUMN raw_calculation_note VARCHAR(1000) NULL COMMENT '원본 산정내역' AFTER raw_adjustment_reason,
    ALGORITHM=INSTANT, LOCK=NONE;
