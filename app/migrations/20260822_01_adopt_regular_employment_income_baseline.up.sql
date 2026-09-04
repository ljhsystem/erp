DELIMITER $$
DROP PROCEDURE IF EXISTS adopt_regular_employment_income_baseline$$
CREATE PROCEDURE adopt_regular_employment_income_baseline()
BEGIN
    DECLARE v_exists INT DEFAULT 0;
    DECLARE v_columns INT DEFAULT 0;

    SELECT COUNT(*) INTO v_exists FROM information_schema.TABLES
     WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='institution_regular_employment_incomes';
    IF v_exists=0 THEN
        CREATE TABLE institution_regular_employment_incomes (
          id varchar(36) NOT NULL COMMENT 'ID', sort_no bigint unsigned NOT NULL COMMENT '순번',
          income_year_month char(7) NOT NULL COMMENT '소득귀속연월 YYYY-MM', payment_date date NOT NULL COMMENT '지급일자',
          title varchar(200) NOT NULL COMMENT '문서제목', employee_count int unsigned NOT NULL DEFAULT 0 COMMENT '상용근로자수',
          gross_amount decimal(18,2) NOT NULL DEFAULT 0 COMMENT '지급총액', taxable_amount decimal(18,2) NOT NULL DEFAULT 0 COMMENT '과세소득합계',
          non_taxable_amount decimal(18,2) NOT NULL DEFAULT 0 COMMENT '비과세소득합계', income_tax_amount decimal(18,2) NOT NULL DEFAULT 0 COMMENT '근로소득세합계',
          local_income_tax_amount decimal(18,2) NOT NULL DEFAULT 0 COMMENT '지방소득세합계', national_pension_amount decimal(18,2) NOT NULL DEFAULT 0 COMMENT '국민연금공제합계',
          health_insurance_amount decimal(18,2) NOT NULL DEFAULT 0 COMMENT '건강보험공제합계', long_term_care_amount decimal(18,2) NOT NULL DEFAULT 0 COMMENT '장기요양보험공제합계',
          employment_insurance_amount decimal(18,2) NOT NULL DEFAULT 0 COMMENT '고용보험공제합계', other_deduction_amount decimal(18,2) NOT NULL DEFAULT 0 COMMENT '기타공제합계',
          deduction_amount decimal(18,2) NOT NULL DEFAULT 0 COMMENT '공제총액', net_payment_amount decimal(18,2) NOT NULL DEFAULT 0 COMMENT '실지급액합계',
          document_status varchar(30) NOT NULL DEFAULT 'DRAFT' COMMENT '문서상태', current_approval_request_id varchar(36) NULL COMMENT '현재결재요청',
          approved_at datetime NULL COMMENT '최종승인일시', description varchar(500) NULL COMMENT '비고', memo text NULL COMMENT '메모',
          created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '생성일시', created_by varchar(100) NOT NULL COMMENT '생성자',
          updated_at datetime NULL COMMENT '수정일시', updated_by varchar(100) NULL COMMENT '수정자', deleted_at datetime NULL COMMENT '삭제일시', deleted_by varchar(100) NULL COMMENT '삭제자',
          PRIMARY KEY(id), UNIQUE KEY uk_institution_regular_employment_income_sort(sort_no),
          UNIQUE KEY uk_institution_regular_employment_income_period(income_year_month), KEY idx_institution_regular_employment_income_payment_date(payment_date),
          KEY idx_institution_regular_employment_income_status(document_status,deleted_at), KEY idx_institution_regular_employment_income_approval(current_approval_request_id),
          CONSTRAINT fk_institution_regular_employment_income_approval FOREIGN KEY(current_approval_request_id) REFERENCES user_approval_requests(id) ON DELETE SET NULL ON UPDATE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='상용근로소득 관리 헤더';
    ELSE
        SELECT COUNT(*) INTO v_columns FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='institution_regular_employment_incomes';
        IF v_columns<>29 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='상용근로소득 Header가 승인된 Baseline 구조와 다릅니다.'; END IF;
    END IF;

    SELECT COUNT(*) INTO v_exists FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='institution_regular_employment_income_items';
    IF v_exists=0 THEN
        CREATE TABLE institution_regular_employment_income_items (
          id varchar(36) NOT NULL COMMENT 'ID', sort_no int unsigned NOT NULL COMMENT '문서내순번', regular_employment_income_id varchar(36) NOT NULL COMMENT '상용근로소득 헤더',
          employee_id varchar(36) NOT NULL COMMENT '직원', employee_name_snapshot varchar(100) NOT NULL COMMENT '근로자명스냅샷', employee_identifier_snapshot varchar(255) NULL COMMENT '근로자식별정보암호문스냅샷',
          department_name_snapshot varchar(100) NULL COMMENT '부서명스냅샷', position_name_snapshot varchar(100) NULL COMMENT '직위·직책스냅샷', employment_contract_id varchar(36) NULL COMMENT '적용근로계약',
          base_salary_amount decimal(18,2) NOT NULL DEFAULT 0 COMMENT '기본급', allowance_amount decimal(18,2) NOT NULL DEFAULT 0 COMMENT '과세수당', bonus_amount decimal(18,2) NOT NULL DEFAULT 0 COMMENT '상여금',
          non_taxable_amount decimal(18,2) NOT NULL DEFAULT 0 COMMENT '비과세소득', gross_amount decimal(18,2) NOT NULL DEFAULT 0 COMMENT '지급총액', taxable_amount decimal(18,2) NOT NULL DEFAULT 0 COMMENT '과세소득금액',
          national_pension_amount decimal(18,2) NOT NULL DEFAULT 0 COMMENT '국민연금', health_insurance_amount decimal(18,2) NOT NULL DEFAULT 0 COMMENT '건강보험', long_term_care_amount decimal(18,2) NOT NULL DEFAULT 0 COMMENT '장기요양보험',
          employment_insurance_amount decimal(18,2) NOT NULL DEFAULT 0 COMMENT '고용보험', income_tax_amount decimal(18,2) NOT NULL DEFAULT 0 COMMENT '근로소득세', local_income_tax_amount decimal(18,2) NOT NULL DEFAULT 0 COMMENT '지방소득세',
          other_deduction_amount decimal(18,2) NOT NULL DEFAULT 0 COMMENT '기타공제', deduction_amount decimal(18,2) NOT NULL DEFAULT 0 COMMENT '공제총액', net_payment_amount decimal(18,2) NOT NULL DEFAULT 0 COMMENT '실지급액',
          description varchar(500) NULL COMMENT '비고', memo text NULL COMMENT '메모', created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '생성일시', created_by varchar(100) NOT NULL COMMENT '생성자',
          updated_at datetime NULL COMMENT '수정일시', updated_by varchar(100) NULL COMMENT '수정자', deleted_at datetime NULL COMMENT '삭제일시', deleted_by varchar(100) NULL COMMENT '삭제자',
          PRIMARY KEY(id), UNIQUE KEY uk_institution_regular_employment_income_item_sort(regular_employment_income_id,sort_no),
          UNIQUE KEY uk_institution_regular_employment_income_employee(regular_employment_income_id,employee_id), KEY idx_institution_regular_employment_income_item_employee(employee_id),
          KEY idx_institution_regular_employment_income_item_contract(employment_contract_id), KEY idx_institution_regular_employment_income_item_deleted(deleted_at),
          CONSTRAINT fk_institution_regular_employment_income_item_header FOREIGN KEY(regular_employment_income_id) REFERENCES institution_regular_employment_incomes(id) ON DELETE CASCADE,
          CONSTRAINT fk_institution_regular_employment_income_item_employee FOREIGN KEY(employee_id) REFERENCES user_employees(id) ON UPDATE CASCADE,
          CONSTRAINT fk_institution_regular_employment_income_item_contract FOREIGN KEY(employment_contract_id) REFERENCES institution_employment_contracts(id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='상용근로소득 직원별 상세';
    ELSE
        SELECT COUNT(*) INTO v_columns FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='institution_regular_employment_income_items';
        IF v_columns<>32 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='상용근로소득 Detail이 승인된 Baseline 구조와 다릅니다.'; END IF;
    END IF;

    SELECT COUNT(*) INTO v_exists FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='ledger_evidence_salary_report';
    IF v_exists=0 THEN
        CREATE TABLE ledger_evidence_salary_report (
          id varchar(36) NOT NULL COMMENT 'ID', sort_no int unsigned NOT NULL COMMENT '순번', external_key varchar(120) NOT NULL COMMENT '외부원본식별값', source_type varchar(40) NOT NULL COMMENT '자료출처',
          import_type varchar(30) NOT NULL COMMENT '자료유형', source_regular_employment_income_id varchar(36) NOT NULL COMMENT '상용근로소득 원본', business_unit varchar(30) NULL COMMENT '사업구분',
          transaction_direction varchar(30) NULL COMMENT '거래구분', operation_type varchar(30) NULL COMMENT '업무유형', raw_income_year_month char(7) NOT NULL COMMENT '귀속연월', raw_payment_date date NOT NULL COMMENT '지급일자',
          raw_employee_count int unsigned NOT NULL DEFAULT 0 COMMENT '근로자수', raw_gross_amount decimal(18,2) NOT NULL DEFAULT 0 COMMENT '지급총액', raw_taxable_amount decimal(18,2) NOT NULL DEFAULT 0 COMMENT '과세소득합계',
          raw_non_taxable_amount decimal(18,2) NOT NULL DEFAULT 0 COMMENT '비과세소득합계', raw_income_tax_amount decimal(18,2) NOT NULL DEFAULT 0 COMMENT '근로소득세합계', raw_local_income_tax_amount decimal(18,2) NOT NULL DEFAULT 0 COMMENT '지방소득세합계',
          raw_national_pension_amount decimal(18,2) NOT NULL DEFAULT 0 COMMENT '국민연금합계', raw_health_insurance_amount decimal(18,2) NOT NULL DEFAULT 0 COMMENT '건강보험합계', raw_long_term_care_amount decimal(18,2) NOT NULL DEFAULT 0 COMMENT '장기요양보험합계',
          raw_employment_insurance_amount decimal(18,2) NOT NULL DEFAULT 0 COMMENT '고용보험합계', raw_other_deduction_amount decimal(18,2) NOT NULL DEFAULT 0 COMMENT '기타공제합계', raw_deduction_amount decimal(18,2) NOT NULL DEFAULT 0 COMMENT '공제총액',
          raw_net_payment_amount decimal(18,2) NOT NULL DEFAULT 0 COMMENT '실지급액합계', raw_description varchar(500) NULL COMMENT '비고', evidence_status varchar(30) NOT NULL COMMENT '증빙상태',
          created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '생성일시', created_by varchar(100) NULL COMMENT '생성자', updated_at datetime NULL COMMENT '수정일시', updated_by varchar(100) NULL COMMENT '수정자', deleted_at datetime NULL COMMENT '삭제일시', deleted_by varchar(100) NULL COMMENT '삭제자',
          PRIMARY KEY(id), UNIQUE KEY uk_salary_report_external_key(external_key), UNIQUE KEY uk_salary_report_source_income(source_regular_employment_income_id),
          KEY idx_salary_report_income_period(raw_income_year_month), KEY idx_salary_report_payment_date(raw_payment_date), KEY idx_salary_report_status(evidence_status), KEY idx_salary_report_deleted_at(deleted_at),
          CONSTRAINT fk_salary_report_source_income FOREIGN KEY(source_regular_employment_income_id) REFERENCES institution_regular_employment_incomes(id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='자료유형(IMPORT_TYPE): 급여(신고)(SALARY_REPORT) 증빙 테이블';
    ELSE
        SELECT COUNT(*) INTO v_columns FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='ledger_evidence_salary_report';
        IF v_columns<>32 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='급여(신고) Evidence가 승인된 Baseline 구조와 다릅니다.'; END IF;
    END IF;
END$$
CALL adopt_regular_employment_income_baseline()$$
DROP PROCEDURE adopt_regular_employment_income_baseline$$
DELIMITER ;
