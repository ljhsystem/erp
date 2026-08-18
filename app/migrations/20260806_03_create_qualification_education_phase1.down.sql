-- 자격·교육관리 1차 rollback
ALTER TABLE `user_employees` ADD COLUMN `certificate_name` varchar(50) DEFAULT NULL COMMENT '대표 자격증' AFTER `emergency_phone`, ADD COLUMN `certificate_file` varchar(255) DEFAULT NULL COMMENT '자격증파일' AFTER `certificate_name`;
UPDATE user_employees e LEFT JOIN (SELECT q.employee_id,q.qualification_name,q.attachment_path FROM institution_qualifications_employee_records q JOIN (SELECT employee_id,MAX(created_at) mx FROM institution_qualifications_employee_records WHERE deleted_at IS NULL GROUP BY employee_id) x ON x.employee_id=q.employee_id AND x.mx=q.created_at) q ON q.employee_id=e.id SET e.certificate_name=q.qualification_name,e.certificate_file=q.attachment_path;
DELETE rp FROM auth_role_permissions rp JOIN auth_permissions p ON p.id=rp.permission_id WHERE p.permission_key LIKE 'api.institution.human_resources.qualification_education.%';
DELETE FROM auth_permissions WHERE permission_key LIKE 'api.institution.human_resources.qualification_education.%';
DELETE FROM system_codes WHERE code_group IN ('QUALIFICATION_TYPE','QUALIFICATION_STATUS','EDUCATION_TYPE','EDUCATION_ATTENDANCE_STATUS','EDUCATION_COMPLETION_STATUS');
DROP TABLE `institution_educations_audits`;
DROP TABLE `institution_educations_employee_records`;
DROP TABLE `institution_educations_courses`;
DROP TABLE `institution_qualifications_audits`;
DROP TABLE `institution_qualifications_employee_records`;
