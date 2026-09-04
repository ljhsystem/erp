ALTER TABLE institution_regular_employment_incomes
  ADD COLUMN company_id varchar(50) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NULL COMMENT '회사 SSOT' AFTER id,
  ADD COLUMN correction_of_id varchar(36) NULL COMMENT '정정 원 승인문서' AFTER current_approval_request_id,
  ADD COLUMN revision_no int unsigned NOT NULL DEFAULT 1 COMMENT '정정개정번호' AFTER correction_of_id,
  ADD COLUMN calculation_version varchar(30) NOT NULL DEFAULT 'REGULAR_INCOME_V1' COMMENT '계산정책버전' AFTER revision_no,
  ADD COLUMN calculation_source_code varchar(30) NOT NULL DEFAULT 'CALCULATED' COMMENT '계산원천' AFTER calculation_version,
  ADD COLUMN request_key varchar(100) NULL COMMENT '멱등요청키' AFTER calculation_source_code,
  ADD COLUMN snapshot_at datetime NULL COMMENT '승인스냅샷 확정일시' AFTER approved_at,
  ADD CONSTRAINT fk_regular_income_company FOREIGN KEY(company_id) REFERENCES system_company(id),
  ADD CONSTRAINT fk_regular_income_correction FOREIGN KEY(correction_of_id) REFERENCES institution_regular_employment_incomes(id),
  ADD CONSTRAINT chk_regular_income_revision CHECK(revision_no>=1),
  ADD CONSTRAINT chk_regular_income_source CHECK(calculation_source_code IN('CALCULATED','MANUAL','HISTORICAL_IMPORT')),
  ADD UNIQUE KEY uk_regular_income_request(request_key),
  ADD KEY idx_regular_income_company_month(company_id,income_year_month),
  ADD KEY idx_regular_income_correction(correction_of_id,revision_no);

ALTER TABLE institution_regular_employment_income_items
  ADD COLUMN calculation_status_code varchar(30) NOT NULL DEFAULT 'NEEDS_CONFIRMATION' COMMENT '직원별 계산상태' AFTER employment_contract_id,
  ADD COLUMN calculation_message varchar(1000) NULL COMMENT '확인필요 사유' AFTER calculation_status_code,
  ADD COLUMN calculation_source_code varchar(30) NOT NULL DEFAULT 'CALCULATED' COMMENT '계산원천' AFTER calculation_message,
  ADD COLUMN employer_burden_amount decimal(18,2) NOT NULL DEFAULT 0 COMMENT '회사부담 합계' AFTER net_payment_amount,
  ADD COLUMN confirmed_at datetime NULL COMMENT '확정일시' AFTER employer_burden_amount,
  ADD COLUMN confirmed_by varchar(100) NULL COMMENT '확정자' AFTER confirmed_at,
  ADD CONSTRAINT chk_regular_income_item_status CHECK(calculation_status_code IN('CALCULATED','NEEDS_CONFIRMATION','CONFIRMED','MANUAL')),
  ADD CONSTRAINT chk_regular_income_item_source CHECK(calculation_source_code IN('CALCULATED','MANUAL','HISTORICAL_IMPORT'));

