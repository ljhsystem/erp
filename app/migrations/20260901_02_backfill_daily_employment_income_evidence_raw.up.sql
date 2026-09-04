DROP PROCEDURE IF EXISTS backfill_daily_employment_income_evidence_raw;
DELIMITER $$
CREATE PROCEDURE backfill_daily_employment_income_evidence_raw()
procedure_body: BEGIN
    IF (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='ledger_evidence_daily_employment_income'
      AND COLUMN_NAME IN ('business_unit','transaction_direction','operation_type','raw_income_year_month','raw_payment_date','raw_work_day_count','raw_gross_payment_amount','raw_worker_deduction_amount','raw_net_payment_amount','raw_employer_burden_amount','evidence_status'))<>11 THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='일용 Evidence 정규화 컬럼 11개가 완전하게 준비되지 않았습니다.';
    END IF;
    IF EXISTS (SELECT 1 FROM ledger_evidence_daily_employment_income e
      LEFT JOIN institution_daily_employment_income_groups g ON g.id=e.daily_employment_income_group_id
      LEFT JOIN system_codes c ON c.code_group='BUSINESS_UNIT' AND c.code=g.business_unit AND c.is_active=1
      WHERE g.id IS NULL OR c.id IS NULL OR e.income_year_month NOT REGEXP '^[0-9]{4}-(0[1-9]|1[0-2])$' OR e.payment_date IS NULL
        OR e.total_work_days<0 OR e.total_gross_amount<0 OR e.total_deduction_amount<0 OR e.total_net_payment_amount<0 OR e.total_employer_burden_amount<0
        OR ROUND(e.total_gross_amount-e.total_deduction_amount,2)<>ROUND(e.total_net_payment_amount,2)
        OR e.evidence_status_code NOT IN ('CORRECTION_REQUIRED','COMPLETED')) THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='기존 일용 Evidence가 결정적 backfill 계약을 충족하지 않습니다.';
    END IF;
    IF NOT EXISTS (SELECT 1 FROM ledger_evidence_daily_employment_income e JOIN institution_daily_employment_income_groups g ON g.id=e.daily_employment_income_group_id WHERE e.business_unit IS NULL OR e.transaction_direction IS NULL
      OR e.operation_type IS NULL OR e.raw_income_year_month IS NULL OR e.raw_payment_date IS NULL OR e.raw_work_day_count IS NULL
      OR e.raw_gross_payment_amount IS NULL OR e.raw_worker_deduction_amount IS NULL OR e.raw_net_payment_amount IS NULL
      OR e.raw_employer_burden_amount IS NULL OR e.evidence_status IS NULL OR e.business_unit<>g.business_unit
      OR e.transaction_direction<>'EXPENSE' OR e.operation_type<>'DAILY_WORKER' OR e.raw_income_year_month<>e.income_year_month
      OR e.raw_payment_date<>e.payment_date OR e.raw_work_day_count<>e.total_work_days OR e.raw_gross_payment_amount<>e.total_gross_amount
      OR e.raw_worker_deduction_amount<>e.total_deduction_amount OR e.raw_net_payment_amount<>e.total_net_payment_amount
      OR e.raw_employer_burden_amount<>e.total_employer_burden_amount OR e.evidence_status<>e.evidence_status_code) THEN LEAVE procedure_body; END IF;
    IF EXISTS (SELECT 1 FROM ledger_evidence_daily_employment_income WHERE business_unit IS NOT NULL OR transaction_direction IS NOT NULL
      OR operation_type IS NOT NULL OR raw_income_year_month IS NOT NULL OR raw_payment_date IS NOT NULL OR raw_work_day_count IS NOT NULL
      OR raw_gross_payment_amount IS NOT NULL OR raw_worker_deduction_amount IS NOT NULL OR raw_net_payment_amount IS NOT NULL
      OR raw_employer_burden_amount IS NOT NULL OR evidence_status IS NOT NULL) THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='일용 Evidence backfill이 이미 실행됐거나 부분 적용된 상태입니다.';
    END IF;
    UPDATE ledger_evidence_daily_employment_income e JOIN institution_daily_employment_income_groups g ON g.id=e.daily_employment_income_group_id
    SET e.business_unit=g.business_unit,e.transaction_direction='EXPENSE',e.operation_type='DAILY_WORKER',
      e.raw_income_year_month=e.income_year_month,e.raw_payment_date=e.payment_date,e.raw_work_day_count=e.total_work_days,
      e.raw_gross_payment_amount=e.total_gross_amount,e.raw_worker_deduction_amount=e.total_deduction_amount,
      e.raw_net_payment_amount=e.total_net_payment_amount,e.raw_employer_burden_amount=e.total_employer_burden_amount,
      e.evidence_status=e.evidence_status_code;
    IF EXISTS (SELECT 1 FROM ledger_evidence_daily_employment_income e JOIN institution_daily_employment_income_groups g ON g.id=e.daily_employment_income_group_id
      WHERE e.business_unit<>g.business_unit OR e.transaction_direction<>'EXPENSE' OR e.operation_type<>'DAILY_WORKER'
       OR e.raw_income_year_month<>e.income_year_month OR e.raw_payment_date<>e.payment_date OR e.raw_work_day_count<>e.total_work_days
       OR e.raw_gross_payment_amount<>e.total_gross_amount OR e.raw_worker_deduction_amount<>e.total_deduction_amount
       OR e.raw_net_payment_amount<>e.total_net_payment_amount OR e.raw_employer_burden_amount<>e.total_employer_burden_amount
       OR e.evidence_status<>e.evidence_status_code) THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='일용 Evidence backfill 대사에 실패했습니다.';
    END IF;
END$$
DELIMITER ;
CALL backfill_daily_employment_income_evidence_raw();
DROP PROCEDURE backfill_daily_employment_income_evidence_raw;
