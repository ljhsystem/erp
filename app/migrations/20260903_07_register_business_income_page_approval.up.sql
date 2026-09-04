INSERT INTO system_page_registry (page_key,module_key,module_label,menu_key,menu_label,page_label,page_description,breadcrumb,default_route_key,default_route_url,source_description,is_active,created_at,updated_at)
VALUES ('web.institution.income_data.business_income','institution','대외기관업무','institution.income_data','소득자료관리','사업소득','사업소득 작성·계산·결재·Evidence·지급 관리','대외기관업무 > 소득자료관리 > 사업소득','web.institution.income_data.business_income','/institution/income-data/business-income','사업소득 P1 SSOT',1,NOW(),NOW())
ON DUPLICATE KEY UPDATE page_label=VALUES(page_label),page_description=VALUES(page_description),breadcrumb=VALUES(breadcrumb),default_route_key=VALUES(default_route_key),default_route_url=VALUES(default_route_url),source_description=VALUES(source_description),is_active=1,updated_at=NOW();

INSERT INTO auth_permissions (id,sort_no,page,permission_source,category,permission_key,permission_name,description,page_key,is_active,created_at,created_by,updated_at,updated_by)
SELECT UUID(),(SELECT COALESCE(MAX(p.sort_no),0)+seed.sort_order FROM auth_permissions p),'사업소득','ROUTE','대외기관업무',seed.permission_key,seed.permission_name,CONCAT('사업소득 ',seed.permission_name),'web.institution.income_data.business_income',1,NOW(),'SYSTEM:MIGRATION',NOW(),'SYSTEM:MIGRATION'
FROM (
    SELECT 1 sort_order,'web.institution.income_data.business_income' permission_key,'화면조회' permission_name UNION ALL
    SELECT 2,'api.institution.income_data.business_income.list','목록조회' UNION ALL
    SELECT 3,'api.institution.income_data.business_income.detail','상세조회' UNION ALL
    SELECT 4,'api.institution.income_data.business_income.calculate','계산 Preview' UNION ALL
    SELECT 5,'api.institution.income_data.business_income.save','저장' UNION ALL
    SELECT 6,'api.institution.income_data.business_income.submit','결재요청' UNION ALL
    SELECT 7,'api.institution.income_data.business_income.delete','삭제' UNION ALL
    SELECT 8,'api.institution.income_data.business_income.trash','휴지통 조회' UNION ALL
    SELECT 9,'api.institution.income_data.business_income.restore','복구' UNION ALL
    SELECT 10,'api.institution.income_data.business_income.purge','영구삭제'
) seed WHERE NOT EXISTS(SELECT 1 FROM auth_permissions current_permission WHERE current_permission.permission_key=seed.permission_key);

INSERT INTO auth_role_permissions (id,role_id,permission_id,created_at,created_by)
SELECT UUID(),role_row.id,permission_row.id,NOW(),'SYSTEM:MIGRATION'
FROM auth_roles role_row JOIN auth_permissions permission_row ON permission_row.page_key='web.institution.income_data.business_income'
LEFT JOIN auth_role_permissions existing ON existing.role_id=role_row.id AND existing.permission_id=permission_row.id
WHERE role_row.role_key='super_admin' AND existing.id IS NULL;

INSERT INTO user_approval_templates (id,sort_no,template_key,template_name,document_type,description,is_active,created_at,created_by,updated_at,updated_by)
SELECT UUID(),COALESCE(MAX(sort_no),0)+1,'BUSINESS_INCOME_DEFAULT','사업소득 기본 결재','BUSINESS_INCOME','사업소득 지급 승인',1,NOW(),'SYSTEM:MIGRATION',NOW(),'SYSTEM:MIGRATION'
FROM user_approval_templates WHERE NOT EXISTS(SELECT 1 FROM user_approval_templates WHERE document_type='BUSINESS_INCOME' AND is_active=1);

-- 승인자는 임의로 생성하지 않는다. 기존 일용근로소득 결재선의 역할 기반 단계를 복제한다.
INSERT INTO user_approval_template_steps (id,sort_no,template_id,step_name,step_type,role_id,approver_id,is_active,created_at,created_by,updated_at,updated_by)
SELECT UUID(),source_step.sort_no,target.id,source_step.step_name,source_step.step_type,source_step.role_id,source_step.approver_id,source_step.is_active,NOW(),'SYSTEM:MIGRATION',NOW(),'SYSTEM:MIGRATION'
FROM user_approval_templates target
JOIN user_approval_templates source_template ON source_template.id=(SELECT source_id.id FROM user_approval_templates source_id WHERE source_id.document_type='DAILY_EMPLOYMENT_INCOME' AND source_id.is_active=1 ORDER BY source_id.sort_no,source_id.id LIMIT 1)
JOIN user_approval_template_steps source_step ON source_step.template_id=source_template.id
WHERE target.template_key='BUSINESS_INCOME_DEFAULT'
  AND NOT EXISTS(SELECT 1 FROM user_approval_template_steps current_step WHERE current_step.template_id=target.id AND current_step.sort_no=source_step.sort_no);
