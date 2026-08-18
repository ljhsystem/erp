DELETE rp
FROM `auth_role_permissions` rp
JOIN `auth_roles` r ON r.`id` = rp.`role_id`
JOIN `auth_permissions` p ON p.`id` = rp.`permission_id`
WHERE r.`role_key` = 'super_admin'
  AND p.`permission_key` LIKE 'api.institution.human_resources.attendance.%'
  AND rp.`created_by` = 'SYSTEM:ATTENDANCE_PERMISSION_SYNC';

UPDATE `auth_permissions`
SET `is_active` = 1
WHERE `permission_key` = 'api.institution.human_resources.attendance.clock';
