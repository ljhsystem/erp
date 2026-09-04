DROP PROCEDURE IF EXISTS migrate_20260826_05_employee_salary_report_evidence;
DELIMITER $$
CREATE PROCEDURE migrate_20260826_05_employee_salary_report_evidence()
BEGIN
    IF (SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='ledger_evidence_salary_report' AND TABLE_TYPE='BASE TABLE') <> 1
       OR (SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='institution_regular_employment_income_accounting_links' AND TABLE_TYPE='BASE TABLE') <> 1 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='직원별 급여 증빙 전환 대상 테이블을 찾을 수 없습니다.';
    END IF;
    IF (SELECT COUNT(*) FROM ledger_evidence_salary_report) <> 0
       OR (SELECT COUNT(*) FROM institution_regular_employment_income_accounting_links) <> 0 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='기존 증빙 또는 생성 Registry가 있어 직원별 구조로 전환할 수 없습니다.';
    END IF;
    IF (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='ledger_evidence_salary_report' AND COLUMN_NAME IN ('approval_request_id','regular_employment_income_item_id','raw_employer_burden_amount','approved_at','approved_by')) <> 0 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='직원별 급여 증빙 구조가 이미 적용됐거나 부분 적용된 상태입니다.';
    END IF;
    IF (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='ledger_evidence_salary_report' AND INDEX_NAME='uk_salary_report_source_income' AND NON_UNIQUE=0) <> 1 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='급여 증빙의 기존 Header 단독 UNIQUE가 예상과 다릅니다.';
    END IF;
    IF (SELECT COUNT(*) FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME='ledger_evidence_salary_report' AND CONSTRAINT_NAME='fk_salary_report_source_income') <> 1 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='급여 증빙 원본 문서 FK가 예상과 다릅니다.';
    END IF;
    IF (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME='institution_regular_employment_income_accounting_links' AND CONSTRAINT_NAME IN ('chk_regular_income_accounting_role','chk_regular_income_accounting_role_fields') AND CONSTRAINT_TYPE='CHECK') <> 2
       OR (SELECT COUNT(*) FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME='institution_regular_employment_income_accounting_links' AND CONSTRAINT_NAME='fk_regular_income_accounting_schedule') <> 1
       OR (SELECT CHECK_CLAUSE FROM information_schema.CHECK_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND CONSTRAINT_NAME='chk_regular_income_accounting_role') NOT LIKE '%INSTITUTION_LIABILITY%'
       OR (SELECT CHECK_CLAUSE FROM information_schema.CHECK_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND CONSTRAINT_NAME='chk_regular_income_accounting_role_fields') NOT LIKE '%payment_schedule_id%is null%' THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Accounting Registry 운영 기준선이 예상과 다릅니다.';
    END IF;

    ALTER TABLE ledger_evidence_salary_report
        DROP INDEX uk_salary_report_source_income,
        ADD COLUMN approval_request_id varchar(36) NOT NULL COMMENT '최종승인 결재요청' AFTER source_regular_employment_income_id,
        ADD COLUMN regular_employment_income_item_id varchar(36) NOT NULL COMMENT '직원별 급여 Item' AFTER approval_request_id,
        MODIFY employee_id varchar(36) NOT NULL COMMENT '직원 식별자',
        ADD COLUMN raw_employer_burden_amount decimal(18,2) NOT NULL DEFAULT 0 COMMENT '직원별 사용자부담 합계' AFTER raw_net_payment_amount,
        ADD COLUMN approved_at datetime NOT NULL COMMENT '최종승인 시각' AFTER evidence_status,
        ADD COLUMN approved_by varchar(100) NOT NULL COMMENT '최종승인 Actor' AFTER approved_at,
        ADD UNIQUE KEY uk_salary_report_source_item (source_regular_employment_income_id,regular_employment_income_item_id),
        ADD UNIQUE KEY uk_salary_report_approval_item (approval_request_id,regular_employment_income_item_id),
        ADD KEY idx_salary_report_employee_period (employee_id,raw_income_year_month),
        ADD CONSTRAINT fk_salary_report_approval_request FOREIGN KEY (approval_request_id) REFERENCES user_approval_requests(id),
        ADD CONSTRAINT fk_salary_report_income_item FOREIGN KEY (regular_employment_income_item_id) REFERENCES institution_regular_employment_income_items(id),
        ADD CONSTRAINT fk_salary_report_employee FOREIGN KEY (employee_id) REFERENCES user_employees(id);

    ALTER TABLE institution_regular_employment_income_accounting_links
        DROP CONSTRAINT chk_regular_income_accounting_role_fields,
        DROP CONSTRAINT chk_regular_income_accounting_role,
        ADD CONSTRAINT chk_regular_income_accounting_role CHECK (generation_role IN ('PAYROLL_REPORT_EVIDENCE','EMPLOYEE_PAYROLL')),
        ADD CONSTRAINT chk_regular_income_accounting_role_fields CHECK (
            (generation_role='PAYROLL_REPORT_EVIDENCE' AND regular_employment_income_item_id IS NOT NULL AND evidence_id IS NOT NULL AND transaction_id IS NULL AND payment_schedule_id IS NULL AND recognition_date IS NULL)
            OR (generation_role='EMPLOYEE_PAYROLL' AND regular_employment_income_item_id IS NOT NULL AND evidence_id IS NOT NULL AND transaction_id IS NOT NULL AND payment_schedule_id IS NULL AND recognition_date IS NOT NULL)
        );
END$$
DELIMITER ;
CALL migrate_20260826_05_employee_salary_report_evidence();
DROP PROCEDURE migrate_20260826_05_employee_salary_report_evidence;
