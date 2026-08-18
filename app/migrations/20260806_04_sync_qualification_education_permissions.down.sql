DELETE rp FROM auth_role_permissions rp JOIN auth_permissions p ON p.id=rp.permission_id WHERE p.permission_key='api.institution.human_resources.qualification_education.education_delete';
DELETE FROM auth_permissions WHERE permission_key='api.institution.human_resources.qualification_education.education_delete';
