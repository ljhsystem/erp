SET NAMES utf8mb4;

CREATE TABLE system_company (
  id VARCHAR(50) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL PRIMARY KEY,
  company_name_ko VARCHAR(200) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;
CREATE TABLE system_clients (
  id VARCHAR(36) NOT NULL PRIMARY KEY, client_name VARCHAR(100) NOT NULL,
  client_type VARCHAR(30) NOT NULL DEFAULT 'DAILY_WORKER', rrn LONGTEXT NULL,
  is_active TINYINT NOT NULL DEFAULT 1, deleted_at DATETIME NULL
) ENGINE=InnoDB;
CREATE TABLE system_projects (
  id VARCHAR(36) NOT NULL PRIMARY KEY, company_id VARCHAR(50) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL,
  sort_no INT NOT NULL DEFAULT 0, project_name VARCHAR(200) NOT NULL,
  start_date DATE NULL, completion_date DATE NULL, is_active TINYINT NOT NULL DEFAULT 1, deleted_at DATETIME NULL,
  CONSTRAINT fk_fixture_project_company FOREIGN KEY(company_id) REFERENCES system_company(id)
) ENGINE=InnoDB;
CREATE TABLE system_work_teams (
  id VARCHAR(36) NOT NULL PRIMARY KEY, company_id VARCHAR(50) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL,
  sort_no INT NOT NULL DEFAULT 0, team_name VARCHAR(200) NOT NULL,
  is_active TINYINT NOT NULL DEFAULT 1, deleted_at DATETIME NULL,
  CONSTRAINT fk_fixture_team_company FOREIGN KEY(company_id) REFERENCES system_company(id)
) ENGINE=InnoDB;
CREATE TABLE user_approval_requests (id VARCHAR(36) NOT NULL PRIMARY KEY) ENGINE=InnoDB;
CREATE TABLE user_approval_templates (
  id VARCHAR(36) NOT NULL PRIMARY KEY, sort_no INT NOT NULL, template_key VARCHAR(50) NOT NULL UNIQUE,
  template_name VARCHAR(100) NOT NULL, document_type VARCHAR(100) NOT NULL, description TEXT NULL,
  is_active TINYINT NOT NULL DEFAULT 1, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  created_by VARCHAR(100) NULL, updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_by VARCHAR(100) NULL
) ENGINE=InnoDB;
CREATE TABLE user_approval_template_steps (
  id VARCHAR(36) NOT NULL PRIMARY KEY, sort_no INT NOT NULL, template_id VARCHAR(36) NOT NULL,
  step_name VARCHAR(100) NOT NULL, step_type ENUM('SUBMIT','APPROVAL','FINAL_APPROVAL') NOT NULL,
  role_id VARCHAR(36) NULL, approver_id VARCHAR(36) NULL, is_active TINYINT NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, created_by VARCHAR(100) NULL,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_by VARCHAR(100) NULL,
  UNIQUE KEY uk_fixture_template_sequence(template_id,sort_no)
) ENGINE=InnoDB;
CREATE TABLE institution_employment_contracts (
  id VARCHAR(36) NOT NULL PRIMARY KEY,
  employment_category VARCHAR(30) NULL
) ENGINE=InnoDB;
CREATE TABLE institution_workplace_size_periods (
  id VARCHAR(36) NOT NULL PRIMARY KEY,
  company_id VARCHAR(50) NOT NULL,
  calculation_purpose_code VARCHAR(100) NOT NULL,
  business_size_code VARCHAR(100) NOT NULL,
  confirmation_status_code VARCHAR(30) NOT NULL,
  effective_from DATE NOT NULL,
  effective_to DATE NULL,
  revision_no INT NOT NULL DEFAULT 1,
  previous_period_id VARCHAR(36) NULL
) ENGINE=InnoDB;
CREATE TABLE ledger_transactions (id VARCHAR(36) NOT NULL PRIMARY KEY) ENGINE=InnoDB;
CREATE TABLE auth_roles (
  id VARCHAR(36) NOT NULL PRIMARY KEY, role_key VARCHAR(100) NOT NULL, is_active TINYINT NOT NULL DEFAULT 1
) ENGINE=InnoDB;
CREATE TABLE auth_permissions (
  id VARCHAR(36) NOT NULL PRIMARY KEY, sort_no INT NOT NULL DEFAULT 0, page VARCHAR(200) NULL,
  permission_source VARCHAR(30) NULL, category VARCHAR(100) NULL, permission_key VARCHAR(191) NOT NULL UNIQUE,
  permission_name VARCHAR(200) NOT NULL, description VARCHAR(1000) NULL, page_key VARCHAR(191) NULL,
  is_active TINYINT NOT NULL DEFAULT 1, created_at DATETIME NOT NULL, created_by VARCHAR(100) NOT NULL,
  updated_at DATETIME NULL, updated_by VARCHAR(100) NULL
) ENGINE=InnoDB;
CREATE TABLE auth_role_permissions (
  id VARCHAR(36) NOT NULL PRIMARY KEY, role_id VARCHAR(36) NOT NULL, permission_id VARCHAR(36) NOT NULL,
  created_at DATETIME NOT NULL, created_by VARCHAR(100) NOT NULL,
  UNIQUE KEY uq_fixture_role_permission(role_id,permission_id),
  CONSTRAINT fk_fixture_rp_role FOREIGN KEY(role_id) REFERENCES auth_roles(id),
  CONSTRAINT fk_fixture_rp_permission FOREIGN KEY(permission_id) REFERENCES auth_permissions(id)
) ENGINE=InnoDB;
CREATE TABLE system_user_settings (
  id VARCHAR(36) NOT NULL PRIMARY KEY, page_key VARCHAR(100) NULL, setting_type VARCHAR(30) NULL,
  settings_json LONGTEXT NOT NULL
) ENGINE=InnoDB;
CREATE TABLE system_file_upload_policies (
  id VARCHAR(36) NOT NULL PRIMARY KEY, policy_key VARCHAR(100) NOT NULL UNIQUE, policy_name VARCHAR(200) NOT NULL,
  bucket VARCHAR(100) NOT NULL, allowed_ext VARCHAR(500) NOT NULL, allowed_mime VARCHAR(1000) NOT NULL,
  max_size_mb INT NOT NULL, is_active TINYINT NOT NULL, description VARCHAR(1000) NULL,
  created_at DATETIME NOT NULL, created_by VARCHAR(100) NOT NULL, updated_at DATETIME NOT NULL, updated_by VARCHAR(100) NOT NULL
) ENGINE=InnoDB;
CREATE TABLE system_codes (
  id VARCHAR(36) NOT NULL PRIMARY KEY, sort_no INT NOT NULL DEFAULT 0, code_group VARCHAR(100) NOT NULL,
  group_name VARCHAR(200) NOT NULL, code VARCHAR(100) NOT NULL, code_name VARCHAR(200) NOT NULL,
  note VARCHAR(1000) NULL, is_active TINYINT NOT NULL DEFAULT 1, extra_data LONGTEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, created_by VARCHAR(100) NOT NULL,
  updated_at DATETIME NULL, updated_by VARCHAR(100) NULL,
  UNIQUE KEY uq_fixture_code(code_group,code)
) ENGINE=InnoDB;
CREATE TABLE system_statutory_standards (
  id CHAR(36) NOT NULL PRIMARY KEY, sort_no INT NOT NULL DEFAULT 0, standard_type_code VARCHAR(100) NOT NULL,
  effective_from DATE NOT NULL, effective_to DATE NULL, value_data LONGTEXT NOT NULL, note VARCHAR(1000) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, created_by VARCHAR(100) NOT NULL,
  updated_at DATETIME NULL, updated_by VARCHAR(100) NULL,
  KEY idx_fixture_standard(standard_type_code,effective_from,effective_to)
) ENGINE=InnoDB;
CREATE TABLE system_statutory_standard_sources (
  id CHAR(36) NOT NULL PRIMARY KEY, standard_id CHAR(36) NOT NULL, organization_name VARCHAR(200) NOT NULL,
  law_name VARCHAR(300) NULL, notice_no VARCHAR(200) NULL, source_name VARCHAR(500) NOT NULL,
  source_url VARCHAR(1000) NULL, published_at DATE NULL, file_path VARCHAR(1000) NULL,
  file_name VARCHAR(500) NULL, file_size BIGINT NULL, mime_type VARCHAR(200) NULL, note VARCHAR(1000) NULL,
  sort_no INT NOT NULL DEFAULT 0, created_at DATETIME NOT NULL, created_by VARCHAR(100) NOT NULL,
  updated_at DATETIME NULL, updated_by VARCHAR(100) NULL,
  CONSTRAINT fk_fixture_source_standard FOREIGN KEY(standard_id) REFERENCES system_statutory_standards(id)
) ENGINE=InnoDB;

INSERT INTO system_company(id,company_name_ko) VALUES ('fixture-company','격리 Fixture 회사');
INSERT INTO system_projects(id,company_id,sort_no,project_name,start_date,completion_date) VALUES
 ('fixture-project-a','fixture-company',1,'비식별 현장 A','2013-01-01',NULL),
 ('fixture-project-b','fixture-company',2,'비식별 현장 B','2013-01-01',NULL);
INSERT INTO system_work_teams(id,company_id,sort_no,team_name) VALUES
 ('fixture-team-a','fixture-company',1,'비식별 작업팀 A'),('fixture-team-b','fixture-company',2,'비식별 작업팀 B');
INSERT INTO system_clients(id,client_name) VALUES ('fixture-worker','비식별 일용근로자');
INSERT INTO auth_roles VALUES ('fixture-role-super','super_admin',1);
INSERT INTO user_approval_templates(id,sort_no,template_key,template_name,document_type,description,is_active,created_by,updated_by)
VALUES ('fixture-daily-approval-template',1,'daily_employment_income_fixture','일용근로소득 결재','DAILY_EMPLOYMENT_INCOME','격리 계산경로 결재 준비상태 검증',1,'SYSTEM:FIXTURE','SYSTEM:FIXTURE');
INSERT INTO user_approval_template_steps(id,sort_no,template_id,step_name,step_type,role_id,approver_id,is_active,created_by,updated_by)
VALUES ('fixture-daily-approval-submit',1,'fixture-daily-approval-template','기안','SUBMIT',NULL,NULL,1,'SYSTEM:FIXTURE','SYSTEM:FIXTURE'),
       ('fixture-daily-approval-final',2,'fixture-daily-approval-template','최종승인','FINAL_APPROVAL','fixture-role-super',NULL,1,'SYSTEM:FIXTURE','SYSTEM:FIXTURE');
INSERT INTO auth_permissions VALUES
 ('fixture-permission-page',1,'일용근로소득','ROUTE','대외기관업무','web.institution.income_data.daily_employment','일용근로소득','Fixture',NULL,1,NOW(),'SYSTEM:FIXTURE',NULL,NULL),
 ('fixture-permission-save',2,'일용근로소득','ROUTE','대외기관업무','api.institution.income_data.daily_employment.save','일용근로소득 저장','Fixture',NULL,1,NOW(),'SYSTEM:FIXTURE',NULL,NULL);
INSERT INTO auth_role_permissions VALUES
 ('fixture-rp-page','fixture-role-super','fixture-permission-page',NOW(),'SYSTEM:FIXTURE'),
 ('fixture-rp-save','fixture-role-super','fixture-permission-save',NOW(),'SYSTEM:FIXTURE');
INSERT INTO system_codes(id,sort_no,code_group,group_name,code,code_name,note,is_active,extra_data,created_by) VALUES
 ('fixture-type-np',1,'STATUTORY_STANDARD_TYPE','법정기준 종류','NATIONAL_PENSION','국민연금',NULL,1,'{}','SYSTEM:FIXTURE'),
 ('fixture-type-hi',2,'STATUTORY_STANDARD_TYPE','법정기준 종류','HEALTH_INSURANCE','건강보험',NULL,1,'{}','SYSTEM:FIXTURE'),
 ('fixture-type-ltc',3,'STATUTORY_STANDARD_TYPE','법정기준 종류','LONG_TERM_CARE','장기요양보험',NULL,1,'{}','SYSTEM:FIXTURE'),
 ('fixture-type-ei',4,'STATUTORY_STANDARD_TYPE','법정기준 종류','EMPLOYMENT_INSURANCE','고용보험',NULL,1,'{}','SYSTEM:FIXTURE'),
 ('fixture-type-ia',5,'STATUTORY_STANDARD_TYPE','법정기준 종류','INDUSTRIAL_ACCIDENT','산재보험',NULL,1,'{}','SYSTEM:FIXTURE'),
 ('fixture-bu-hq',1,'BUSINESS_UNIT','사업구분','HQ','본사',NULL,1,'{}','SYSTEM:FIXTURE'),
 ('fixture-bu-con',2,'BUSINESS_UNIT','사업구분','CONSTRUCTION','전문건설업',NULL,1,'{}','SYSTEM:FIXTURE'),
 ('fixture-bu-ecom',3,'BUSINESS_UNIT','사업구분','ECOMMERCE','통신판매업',NULL,1,'{}','SYSTEM:FIXTURE'),
 ('fixture-work-general',1,'WORK_TYPE','공종','GENERAL','일반',NULL,1,'{}','SYSTEM:FIXTURE');
