DELIMITER $$
CREATE PROCEDURE migrate_employment_regulation_boundary()
BEGIN
    IF (SELECT COUNT(*) FROM institution_employment_rules) <> 0
       OR (SELECT COUNT(*) FROM institution_employment_rules_revisions) <> 0
       OR (SELECT COUNT(*) FROM institution_employment_rules_items) <> 0
       OR (SELECT COUNT(*) FROM institution_employment_rules_scopes) <> 0
       OR (SELECT COUNT(*) FROM institution_employment_rules_audits) <> 0 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = '규정 업무데이터가 존재하여 SSOT 경계 Migration을 중단합니다.';
    END IF;

    ALTER TABLE institution_employment_rules
        DROP FOREIGN KEY fk_employment_rule_current_revision,
        DROP COLUMN current_revision_id,
        CHANGE COLUMN rule_code regulation_code varchar(50) NOT NULL COMMENT '규정 코드',
        CHANGE COLUMN rule_type_code regulation_type_code varchar(50) NOT NULL COMMENT '규정 종류 코드';

    ALTER TABLE institution_employment_rules
        DROP INDEX idx_employment_rule_type_active,
        ADD KEY idx_employment_regulation_type_active(regulation_type_code,is_active);

    ALTER TABLE institution_employment_rules_revisions
        CHANGE COLUMN revision_title title varchar(200) NOT NULL COMMENT '개정본 제목',
        CHANGE COLUMN revision_reason change_reason varchar(500) NOT NULL COMMENT '개정 사유',
        ADD COLUMN change_summary varchar(1000) DEFAULT NULL COMMENT '개정 요약' AFTER change_reason,
        ADD COLUMN revision_date date NOT NULL COMMENT '제정 또는 개정일' AFTER change_summary,
        CHANGE COLUMN current_approval_request_id approval_request_id varchar(36) DEFAULT NULL COMMENT '결재요청 식별자';

    DROP TABLE institution_employment_rules_scopes;
    DROP TABLE institution_employment_rules_items;
    ALTER TABLE institution_employment_rules_audits DROP COLUMN item_id;

    DELETE FROM system_codes
     WHERE code_group IN (
        'EMPLOYMENT_RULE_POLICY',
        'EMPLOYMENT_RULE_VALUE_TYPE',
        'EMPLOYMENT_RULE_SCOPE_TYPE',
        'EMPLOYMENT_RULE_OPERATOR',
        'EMPLOYMENT_RULE_UNIT'
     );

    DELETE FROM system_codes WHERE code_group='EMPLOYMENT_RULE_TYPE';
    INSERT INTO system_codes(id,sort_no,code_group,group_name,code,code_name,is_active,created_at,created_by,updated_at,updated_by)
    SELECT UUID(),COALESCE(MAX(sort_no),0)+1,'EMPLOYMENT_RULE_TYPE','규정 종류','EMPLOYMENT_RULE','취업규칙',1,NOW(),'SYSTEM:MIGRATION',NOW(),'SYSTEM:MIGRATION'
      FROM system_codes;
    INSERT INTO system_codes(id,sort_no,code_group,group_name,code,code_name,is_active,created_at,created_by,updated_at,updated_by)
    SELECT UUID(),COALESCE(MAX(sort_no),0)+1,'EMPLOYMENT_RULE_TYPE','규정 종류','PERSONNEL_REGULATION','인사규정',1,NOW(),'SYSTEM:MIGRATION',NOW(),'SYSTEM:MIGRATION'
      FROM system_codes;

    DELETE FROM system_codes
     WHERE code_group='EMPLOYMENT_RULE_STATUS'
       AND code IN ('EXPIRED');
    INSERT INTO system_codes(id,sort_no,code_group,group_name,code,code_name,is_active,created_at,created_by,updated_at,updated_by)
    SELECT UUID(),COALESCE(MAX(sort_no),0)+1,'EMPLOYMENT_RULE_STATUS','규정 상태','SCHEDULED','시행예정',1,NOW(),'SYSTEM:MIGRATION',NOW(),'SYSTEM:MIGRATION'
      FROM system_codes
     WHERE NOT EXISTS(SELECT 1 FROM system_codes WHERE code_group='EMPLOYMENT_RULE_STATUS' AND code='SCHEDULED');
    INSERT INTO system_codes(id,sort_no,code_group,group_name,code,code_name,is_active,created_at,created_by,updated_at,updated_by)
    SELECT UUID(),COALESCE(MAX(sort_no),0)+1,'EMPLOYMENT_RULE_STATUS','규정 상태','RETIRED','폐지',1,NOW(),'SYSTEM:MIGRATION',NOW(),'SYSTEM:MIGRATION'
      FROM system_codes
     WHERE NOT EXISTS(SELECT 1 FROM system_codes WHERE code_group='EMPLOYMENT_RULE_STATUS' AND code='RETIRED');

    INSERT INTO system_page_registry(
        page_key,module_key,module_label,menu_key,menu_label,page_label,page_description,
        breadcrumb,default_route_key,default_route_url,source_description,is_active,created_at,updated_at
    ) VALUES (
        'web.institution.human_resources.employment_rules','institution','대외기관업무',
        'institution.human_resources','인사·노무관리','취업규칙·인사규정',
        '회사 공식 취업규칙과 인사규정의 개정·시행 이력 관리',
        '대외기관업무 > 인사·노무관리 > 취업규칙·인사규정',
        'web.institution.human_resources.employment_rules','/institution/human-resources/employment-rules',
        '공식 규정문서 Header·Revision SSOT',1,NOW(),NOW()
    ) ON DUPLICATE KEY UPDATE
        module_key=VALUES(module_key),module_label=VALUES(module_label),menu_key=VALUES(menu_key),
        menu_label=VALUES(menu_label),page_label=VALUES(page_label),page_description=VALUES(page_description),
        breadcrumb=VALUES(breadcrumb),default_route_key=VALUES(default_route_key),
        default_route_url=VALUES(default_route_url),source_description=VALUES(source_description),
        is_active=1,updated_at=NOW();

    DELETE rp FROM auth_role_permissions rp
      JOIN auth_permissions p ON p.id=rp.permission_id
     WHERE p.permission_key='api.institution.human_resources.employment_rules.excel';
    DELETE FROM auth_permissions WHERE permission_key='api.institution.human_resources.employment_rules.excel';

    UPDATE auth_permissions
       SET page_key='web.institution.human_resources.employment_rules',updated_at=NOW(),updated_by='SYSTEM:MIGRATION'
     WHERE permission_key='web.institution.human_resources.employment_rules'
        OR permission_key LIKE 'api.institution.human_resources.employment_rules.%';
END$$
DELIMITER ;

CALL migrate_employment_regulation_boundary();
DROP PROCEDURE migrate_employment_regulation_boundary;
