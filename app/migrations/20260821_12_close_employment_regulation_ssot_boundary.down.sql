ALTER TABLE institution_employment_rules_revisions
    CHANGE COLUMN title revision_title varchar(200) NOT NULL COMMENT '개정본 제목',
    CHANGE COLUMN change_reason revision_reason varchar(500) NOT NULL COMMENT '개정 사유',
    DROP COLUMN change_summary,
    DROP COLUMN revision_date,
    CHANGE COLUMN approval_request_id current_approval_request_id varchar(36) DEFAULT NULL COMMENT '현재 결재요청 식별자';

ALTER TABLE institution_employment_rules_audits
    ADD COLUMN item_id char(36) DEFAULT NULL COMMENT '규정항목 식별자' AFTER revision_id;

ALTER TABLE institution_employment_rules
    CHANGE COLUMN regulation_code rule_code varchar(50) NOT NULL COMMENT '취업규칙 코드',
    CHANGE COLUMN regulation_type_code rule_type_code varchar(50) NOT NULL COMMENT '취업규칙 유형 코드',
    ADD COLUMN current_revision_id char(36) DEFAULT NULL COMMENT '현재 개정본 식별자' AFTER owner_department_id,
    DROP INDEX idx_employment_regulation_type_active,
    ADD KEY idx_employment_rule_type_active(rule_type_code,is_active),
    ADD CONSTRAINT fk_employment_rule_current_revision FOREIGN KEY(current_revision_id) REFERENCES institution_employment_rules_revisions(id);

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

DELETE FROM system_codes WHERE code_group='EMPLOYMENT_RULE_TYPE';
INSERT INTO system_codes(id,sort_no,code_group,group_name,code,code_name,is_active,created_at,created_by,updated_at,updated_by)
SELECT UUID(),COALESCE((SELECT MAX(sort_no) FROM system_codes),0)+v.n,'EMPLOYMENT_RULE_TYPE','취업규칙 종류',v.code,v.name,1,NOW(),'SYSTEM:MIGRATION',NOW(),'SYSTEM:MIGRATION'
FROM (
 SELECT 1 n,'COMPANY' code,'회사 기본규정' name UNION ALL SELECT 2,'WORK','근무규정' UNION ALL
 SELECT 3,'LEAVE','휴가규정' UNION ALL SELECT 4,'PAYROLL','급여규정' UNION ALL
 SELECT 5,'EDUCATION','교육규정' UNION ALL SELECT 6,'QUALIFICATION','자격규정' UNION ALL
 SELECT 7,'PROMOTION','승진규정' UNION ALL SELECT 8,'REWARD','포상규정' UNION ALL
 SELECT 9,'DISCIPLINE','징계규정' UNION ALL SELECT 10,'WELFARE','복리후생' UNION ALL SELECT 11,'OTHER','기타'
) v;

DELETE FROM system_codes WHERE code_group='EMPLOYMENT_RULE_STATUS' AND code IN ('SCHEDULED','RETIRED');
INSERT INTO system_codes(id,sort_no,code_group,group_name,code,code_name,is_active,created_at,created_by,updated_at,updated_by)
SELECT UUID(),COALESCE(MAX(sort_no),0)+1,'EMPLOYMENT_RULE_STATUS','취업규칙 상태','EXPIRED','종료',1,NOW(),'SYSTEM:MIGRATION',NOW(),'SYSTEM:MIGRATION'
FROM system_codes WHERE NOT EXISTS(SELECT 1 FROM system_codes WHERE code_group='EMPLOYMENT_RULE_STATUS' AND code='EXPIRED');

DELETE FROM system_page_registry WHERE page_key='web.institution.human_resources.employment_rules';
UPDATE auth_permissions SET page_key=NULL,updated_at=NOW(),updated_by='SYSTEM:MIGRATION'
 WHERE permission_key='web.institution.human_resources.employment_rules'
    OR permission_key LIKE 'api.institution.human_resources.employment_rules.%';

INSERT INTO auth_permissions(id,sort_no,page,permission_source,category,permission_key,permission_name,description,page_key,is_active,created_at,created_by,updated_at,updated_by)
SELECT UUID(),COALESCE(MAX(sort_no),0)+1,'취업규칙·인사규정','ROUTE','대외기관업무','api.institution.human_resources.employment_rules.excel','Excel 다운로드','Excel 다운로드',NULL,1,NOW(),'SYSTEM:MIGRATION',NOW(),'SYSTEM:MIGRATION'
FROM auth_permissions WHERE NOT EXISTS(SELECT 1 FROM auth_permissions WHERE permission_key='api.institution.human_resources.employment_rules.excel');
