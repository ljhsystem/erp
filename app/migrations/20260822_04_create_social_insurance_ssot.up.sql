CREATE TABLE institution_social_insurance_coverages (
  id varchar(36) NOT NULL COMMENT '사회보험 적용기간 ID', employee_id varchar(36) NOT NULL COMMENT '직원 SSOT',
  insurance_type_code varchar(50) NOT NULL COMMENT '법정기준 보험종류 코드', coverage_status_code varchar(30) NOT NULL COMMENT 'ACQUIRED/EXCLUDED',
  effective_from date NOT NULL COMMENT '자격 적용 시작일', effective_to date NULL COMMENT '자격 적용 종료일',
  exclusion_reason_code varchar(50) NULL COMMENT '공식 적용제외 사유코드', exclusion_reason varchar(500) NULL COMMENT '적용제외 근거 설명',
  source_type_code varchar(30) NOT NULL COMMENT 'OFFICIAL_CONFIRMED/MANUAL_CONFIRMED/HISTORICAL_IMPORT', source_id varchar(36) NULL COMMENT '향후 신고·고지 원문 ID',
  confirmed_at datetime NULL COMMENT '공식 확인일시', note varchar(500) NULL COMMENT '비고', request_key varchar(100) NOT NULL COMMENT '멱등 요청키',
  created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP, created_by varchar(100) NOT NULL, updated_at datetime NULL, updated_by varchar(100) NULL,
  PRIMARY KEY(id), UNIQUE KEY uk_social_insurance_coverage_request(request_key),
  KEY idx_social_insurance_coverage_period(employee_id,insurance_type_code,effective_from,effective_to),
  KEY idx_social_insurance_coverage_batch(insurance_type_code,effective_from,effective_to,employee_id),
  CONSTRAINT fk_social_insurance_coverage_employee FOREIGN KEY(employee_id) REFERENCES user_employees(id),
  CONSTRAINT chk_social_insurance_coverage_type CHECK(insurance_type_code IN('NATIONAL_PENSION','HEALTH_INSURANCE','LONG_TERM_CARE','EMPLOYMENT_INSURANCE')),
  CONSTRAINT chk_social_insurance_coverage_status CHECK(coverage_status_code IN('ACQUIRED','EXCLUDED')),
  CONSTRAINT chk_social_insurance_coverage_source CHECK(source_type_code IN('OFFICIAL_CONFIRMED','MANUAL_CONFIRMED','HISTORICAL_IMPORT')),
  CONSTRAINT chk_social_insurance_coverage_period CHECK(effective_to IS NULL OR effective_to>=effective_from),
  CONSTRAINT chk_social_insurance_exclusion_reason CHECK(coverage_status_code<>'EXCLUDED' OR exclusion_reason_code IS NOT NULL OR CHAR_LENGTH(TRIM(exclusion_reason))>0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='직원 사회보험 실제 취득·적용제외 기간 SSOT';

CREATE TABLE institution_social_insurance_assessment_bases (
  id varchar(36) NOT NULL COMMENT '사회보험 산정기준 ID', coverage_id varchar(36) NOT NULL COMMENT '사회보험 적용기간',
  basis_type_code varchar(40) NOT NULL COMMENT '기준소득월액/보수월액/보험료 산정대상 보수',
  effective_from date NOT NULL COMMENT '산정기준 시작일', effective_to date NULL COMMENT '산정기준 종료일', basis_amount decimal(18,2) NOT NULL COMMENT '공식 산정기준 금액',
  confirmation_status_code varchar(30) NOT NULL COMMENT 'PROVISIONAL/CONFIRMED', source_type_code varchar(30) NOT NULL COMMENT 'OFFICIAL_CONFIRMED/MANUAL_CONFIRMED/HISTORICAL_IMPORT',
  source_id varchar(36) NULL COMMENT '향후 신고·고지 원문 ID', confirmed_at datetime NULL COMMENT '확정일시', note varchar(500) NULL COMMENT '근거 설명', request_key varchar(100) NOT NULL COMMENT '멱등 요청키',
  created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP, created_by varchar(100) NOT NULL, updated_at datetime NULL, updated_by varchar(100) NULL,
  PRIMARY KEY(id), UNIQUE KEY uk_social_insurance_basis_request(request_key), KEY idx_social_insurance_basis_period(coverage_id,basis_type_code,effective_from,effective_to),
  CONSTRAINT fk_social_insurance_basis_coverage FOREIGN KEY(coverage_id) REFERENCES institution_social_insurance_coverages(id),
  CONSTRAINT chk_social_insurance_basis_type CHECK(basis_type_code IN('STANDARD_MONTHLY_INCOME','MONTHLY_REMUNERATION','INSURABLE_REMUNERATION')),
  CONSTRAINT chk_social_insurance_basis_status CHECK(confirmation_status_code IN('PROVISIONAL','CONFIRMED')),
  CONSTRAINT chk_social_insurance_basis_source CHECK(source_type_code IN('OFFICIAL_CONFIRMED','MANUAL_CONFIRMED','HISTORICAL_IMPORT')),
  CONSTRAINT chk_social_insurance_basis_amount CHECK(basis_amount>=0), CONSTRAINT chk_social_insurance_basis_period CHECK(effective_to IS NULL OR effective_to>=effective_from)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='직원 사회보험 산정기준 기간이력 SSOT';

CREATE TABLE institution_social_insurance_audits (
  id varchar(36) NOT NULL, target_type_code varchar(30) NOT NULL COMMENT 'COVERAGE/ASSESSMENT_BASIS', target_id varchar(36) NOT NULL,
  action_type_code varchar(30) NOT NULL COMMENT 'CREATE/UPDATE/CLOSE/CORRECT', before_data json NULL, after_data json NULL, reason varchar(1000) NOT NULL,
  request_key varchar(100) NOT NULL, processed_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP, processed_by varchar(100) NOT NULL,
  PRIMARY KEY(id), UNIQUE KEY uk_social_insurance_audit_request(request_key), KEY idx_social_insurance_audit_target(target_type_code,target_id,processed_at),
  CONSTRAINT chk_social_insurance_audit_target CHECK(target_type_code IN('COVERAGE','ASSESSMENT_BASIS')),
  CONSTRAINT chk_social_insurance_audit_action CHECK(action_type_code IN('CREATE','UPDATE','CLOSE','CORRECT'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='직원 사회보험 변경 감사원장';

INSERT INTO system_codes(id,sort_no,code_group,group_name,code,code_name,extra_data,note,is_active,created_by,updated_by) VALUES
('a8202204-0001-4000-8000-000000000001',91001,'SOCIAL_INSURANCE_COVERAGE_STATUS','사회보험 적용상태','ACQUIRED','취득',NULL,'실제 확인된 취득기간',1,'SYSTEM:MIGRATION','SYSTEM:MIGRATION'),
('a8202204-0002-4000-8000-000000000002',91002,'SOCIAL_INSURANCE_COVERAGE_STATUS','사회보험 적용상태','EXCLUDED','적용제외',NULL,'공식 근거가 필요한 제외기간',1,'SYSTEM:MIGRATION','SYSTEM:MIGRATION'),
('a8202204-0003-4000-8000-000000000003',91003,'SOCIAL_INSURANCE_BASIS_TYPE','사회보험 산정기준','STANDARD_MONTHLY_INCOME','기준소득월액',NULL,'국민연금 산정기준',1,'SYSTEM:MIGRATION','SYSTEM:MIGRATION'),
('a8202204-0004-4000-8000-000000000004',91004,'SOCIAL_INSURANCE_BASIS_TYPE','사회보험 산정기준','MONTHLY_REMUNERATION','보수월액',NULL,'건강보험 산정기준',1,'SYSTEM:MIGRATION','SYSTEM:MIGRATION'),
('a8202204-0005-4000-8000-000000000005',91005,'SOCIAL_INSURANCE_BASIS_TYPE','사회보험 산정기준','INSURABLE_REMUNERATION','보험료 산정대상 보수',NULL,'공식 고용보험 기준이 있을 때만 사용',1,'SYSTEM:MIGRATION','SYSTEM:MIGRATION'),
('a8202204-0006-4000-8000-000000000006',91006,'SOCIAL_INSURANCE_CONFIRMATION_STATUS','사회보험 확인상태','PROVISIONAL','잠정',NULL,'미리보기만 허용',1,'SYSTEM:MIGRATION','SYSTEM:MIGRATION'),
('a8202204-0007-4000-8000-000000000007',91007,'SOCIAL_INSURANCE_CONFIRMATION_STATUS','사회보험 확인상태','CONFIRMED','확정',NULL,'결재요청 계산 허용',1,'SYSTEM:MIGRATION','SYSTEM:MIGRATION')
ON DUPLICATE KEY UPDATE code_name=VALUES(code_name),note=VALUES(note),is_active=1,updated_at=NOW(),updated_by='SYSTEM:MIGRATION';

INSERT INTO system_page_registry(page_key,module_key,module_label,menu_key,menu_label,page_label,page_description,breadcrumb,default_route_key,default_route_url,source_description,is_active)
VALUES('web.institution.social_insurance','institution','대외기관업무','institution.social_insurance','4대보험업무','4대보험업무','직원 사회보험 취득·적용제외·산정기준 기간이력 관리','대외기관업무 > 4대보험업무','web.institution.social_insurance','/institution/social-insurance','social-insurance SSOT 관리페이지',1)
ON DUPLICATE KEY UPDATE page_label=VALUES(page_label),page_description=VALUES(page_description),breadcrumb=VALUES(breadcrumb),default_route_url=VALUES(default_route_url),source_description=VALUES(source_description),is_active=1,updated_at=NOW();

ALTER TABLE institution_regular_employment_income_calculation_bases
  DROP CONSTRAINT chk_regular_income_basis_type,
  ADD CONSTRAINT chk_regular_income_basis_type CHECK(basis_type_code IN('EMPLOYMENT_CONTRACT','ATTENDANCE_CLOSURE','LEAVE_USAGE','STATUTORY_STANDARD','INSURANCE_ELIGIBILITY','INSURANCE_COVERAGE','INSURANCE_ASSESSMENT_BASE'));
