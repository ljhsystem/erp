DELETE mapping
FROM auth_role_permissions mapping
JOIN auth_permissions permission ON permission.id = mapping.permission_id
WHERE permission.permission_key IN (
    'api.institution.human_resources.job_assignment.history_save',
    'api.institution.human_resources.job_assignment.project_save',
    'api.institution.human_resources.job_assignment.end',
    'api.institution.human_resources.job_assignment.correct'
);

DELETE FROM auth_permissions
WHERE permission_key IN (
    'api.institution.human_resources.job_assignment.history_save',
    'api.institution.human_resources.job_assignment.project_save',
    'api.institution.human_resources.job_assignment.end',
    'api.institution.human_resources.job_assignment.correct'
);

DELETE FROM system_codes
WHERE code_group IN ('EMPLOYEE_ASSIGNMENT_AUDIT_ACTION', 'EMPLOYEE_ASSIGNMENT_SOURCE')
  AND created_by = 'SYSTEM:EMPLOYEE_ASSIGNMENT';

DROP TABLE IF EXISTS `user_employee_assignment_audits`;
