UPDATE `auth_permissions`
SET `is_active` = 0
WHERE `permission_key` = 'api.institution.human_resources.attendance.clock';

INSERT INTO `auth_role_permissions` (`id`,`role_id`,`permission_id`,`created_at`,`created_by`)
SELECT UUID(), r.`id`, p.`id`, NOW(), 'SYSTEM:ATTENDANCE_PERMISSION_SYNC'
FROM `auth_roles` r
JOIN `auth_permissions` p
  ON p.`permission_key` LIKE 'api.institution.human_resources.attendance.%'
 AND p.`is_active` = 1
LEFT JOIN `auth_role_permissions` rp
  ON rp.`role_id` = r.`id`
 AND rp.`permission_id` = p.`id`
WHERE r.`role_key` = 'super_admin'
  AND rp.`id` IS NULL;