CREATE TABLE institution_regular_employment_income_line_items (
  id varchar(36) NOT NULL COMMENT 'ID', regular_employment_income_item_id varchar(36) NOT NULL COMMENT '직원별 소득상세', sort_no int unsigned NOT NULL COMMENT '표시순번',
  item_type_code varchar(30) NOT NULL COMMENT 'PAY/DEDUCTION/EMPLOYER_BURDEN', item_code varchar(60) NOT NULL COMMENT '안정 항목코드', item_name_snapshot varchar(100) NOT NULL COMMENT '승인 당시 항목명',
  taxable_flag tinyint(1) NULL COMMENT '지급항목 과세여부', calculated_amount decimal(18,2) NOT NULL DEFAULT 0 COMMENT '자동계산금액', adjustment_amount decimal(18,2) NOT NULL DEFAULT 0 COMMENT '관리자조정금액',
  final_amount decimal(18,2) NOT NULL DEFAULT 0 COMMENT '확정금액', adjustment_reason varchar(500) NULL COMMENT '조정사유', calculation_source_code varchar(30) NOT NULL DEFAULT 'CALCULATED' COMMENT '계산원천',
  created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP, created_by varchar(100) NOT NULL, updated_at datetime NULL, updated_by varchar(100) NULL,
  PRIMARY KEY(id), UNIQUE KEY uk_regular_income_line_item(regular_employment_income_item_id,item_type_code,item_code),
  KEY idx_regular_income_line_item_type(item_type_code,item_code),
  CONSTRAINT fk_regular_income_line_item_detail FOREIGN KEY(regular_employment_income_item_id) REFERENCES institution_regular_employment_income_items(id) ON DELETE CASCADE,
  CONSTRAINT chk_regular_income_line_type CHECK(item_type_code IN('PAY','DEDUCTION','EMPLOYER_BURDEN')),
  CONSTRAINT chk_regular_income_line_source CHECK(calculation_source_code IN('CALCULATED','MANUAL','HISTORICAL_IMPORT')),
  CONSTRAINT chk_regular_income_line_final CHECK(final_amount=calculated_amount+adjustment_amount),
  CONSTRAINT chk_regular_income_line_adjustment CHECK(adjustment_amount=0 OR (adjustment_reason IS NOT NULL AND CHAR_LENGTH(TRIM(adjustment_reason))>0))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='상용근로소득 동적 지급·공제·회사부담 항목';

CREATE TABLE institution_regular_employment_income_calculation_bases (
  id varchar(36) NOT NULL, regular_employment_income_item_id varchar(36) NOT NULL, basis_type_code varchar(40) NOT NULL COMMENT '계산근거유형',
  source_table varchar(100) NOT NULL COMMENT '근거 저장소', source_id varchar(36) NOT NULL COMMENT '근거 ID', source_revision varchar(50) NULL COMMENT '근거 Revision',
  effective_from date NULL, effective_to date NULL, basis_code varchar(60) NULL COMMENT '법정기준 Type 등', basis_summary varchar(500) NULL COMMENT '사람이 읽는 근거요약',
  created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP, created_by varchar(100) NOT NULL,
  PRIMARY KEY(id), UNIQUE KEY uk_regular_income_basis(regular_employment_income_item_id,basis_type_code,source_table,source_id,source_revision),
  KEY idx_regular_income_basis_source(source_table,source_id),
  CONSTRAINT fk_regular_income_basis_detail FOREIGN KEY(regular_employment_income_item_id) REFERENCES institution_regular_employment_income_items(id) ON DELETE CASCADE,
  CONSTRAINT chk_regular_income_basis_type CHECK(basis_type_code IN('EMPLOYMENT_CONTRACT','ATTENDANCE_CLOSURE','LEAVE_USAGE','STATUTORY_STANDARD','INSURANCE_ELIGIBILITY'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='상용근로소득 계산근거 참조';

CREATE TABLE institution_regular_employment_income_audits (
  id varchar(36) NOT NULL, regular_employment_income_id varchar(36) NOT NULL, regular_employment_income_item_id varchar(36) NULL,
  action_code varchar(40) NOT NULL, reason varchar(500) NULL, before_value json NULL, after_value json NULL, request_key varchar(100) NULL,
  acted_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP, acted_by varchar(100) NOT NULL,
  PRIMARY KEY(id), UNIQUE KEY uk_regular_income_audit_request(request_key), KEY idx_regular_income_audit_document(regular_employment_income_id,acted_at),
  CONSTRAINT fk_regular_income_audit_header FOREIGN KEY(regular_employment_income_id) REFERENCES institution_regular_employment_incomes(id),
  CONSTRAINT fk_regular_income_audit_detail FOREIGN KEY(regular_employment_income_item_id) REFERENCES institution_regular_employment_income_items(id),
  CONSTRAINT chk_regular_income_audit_action CHECK(action_code IN('CREATE','CALCULATE','RECALCULATE','ADJUST','SUBMIT','WITHDRAW','APPROVE','ACCOUNTING_MATERIALIZE','CORRECT','CANCEL'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='상용근로소득 업무 감사원장';

CREATE TABLE institution_regular_employment_income_accounting_links (
  id varchar(36) NOT NULL, regular_employment_income_id varchar(36) NOT NULL, regular_employment_income_item_id varchar(36) NOT NULL,
  evidence_id varchar(36) NOT NULL, transaction_id varchar(36) NOT NULL, payment_schedule_id varchar(36) NOT NULL, request_key varchar(100) NOT NULL,
  created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP, created_by varchar(100) NOT NULL,
  PRIMARY KEY(id), UNIQUE KEY uk_regular_income_accounting_detail(regular_employment_income_item_id), UNIQUE KEY uk_regular_income_accounting_request(request_key),
  UNIQUE KEY uk_regular_income_accounting_transaction(transaction_id), UNIQUE KEY uk_regular_income_accounting_schedule(payment_schedule_id),
  CONSTRAINT fk_regular_income_accounting_header FOREIGN KEY(regular_employment_income_id) REFERENCES institution_regular_employment_incomes(id),
  CONSTRAINT fk_regular_income_accounting_detail FOREIGN KEY(regular_employment_income_item_id) REFERENCES institution_regular_employment_income_items(id),
  CONSTRAINT fk_regular_income_accounting_evidence FOREIGN KEY(evidence_id) REFERENCES ledger_evidence_salary_report(id),
  CONSTRAINT fk_regular_income_accounting_transaction FOREIGN KEY(transaction_id) REFERENCES ledger_transactions(id),
  CONSTRAINT fk_regular_income_accounting_schedule FOREIGN KEY(payment_schedule_id) REFERENCES ledger_payment_schedules(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='상용근로소득 직원별 회계반영 멱등 연결';
