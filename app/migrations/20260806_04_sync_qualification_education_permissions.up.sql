INSERT INTO auth_permissions (id,sort_no,page,permission_source,category,permission_key,permission_name,description,page_key,is_active,created_at,created_by,updated_at,updated_by)
SELECT UUID(),COALESCE(MAX(sort_no),0)+1,'자격·교육관리','ROUTE','대외기관업무','api.institution.human_resources.qualification_education.education_delete','교육이력삭제','자격·교육관리 교육이력삭제','web.institution.human_resources.qualification_education',1,NOW(),'SYSTEM:MIGRATION',NOW(),'SYSTEM:MIGRATION'
FROM auth_permissions
WHERE NOT EXISTS (SELECT 1 FROM auth_permissions WHERE permission_key='api.institution.human_resources.qualification_education.education_delete');
INSERT INTO auth_role_permissions (id,role_id,permission_id,created_at,created_by)
SELECT UUID(),r.id,p.id,NOW(),'SYSTEM:MIGRATION' FROM auth_roles r JOIN auth_permissions p ON p.permission_key='api.institution.human_resources.qualification_education.education_delete'
LEFT JOIN auth_role_permissions rp ON rp.role_id=r.id AND rp.permission_id=p.id WHERE r.role_key='super_admin' AND rp.id IS NULL;
