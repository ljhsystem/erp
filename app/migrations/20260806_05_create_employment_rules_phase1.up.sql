CREATE TABLE institution_employment_rules (
 id char(36) NOT NULL, company_id varchar(50) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL, rule_code varchar(50) NOT NULL, rule_type_code varchar(50) NOT NULL,
 title varchar(200) NOT NULL, description varchar(1000) DEFAULT NULL, owner_department_id varchar(36) DEFAULT NULL,
 current_revision_id char(36) DEFAULT NULL, is_active tinyint(1) NOT NULL DEFAULT 1, request_key varchar(191) NOT NULL,
 created_at datetime NOT NULL, created_by varchar(100) NOT NULL, updated_at datetime NOT NULL, updated_by varchar(100) NOT NULL,
 deleted_at datetime DEFAULT NULL, deleted_by varchar(100) DEFAULT NULL,
 PRIMARY KEY(id), UNIQUE KEY uk_employment_rule_code(company_id,rule_code), UNIQUE KEY uk_employment_rule_request(request_key),
 KEY idx_employment_rule_type_active(rule_type_code,is_active),
 CONSTRAINT fk_employment_rule_company FOREIGN KEY(company_id) REFERENCES system_company(id),
 CONSTRAINT fk_employment_rule_department FOREIGN KEY(owner_department_id) REFERENCES user_departments(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Employment rules document SSOT';

CREATE TABLE institution_employment_rules_revisions (
 id char(36) NOT NULL, rule_id char(36) NOT NULL, revision_no int unsigned NOT NULL, revision_title varchar(200) NOT NULL,
 revision_reason varchar(500) NOT NULL, content_text longtext DEFAULT NULL, effective_from date NOT NULL, effective_to date DEFAULT NULL,
 status_code varchar(30) NOT NULL DEFAULT 'DRAFT', current_approval_request_id varchar(36) DEFAULT NULL,
 approved_at datetime DEFAULT NULL, approved_by varchar(100) DEFAULT NULL, published_at datetime DEFAULT NULL, published_by varchar(100) DEFAULT NULL,
 document_file_path varchar(500) DEFAULT NULL, document_file_name varchar(255) DEFAULT NULL, request_key varchar(191) NOT NULL,
 created_at datetime NOT NULL, created_by varchar(100) NOT NULL, updated_at datetime NOT NULL, updated_by varchar(100) NOT NULL,
 deleted_at datetime DEFAULT NULL, deleted_by varchar(100) DEFAULT NULL,
 PRIMARY KEY(id), UNIQUE KEY uk_employment_rule_revision(rule_id,revision_no), UNIQUE KEY uk_employment_rule_revision_request(request_key),
 KEY idx_employment_rule_revision_effective(rule_id,status_code,effective_from,effective_to),
 CONSTRAINT fk_employment_rule_revision_rule FOREIGN KEY(rule_id) REFERENCES institution_employment_rules(id),
 CONSTRAINT fk_employment_rule_revision_approval FOREIGN KEY(current_approval_request_id) REFERENCES user_approval_requests(id),
 CONSTRAINT chk_employment_rule_revision_dates CHECK(effective_to IS NULL OR effective_to>=effective_from)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Immutable approved revisions';

ALTER TABLE institution_employment_rules ADD CONSTRAINT fk_employment_rule_current_revision FOREIGN KEY(current_revision_id) REFERENCES institution_employment_rules_revisions(id);

CREATE TABLE institution_employment_rules_items (
 id char(36) NOT NULL, revision_id char(36) NOT NULL, policy_code varchar(50) NOT NULL, value_type_code varchar(30) NOT NULL,
 value_text text DEFAULT NULL, value_number decimal(18,4) DEFAULT NULL, value_boolean tinyint(1) DEFAULT NULL,
 value_date date DEFAULT NULL, value_time time DEFAULT NULL, value_minutes int DEFAULT NULL,
 value_json longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK(value_json IS NULL OR json_valid(value_json)),
 unit_code varchar(30) DEFAULT NULL, operator_code varchar(30) NOT NULL DEFAULT 'EXACT', note varchar(1000) DEFAULT NULL,
 sort_no int unsigned NOT NULL, created_at datetime NOT NULL, created_by varchar(100) NOT NULL, updated_at datetime NOT NULL, updated_by varchar(100) NOT NULL,
 PRIMARY KEY(id), UNIQUE KEY uk_employment_rule_item(revision_id,policy_code), KEY idx_employment_rule_item_policy(policy_code),
 CONSTRAINT fk_employment_rule_item_revision FOREIGN KEY(revision_id) REFERENCES institution_employment_rules_revisions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Structured policy values by revision';

CREATE TABLE institution_employment_rules_scopes (
 id char(36) NOT NULL, revision_id char(36) NOT NULL, scope_type_code varchar(30) NOT NULL,
 department_id varchar(36) DEFAULT NULL, position_id varchar(36) DEFAULT NULL, job_id varchar(36) DEFAULT NULL,
 employment_category_code varchar(30) DEFAULT NULL, created_at datetime NOT NULL, created_by varchar(100) NOT NULL,
 updated_at datetime NOT NULL, updated_by varchar(100) NOT NULL,
 PRIMARY KEY(id), KEY idx_employment_rule_scope_revision(revision_id,scope_type_code),
 CONSTRAINT fk_employment_rule_scope_revision FOREIGN KEY(revision_id) REFERENCES institution_employment_rules_revisions(id) ON DELETE CASCADE,
 CONSTRAINT fk_employment_rule_scope_department FOREIGN KEY(department_id) REFERENCES user_departments(id),
 CONSTRAINT fk_employment_rule_scope_position FOREIGN KEY(position_id) REFERENCES user_positions(id),
 CONSTRAINT fk_employment_rule_scope_job FOREIGN KEY(job_id) REFERENCES institution_job_assignments_jobs(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Employment rule applicability scopes';

CREATE TABLE institution_employment_rules_audits (
 id char(36) NOT NULL, rule_id char(36) NOT NULL, revision_id char(36) DEFAULT NULL, item_id char(36) DEFAULT NULL,
 action_type_code varchar(30) NOT NULL, source_type_code varchar(30) NOT NULL, reason varchar(500) NOT NULL, request_key varchar(191) NOT NULL,
 before_data longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK(before_data IS NULL OR json_valid(before_data)),
 after_data longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK(after_data IS NULL OR json_valid(after_data)),
 processed_by varchar(100) NOT NULL, processed_at datetime NOT NULL,
 PRIMARY KEY(id), UNIQUE KEY uk_employment_rule_audit_request(request_key), KEY idx_employment_rule_audit(rule_id,processed_at),
 CONSTRAINT fk_employment_rule_audit_rule FOREIGN KEY(rule_id) REFERENCES institution_employment_rules(id),
 CONSTRAINT fk_employment_rule_audit_revision FOREIGN KEY(revision_id) REFERENCES institution_employment_rules_revisions(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Employment rule audit trail';

INSERT INTO system_codes(id,sort_no,code_group,group_name,code,code_name,is_active,created_at,created_by,updated_at,updated_by)
SELECT UUID(),COALESCE((SELECT MAX(sort_no) FROM system_codes),0)+v.n,v.g,v.gn,v.c,v.cn,1,NOW(),'SYSTEM:MIGRATION',NOW(),'SYSTEM:MIGRATION'
FROM (
 SELECT 1 n,'EMPLOYMENT_RULE_TYPE'g,'취업규칙 종류'gn,'COMPANY'c,'회사 기본규정'cn UNION ALL SELECT 2,'EMPLOYMENT_RULE_TYPE','취업규칙 종류','WORK','근무규정' UNION ALL SELECT 3,'EMPLOYMENT_RULE_TYPE','취업규칙 종류','LEAVE','휴가규정' UNION ALL SELECT 4,'EMPLOYMENT_RULE_TYPE','취업규칙 종류','PAYROLL','급여규정' UNION ALL SELECT 5,'EMPLOYMENT_RULE_TYPE','취업규칙 종류','EDUCATION','교육규정' UNION ALL SELECT 6,'EMPLOYMENT_RULE_TYPE','취업규칙 종류','QUALIFICATION','자격규정' UNION ALL SELECT 7,'EMPLOYMENT_RULE_TYPE','취업규칙 종류','PROMOTION','승진규정' UNION ALL SELECT 8,'EMPLOYMENT_RULE_TYPE','취업규칙 종류','REWARD','포상규정' UNION ALL SELECT 9,'EMPLOYMENT_RULE_TYPE','취업규칙 종류','DISCIPLINE','징계규정' UNION ALL SELECT 10,'EMPLOYMENT_RULE_TYPE','취업규칙 종류','WELFARE','복리후생' UNION ALL SELECT 11,'EMPLOYMENT_RULE_TYPE','취업규칙 종류','OTHER','기타'
 UNION ALL SELECT 12,'EMPLOYMENT_RULE_STATUS','취업규칙 상태','DRAFT','초안' UNION ALL SELECT 13,'EMPLOYMENT_RULE_STATUS','취업규칙 상태','APPROVAL_PENDING','결재중' UNION ALL SELECT 14,'EMPLOYMENT_RULE_STATUS','취업규칙 상태','APPROVED','승인' UNION ALL SELECT 15,'EMPLOYMENT_RULE_STATUS','취업규칙 상태','EFFECTIVE','시행' UNION ALL SELECT 16,'EMPLOYMENT_RULE_STATUS','취업규칙 상태','EXPIRED','종료' UNION ALL SELECT 17,'EMPLOYMENT_RULE_STATUS','취업규칙 상태','WITHDRAWN','회수'
 UNION ALL SELECT 18,'EMPLOYMENT_RULE_VALUE_TYPE','정책값 형식','TEXT','문자' UNION ALL SELECT 19,'EMPLOYMENT_RULE_VALUE_TYPE','정책값 형식','NUMBER','숫자' UNION ALL SELECT 20,'EMPLOYMENT_RULE_VALUE_TYPE','정책값 형식','BOOLEAN','참거짓' UNION ALL SELECT 21,'EMPLOYMENT_RULE_VALUE_TYPE','정책값 형식','DATE','날짜' UNION ALL SELECT 22,'EMPLOYMENT_RULE_VALUE_TYPE','정책값 형식','TIME','시간' UNION ALL SELECT 23,'EMPLOYMENT_RULE_VALUE_TYPE','정책값 형식','MINUTES','분' UNION ALL SELECT 24,'EMPLOYMENT_RULE_VALUE_TYPE','정책값 형식','PERCENT','비율' UNION ALL SELECT 25,'EMPLOYMENT_RULE_VALUE_TYPE','정책값 형식','JSON','복합조건'
 UNION ALL SELECT 26,'EMPLOYMENT_RULE_SCOPE_TYPE','정책 적용범위','ALL','회사 전체' UNION ALL SELECT 27,'EMPLOYMENT_RULE_SCOPE_TYPE','정책 적용범위','DEPARTMENT','부서' UNION ALL SELECT 28,'EMPLOYMENT_RULE_SCOPE_TYPE','정책 적용범위','POSITION','직위' UNION ALL SELECT 29,'EMPLOYMENT_RULE_SCOPE_TYPE','정책 적용범위','JOB','직무' UNION ALL SELECT 30,'EMPLOYMENT_RULE_SCOPE_TYPE','정책 적용범위','EMPLOYMENT_CATEGORY','고용구분'
 UNION ALL SELECT 31,'EMPLOYMENT_RULE_OPERATOR','정책 연산자','EXACT','정확값' UNION ALL SELECT 32,'EMPLOYMENT_RULE_OPERATOR','정책 연산자','MINIMUM','최소' UNION ALL SELECT 33,'EMPLOYMENT_RULE_OPERATOR','정책 연산자','MAXIMUM','최대' UNION ALL SELECT 34,'EMPLOYMENT_RULE_OPERATOR','정책 연산자','ALLOWED','허용' UNION ALL SELECT 35,'EMPLOYMENT_RULE_OPERATOR','정책 연산자','REQUIRED','필수'
 UNION ALL SELECT 36,'EMPLOYMENT_RULE_UNIT','정책 단위','MINUTE','분' UNION ALL SELECT 37,'EMPLOYMENT_RULE_UNIT','정책 단위','HOUR','시간' UNION ALL SELECT 38,'EMPLOYMENT_RULE_UNIT','정책 단위','DAY','일' UNION ALL SELECT 39,'EMPLOYMENT_RULE_UNIT','정책 단위','PERCENT','퍼센트' UNION ALL SELECT 40,'EMPLOYMENT_RULE_UNIT','정책 단위','COUNT','회'
)v WHERE NOT EXISTS(SELECT 1 FROM system_codes c WHERE c.code_group=v.g AND c.code=v.c);

INSERT INTO system_codes(id,sort_no,code_group,group_name,code,code_name,is_active,created_at,created_by,updated_at,updated_by)
SELECT UUID(),COALESCE((SELECT MAX(sort_no) FROM system_codes),0)+v.n,'EMPLOYMENT_RULE_POLICY','취업규칙 정책',v.c,v.cn,1,NOW(),'SYSTEM:MIGRATION',NOW(),'SYSTEM:MIGRATION'
FROM (SELECT 1 n,'WORK_START_TIME'c,'기본 출근시간'cn UNION ALL SELECT 2,'WORK_END_TIME','기본 퇴근시간' UNION ALL SELECT 3,'BREAK_MINUTES','휴게시간' UNION ALL SELECT 4,'DAILY_SCHEDULED_MINUTES','일 소정근로시간' UNION ALL SELECT 5,'WEEKLY_SCHEDULED_MINUTES','주 소정근로시간' UNION ALL SELECT 6,'OVERTIME_STANDARD','연장근무 기준' UNION ALL SELECT 7,'NIGHT_WORK_STANDARD','야간근무 기준' UNION ALL SELECT 8,'HOLIDAY_WORK_STANDARD','휴일근무 기준' UNION ALL SELECT 9,'WEEKLY_HOLIDAY_STANDARD','주휴 기준' UNION ALL SELECT 10,'COMPANY_PAID_HOLIDAY','회사 유급휴일' UNION ALL SELECT 11,'LEAVE_ACCRUAL_STANDARD','휴가 발생 기준' UNION ALL SELECT 12,'HALF_DAY_RATIO','반차 기준' UNION ALL SELECT 13,'HOURLY_LEAVE_MINUTES','시간차 기준' UNION ALL SELECT 14,'PAYMENT_DAY','급여 지급일' UNION ALL SELECT 15,'PAYROLL_CUTOFF_DAY','급여 마감일' UNION ALL SELECT 16,'PAYROLL_CALCULATION_STANDARD','급여 계산 기준' UNION ALL SELECT 17,'MANDATORY_EDUCATION','교육 의무' UNION ALL SELECT 18,'QUALIFICATION_MAINTENANCE','자격 유지 기준' UNION ALL SELECT 19,'PROMOTION_STANDARD','승진 기준' UNION ALL SELECT 20,'DISCIPLINE_STANDARD','징계 기준' UNION ALL SELECT 21,'REWARD_STANDARD','포상 기준' UNION ALL SELECT 22,'WELFARE_STANDARD','복리후생' UNION ALL SELECT 23,'RETIREMENT_STANDARD','퇴직 기준' UNION ALL SELECT 24,'OTHER_POLICY','기타 회사 정책')v
WHERE NOT EXISTS(SELECT 1 FROM system_codes c WHERE c.code_group='EMPLOYMENT_RULE_POLICY' AND c.code=v.c);

INSERT INTO auth_permissions(id,sort_no,page,permission_source,category,permission_key,permission_name,description,page_key,is_active,created_at,created_by,updated_at,updated_by)
SELECT UUID(),COALESCE((SELECT MAX(sort_no) FROM auth_permissions),0)+v.n,'취업규칙·인사규정','ROUTE','대외기관업무',v.k,v.pn,v.pn,'web.institution.human_resources.employment_rules',1,NOW(),'SYSTEM:MIGRATION',NOW(),'SYSTEM:MIGRATION'
FROM (SELECT 1 n,'api.institution.human_resources.employment_rules.view'k,'조회'pn UNION ALL SELECT 2,'api.institution.human_resources.employment_rules.detail','상세조회' UNION ALL SELECT 3,'api.institution.human_resources.employment_rules.save','저장' UNION ALL SELECT 4,'api.institution.human_resources.employment_rules.delete','삭제' UNION ALL SELECT 5,'api.institution.human_resources.employment_rules.revise','개정' UNION ALL SELECT 6,'api.institution.human_resources.employment_rules.submit','결재요청' UNION ALL SELECT 7,'api.institution.human_resources.employment_rules.withdraw','회수' UNION ALL SELECT 8,'api.institution.human_resources.employment_rules.activate','시행' UNION ALL SELECT 9,'api.institution.human_resources.employment_rules.history','개정이력' UNION ALL SELECT 10,'api.institution.human_resources.employment_rules.options','선택옵션' UNION ALL SELECT 11,'api.institution.human_resources.employment_rules.excel','Excel 다운로드')v
WHERE NOT EXISTS(SELECT 1 FROM auth_permissions p WHERE p.permission_key=v.k);

INSERT INTO auth_role_permissions(id,role_id,permission_id,created_at,created_by)
SELECT UUID(),r.id,p.id,NOW(),'SYSTEM:MIGRATION' FROM auth_roles r JOIN auth_permissions p ON p.permission_key='web.institution.human_resources.employment_rules' OR p.permission_key LIKE 'api.institution.human_resources.employment_rules.%' LEFT JOIN auth_role_permissions rp ON rp.role_id=r.id AND rp.permission_id=p.id WHERE r.role_key='super_admin' AND rp.id IS NULL;

INSERT INTO user_approval_templates(id,sort_no,template_key,template_name,document_type,description,is_active,created_at,created_by,updated_at,updated_by)
SELECT '82eb7481-d354-4bc7-ada7-cd73932e1301',COALESCE(MAX(sort_no),0)+1,'EMPLOYMENT_RULE_REVISION_DEFAULT','취업규칙 개정 기본 결재','EMPLOYMENT_RULE_REVISION','기안 후 최고관리자 최종 승인',1,NOW(),'SYSTEM:MIGRATION',NOW(),'SYSTEM:MIGRATION' FROM user_approval_templates WHERE NOT EXISTS(SELECT 1 FROM user_approval_templates WHERE document_type='EMPLOYMENT_RULE_REVISION' AND is_active=1);
INSERT INTO user_approval_template_steps(id,sort_no,template_id,step_name,step_type,role_id,approver_id,is_active,created_at,created_by,updated_at,updated_by)
SELECT UUID(),1,t.id,'기안','SUBMIT',NULL,NULL,1,NOW(),'SYSTEM:MIGRATION',NOW(),'SYSTEM:MIGRATION' FROM user_approval_templates t WHERE t.template_key='EMPLOYMENT_RULE_REVISION_DEFAULT' AND NOT EXISTS(SELECT 1 FROM user_approval_template_steps s WHERE s.template_id=t.id AND s.sort_no=1);
INSERT INTO user_approval_template_steps(id,sort_no,template_id,step_name,step_type,role_id,approver_id,is_active,created_at,created_by,updated_at,updated_by)
SELECT UUID(),2,t.id,'최종승인','FINAL_APPROVAL',r.id,NULL,1,NOW(),'SYSTEM:MIGRATION',NOW(),'SYSTEM:MIGRATION' FROM user_approval_templates t JOIN auth_roles r ON r.role_key='super_admin' WHERE t.template_key='EMPLOYMENT_RULE_REVISION_DEFAULT' AND NOT EXISTS(SELECT 1 FROM user_approval_template_steps s WHERE s.template_id=t.id AND s.sort_no=2);

UPDATE system_page_registry SET page_description='회사 취업규칙·인사규정 정책 및 개정 관리',source_description='취업규칙 정책 SSOT',updated_at=NOW() WHERE page_key='web.institution.human_resources.employment_rules';
