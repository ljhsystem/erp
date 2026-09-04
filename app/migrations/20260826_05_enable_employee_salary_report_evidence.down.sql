DROP PROCEDURE IF EXISTS rollback_20260826_05_employee_salary_report_evidence;
DELIMITER $$
CREATE PROCEDURE rollback_20260826_05_employee_salary_report_evidence()
BEGIN
    IF EXISTS (SELECT 1 FROM ledger_evidence_salary_report LIMIT 1)
       OR EXISTS (SELECT 1 FROM institution_regular_employment_income_accounting_links LIMIT 1) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='직원별 급여 증빙 또는 Registry가 있어 이전 구조로 복원할 수 없습니다.';
    END IF;

    ALTER TABLE institution_regular_employment_income_accounting_links
        DROP CONSTRAINT chk_regular_income_accounting_role_fields,
        DROP CONSTRAINT chk_regular_income_accounting_role,
        ADD CONSTRAINT chk_regular_income_accounting_role CHECK (generation_role IN ('EMPLOYEE_PAYROLL','INSTITUTION_LIABILITY','PAYROLL_REPORT_EVIDENCE')),
        ADD CONSTRAINT chk_regular_income_accounting_role_fields CHECK (
            (generation_role='PAYROLL_REPORT_EVIDENCE' AND regular_employment_income_item_id IS NULL AND evidence_id IS NOT NULL AND transaction_id IS NULL AND payment_schedule_id IS NULL AND recognition_date IS NULL)
            OR (generation_role='EMPLOYEE_PAYROLL' AND regular_employment_income_item_id IS NOT NULL AND evidence_id IS NOT NULL AND transaction_id IS NOT NULL AND payment_schedule_id IS NULL AND recognition_date IS NOT NULL)
            OR (generation_role='INSTITUTION_LIABILITY' AND regular_employment_income_item_id IS NULL AND evidence_id IS NOT NULL AND transaction_id IS NOT NULL AND payment_schedule_id IS NULL AND recognition_date IS NOT NULL)
        );

    ALTER TABLE ledger_evidence_salary_report
        DROP FOREIGN KEY fk_salary_report_employee,
        DROP FOREIGN KEY fk_salary_report_income_item,
        DROP FOREIGN KEY fk_salary_report_approval_request,
        DROP INDEX idx_salary_report_employee_period,
        DROP INDEX uk_salary_report_approval_item,
        DROP INDEX uk_salary_report_source_item,
        DROP COLUMN approved_by,
        DROP COLUMN approved_at,
        DROP COLUMN raw_employer_burden_amount,
        MODIFY employee_id varchar(36) NULL COMMENT '직원 식별자',
        DROP COLUMN regular_employment_income_item_id,
        DROP COLUMN approval_request_id,
        ADD UNIQUE KEY uk_salary_report_source_income (source_regular_employment_income_id);
END$$
DELIMITER ;
CALL rollback_20260826_05_employee_salary_report_evidence();
DROP PROCEDURE rollback_20260826_05_employee_salary_report_evidence;
