DELETE FROM `user_approval_template_steps` WHERE `template_id`='30b621cb-2f5a-4eb6-ae22-9087f9833461';
DELETE FROM `user_approval_templates` WHERE `id`='30b621cb-2f5a-4eb6-ae22-9087f9833461';
DELETE rp FROM `auth_role_permissions` rp JOIN `auth_permissions` p ON p.id=rp.permission_id WHERE p.permission_key LIKE 'api.institution.human_resources.leave.%';
DELETE FROM `auth_permissions` WHERE `permission_key` LIKE 'api.institution.human_resources.leave.%';
UPDATE `system_page_registry` SET `page_description`='휴가관리 Placeholder',`source_description`='Placeholder',`updated_at`=NOW() WHERE `page_key`='web.institution.human_resources.leave';
