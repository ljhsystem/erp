SET NAMES utf8mb4;

DELIMITER $$
CREATE PROCEDURE migrate_20260903_16_remove_income_scheduled_payment_dates()
BEGIN
    IF (SELECT COUNT(*) FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA=DATABASE() AND (
            (TABLE_NAME='institution_regular_employment_incomes' AND COLUMN_NAME IN ('payment_date','nominal_payment_date','proposed_payment_date','payment_date_override_reason')) OR
            (TABLE_NAME='institution_daily_employment_incomes' AND COLUMN_NAME='payment_date') OR
            (TABLE_NAME='institution_daily_employment_income_calculation_results' AND COLUMN_NAME='payment_date') OR
            (TABLE_NAME='institution_business_income_groups' AND COLUMN_NAME='payment_date') OR
            (TABLE_NAME='ledger_evidence_salary_report' AND COLUMN_NAME='raw_payment_date') OR
            (TABLE_NAME='ledger_evidence_daily_employment_income' AND COLUMN_NAME IN ('payment_date','raw_payment_date')) OR
            (TABLE_NAME='ledger_evidence_business_income' AND COLUMN_NAME='raw_payment_date')
        ))<>11 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='소득 지급예정일 제거 대상 컬럼이 완전하지 않습니다.';
    END IF;
    IF EXISTS (
        SELECT 1
        FROM (
            SELECT calculation_revision_id,result_type_code,worker_client_id,daily_employment_income_item_id,
                   workplace_scope_key,workday_scope_key,application_from,application_to,payment_sequence,COUNT(*) row_count
            FROM institution_daily_employment_income_calculation_results
            GROUP BY calculation_revision_id,result_type_code,worker_client_id,daily_employment_income_item_id,
                     workplace_scope_key,workday_scope_key,application_from,application_to,payment_sequence
            HAVING row_count>1
        ) duplicate_grain
    ) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='일용 계산결과의 지급일 제거 후 Grain이 충돌합니다.';
    END IF;
    IF EXISTS (SELECT 1 FROM institution_business_income_items) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='사업소득 기존 항목의 실제 거래일 확인 후 별도 전환이 필요합니다.';
    END IF;

    ALTER TABLE institution_regular_employment_incomes
        DROP CONSTRAINT chk_regular_income_payment_override,
        DROP INDEX idx_institution_regular_employment_income_payment_date,
        DROP COLUMN nominal_payment_date,
        DROP COLUMN proposed_payment_date,
        DROP COLUMN payment_date_override_reason,
        DROP COLUMN payment_date,
        ALGORITHM=INPLACE, LOCK=NONE;

    ALTER TABLE institution_daily_employment_incomes
        DROP COLUMN payment_date,
        ALGORITHM=INPLACE, LOCK=NONE;

    ALTER TABLE institution_daily_employment_income_calculation_results
        DROP INDEX uq_daily_calc_result_grain,
        DROP INDEX idx_daily_calc_result_worker,
        DROP COLUMN payment_date,
        ADD UNIQUE KEY uq_daily_calc_result_grain (
            calculation_revision_id,result_type_code,worker_client_id,daily_employment_income_item_id,
            workplace_scope_key,workday_scope_key,application_from,application_to,payment_sequence
        ),
        ADD KEY idx_daily_calc_result_worker (worker_client_id,result_type_code),
        ALGORITHM=INPLACE, LOCK=NONE;

    ALTER TABLE institution_business_income_items
        ADD COLUMN transaction_date DATE NOT NULL COMMENT '거래일' AFTER client_tax_profile_id,
        ALGORITHM=INPLACE, LOCK=NONE;

    ALTER TABLE institution_business_income_groups
        DROP COLUMN payment_date,
        ALGORITHM=INSTANT, LOCK=NONE;

    ALTER TABLE ledger_evidence_salary_report
        DROP INDEX idx_salary_report_payment_date,
        DROP COLUMN raw_payment_date,
        ALGORITHM=INPLACE, LOCK=NONE;

    ALTER TABLE ledger_evidence_daily_employment_income
        DROP COLUMN raw_payment_date,
        DROP COLUMN payment_date,
        ALGORITHM=INSTANT, LOCK=NONE;

    ALTER TABLE ledger_evidence_business_income
        DROP INDEX idx_business_income_evidence_list,
        DROP COLUMN raw_payment_date,
        ADD KEY idx_business_income_evidence_list (raw_income_year_month,sort_no,id),
        ALGORITHM=INPLACE, LOCK=NONE;
END$$
DELIMITER ;

CALL migrate_20260903_16_remove_income_scheduled_payment_dates();
DROP PROCEDURE migrate_20260903_16_remove_income_scheduled_payment_dates;
