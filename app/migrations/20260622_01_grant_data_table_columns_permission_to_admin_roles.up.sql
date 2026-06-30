INSERT INTO `auth_role_permissions` (
    `id`,
    `sort_no`,
    `role_id`,
    `permission_id`,
    `created_at`,
    `created_by`
)
SELECT
    UUID(),
    COALESCE((SELECT MAX(`sort_no`) FROM `auth_role_permissions`), 0) + 1,
    `r`.`id`,
    `p`.`id`,
    NOW(),
    'SYSTEM'
FROM `auth_roles` `r`
JOIN `auth_permissions` `p`
    ON `p`.`permission_key` = 'api.settings.system.data_table_columns'
WHERE `r`.`role_key` = 'super_admin'
  AND NOT EXISTS (
      SELECT 1
      FROM `auth_role_permissions` `rp`
      WHERE `rp`.`role_id` = `r`.`id`
        AND `rp`.`permission_id` = `p`.`id`
  );

INSERT INTO `auth_role_permissions` (
    `id`,
    `sort_no`,
    `role_id`,
    `permission_id`,
    `created_at`,
    `created_by`
)
SELECT
    UUID(),
    COALESCE((SELECT MAX(`sort_no`) FROM `auth_role_permissions`), 0) + 1,
    `r`.`id`,
    `p`.`id`,
    NOW(),
    'SYSTEM'
FROM `auth_roles` `r`
JOIN `auth_permissions` `p`
    ON `p`.`permission_key` = 'api.settings.system.data_table_columns'
WHERE `r`.`role_key` = 'admin'
  AND NOT EXISTS (
      SELECT 1
      FROM `auth_role_permissions` `rp`
      WHERE `rp`.`role_id` = `r`.`id`
        AND `rp`.`permission_id` = `p`.`id`
  );
