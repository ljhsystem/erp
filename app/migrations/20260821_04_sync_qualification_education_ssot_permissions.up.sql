INSERT INTO `auth_permissions` (`id`,`sort_no`,`page`,`permission_source`,`category`,`permission_key`,`permission_name`,`description`,`page_key`,`is_active`,`created_at`,`created_by`,`updated_at`,`updated_by`)
SELECT UUID(),(SELECT COALESCE(MAX(p.sort_no),0)+v.seq FROM auth_permissions p),'자격·교육관리','ROUTE','대외기관업무',v.permission_key,v.permission_name,v.description,'web.institution.human_resources.qualification_education',1,NOW(),'SYSTEM:MIGRATION',NOW(),'SYSTEM:MIGRATION'
FROM (
  SELECT 1 seq,'api.institution.human_resources.qualification_education.qualification_invalidate' permission_key,'자격무효화' permission_name,'검증된 자격 무효화' description UNION ALL
  SELECT 2,'api.institution.human_resources.qualification_education.education_invalidate','교육이력무효화','확정 교육이력 무효화' UNION ALL
  SELECT 3,'api.institution.human_resources.qualification_education.qualification_type_list','자격기준조회','자격 기준정보 조회' UNION ALL
  SELECT 4,'api.institution.human_resources.qualification_education.policy_manage','자격교육정책관리','자격 기준정보와 직무 요구조건 관리' UNION ALL
  SELECT 5,'api.institution.human_resources.qualification_education.course_list','교육과정조회','교육과정 기준정보 조회' UNION ALL
  SELECT 6,'api.institution.human_resources.qualification_education.requirement_list','직무요건조회','직무별 자격·교육 요구조건 조회' UNION ALL
  SELECT 7,'api.institution.human_resources.qualification_education.requirement_manage','직무요건관리','직무별 자격·교육 요구조건 관리' UNION ALL
  SELECT 8,'api.institution.human_resources.qualification_education.policy_reorder','기준순서변경','자격 기준과 교육과정 표시순서 변경'
) v
WHERE NOT EXISTS (SELECT 1 FROM auth_permissions x WHERE x.permission_key=v.permission_key);

INSERT INTO `auth_role_permissions` (`id`,`role_id`,`permission_id`,`created_at`,`created_by`)
SELECT UUID(),r.id,p.id,NOW(),'SYSTEM:MIGRATION'
FROM auth_roles r JOIN auth_permissions p ON p.permission_key IN (
  'api.institution.human_resources.qualification_education.qualification_invalidate',
  'api.institution.human_resources.qualification_education.education_invalidate',
  'api.institution.human_resources.qualification_education.qualification_type_list',
  'api.institution.human_resources.qualification_education.policy_manage',
  'api.institution.human_resources.qualification_education.course_list',
  'api.institution.human_resources.qualification_education.requirement_list',
  'api.institution.human_resources.qualification_education.requirement_manage',
  'api.institution.human_resources.qualification_education.policy_reorder'
)
LEFT JOIN auth_role_permissions rp ON rp.role_id=r.id AND rp.permission_id=p.id
WHERE r.role_key='super_admin' AND rp.id IS NULL;

DELETE rp FROM `auth_role_permissions` rp JOIN `auth_permissions` p ON p.id=rp.permission_id WHERE p.permission_key='api.institution.human_resources.qualification_education.excel';
DELETE FROM `auth_permissions` WHERE permission_key='api.institution.human_resources.qualification_education.excel';
