DROP PROCEDURE IF EXISTS migrate_20260901_03_daily_evidence_checks;
DELIMITER $$
CREATE PROCEDURE migrate_20260901_03_daily_evidence_checks()
procedure_body: BEGIN
    IF (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE()
      AND TABLE_NAME='ledger_evidence_daily_employment_income' AND CONSTRAINT_NAME IN ('ck_daily_evidence_raw_non_negative','ck_daily_evidence_raw_amounts','ck_daily_evidence_raw_period','ck_daily_evidence_business_classification','ck_daily_evidence_review_status'))<>0 THEN
      IF (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME='ledger_evidence_daily_employment_income' AND CONSTRAINT_NAME IN ('ck_daily_evidence_raw_non_negative','ck_daily_evidence_raw_amounts','ck_daily_evidence_raw_period','ck_daily_evidence_business_classification','ck_daily_evidence_review_status'))=5 THEN LEAVE procedure_body; END IF;
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='일용 Evidence CHECK가 부분 적용된 상태입니다.';
    END IF;
    IF EXISTS (SELECT 1 FROM ledger_evidence_daily_employment_income WHERE raw_income_year_month IS NULL OR raw_payment_date IS NULL
      OR raw_work_day_count IS NULL OR raw_gross_payment_amount IS NULL OR raw_worker_deduction_amount IS NULL
      OR raw_net_payment_amount IS NULL OR raw_employer_burden_amount IS NULL OR business_unit IS NULL
      OR transaction_direction IS NULL OR operation_type IS NULL OR evidence_status IS NULL) THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='일용 Evidence backfill 완료 후 CHECK를 설치해야 합니다.';
    END IF;
    IF EXISTS (SELECT 1 FROM ledger_evidence_daily_employment_income e LEFT JOIN system_codes c
      ON c.code_group='BUSINESS_UNIT' AND c.code=e.business_unit AND c.is_active=1 WHERE c.id IS NULL) THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='활성 BUSINESS_UNIT이 아닌 일용 Evidence가 있습니다.';
    END IF;
    ALTER TABLE ledger_evidence_daily_employment_income
      ADD CONSTRAINT ck_daily_evidence_raw_non_negative CHECK (raw_work_day_count>=0 AND raw_gross_payment_amount>=0 AND raw_worker_deduction_amount>=0 AND raw_net_payment_amount>=0 AND raw_employer_burden_amount>=0),
      ADD CONSTRAINT ck_daily_evidence_raw_amounts CHECK (ROUND(raw_gross_payment_amount-raw_worker_deduction_amount,2)=ROUND(raw_net_payment_amount,2)),
      ADD CONSTRAINT ck_daily_evidence_raw_period CHECK (raw_income_year_month REGEXP '^[0-9]{4}-(0[1-9]|1[0-2])$'),
      ADD CONSTRAINT ck_daily_evidence_business_classification CHECK (transaction_direction='EXPENSE' AND operation_type='DAILY_WORKER' AND TRIM(business_unit)<>''),
      ADD CONSTRAINT ck_daily_evidence_review_status CHECK (evidence_status IN ('CORRECTION_REQUIRED','COMPLETED'));
END$$
DELIMITER ;
CALL migrate_20260901_03_daily_evidence_checks();
DROP PROCEDURE migrate_20260901_03_daily_evidence_checks;
