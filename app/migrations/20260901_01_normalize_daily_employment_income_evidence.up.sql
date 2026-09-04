DROP PROCEDURE IF EXISTS add_daily_employment_income_evidence_raw_columns;
DELIMITER $$
CREATE PROCEDURE add_daily_employment_income_evidence_raw_columns()
procedure_body: BEGIN
    DECLARE v_existing INT DEFAULT 0;
    IF (SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='ledger_evidence_daily_employment_income' AND TABLE_TYPE='BASE TABLE')<>1 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='일용근로소득 Evidence 테이블을 찾을 수 없습니다.';
    END IF;
    SELECT COUNT(*) INTO v_existing FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='ledger_evidence_daily_employment_income'
       AND COLUMN_NAME IN ('business_unit','transaction_direction','operation_type','raw_income_year_month','raw_payment_date','raw_work_day_count','raw_gross_payment_amount','raw_worker_deduction_amount','raw_net_payment_amount','raw_employer_burden_amount','evidence_status');
    IF v_existing=11 THEN LEAVE procedure_body; END IF;
    IF v_existing<>0 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='일용 Evidence 정규화 컬럼이 부분 적용된 상태입니다.'; END IF;
    IF EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='ledger_evidence_daily_employment_income'
      AND COLUMN_NAME IN ('work_day_count','gross_payment_amount','worker_deduction_amount','net_payment_amount','employer_burden_amount')) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='폐기된 무접두사 일용 Evidence 컬럼안이 감지되었습니다.';
    END IF;
    ALTER TABLE ledger_evidence_daily_employment_income
      ADD COLUMN business_unit VARCHAR(30) NULL COMMENT '승인 당시 사업구분' AFTER worker_client_id,
      ADD COLUMN transaction_direction VARCHAR(30) NULL COMMENT '승인 업무 거래방향' AFTER business_unit,
      ADD COLUMN operation_type VARCHAR(50) NULL COMMENT '승인 업무유형' AFTER transaction_direction,
      ADD COLUMN raw_income_year_month CHAR(7) NULL COMMENT '승인된 귀속연월 원천 사실' AFTER work_team_id,
      ADD COLUMN raw_payment_date DATE NULL COMMENT '승인된 지급일 원천 사실' AFTER raw_income_year_month,
      ADD COLUMN raw_work_day_count DECIMAL(10,2) NULL COMMENT '승인된 근무일수 원천 사실' AFTER total_work_days,
      ADD COLUMN raw_gross_payment_amount DECIMAL(18,2) NULL COMMENT '승인된 공제 전 지급액 원천 사실' AFTER total_gross_amount,
      ADD COLUMN raw_worker_deduction_amount DECIMAL(18,2) NULL COMMENT '승인된 근로자 공제액 원천 사실' AFTER total_deduction_amount,
      ADD COLUMN raw_net_payment_amount DECIMAL(18,2) NULL COMMENT '승인된 실지급액 원천 사실' AFTER total_net_payment_amount,
      ADD COLUMN raw_employer_burden_amount DECIMAL(18,2) NULL COMMENT '승인된 사용자부담액 원천 사실' AFTER total_employer_burden_amount,
      ADD COLUMN evidence_status VARCHAR(30) NULL COMMENT '사용자 검토·회계준비 상태' AFTER evidence_status_code;
END$$
DELIMITER ;
CALL add_daily_employment_income_evidence_raw_columns();
DROP PROCEDURE add_daily_employment_income_evidence_raw_columns;
