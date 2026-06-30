DELETE `rp`
FROM `auth_role_permissions` `rp`
JOIN `auth_roles` `r`
    ON `r`.`id` = `rp`.`role_id`
JOIN `auth_permissions` `p`
    ON `p`.`id` = `rp`.`permission_id`
WHERE `p`.`permission_key` = 'api.settings.system.data_table_columns'
  AND `r`.`role_key` IN ('super_admin', 'admin');
