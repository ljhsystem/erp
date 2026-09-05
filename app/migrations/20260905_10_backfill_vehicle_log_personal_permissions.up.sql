INSERT INTO `auth_user_permissions` (`id`,`user_id`,`permission_id`,`created_at`,`created_by`)
SELECT UUID(), profile.user_id, api_permission.id, CURRENT_TIMESTAMP,
       'SYSTEM:MIGRATION:VEHICLE_LOG_PERMISSION_BACKFILL'
FROM `auth_user_permission_profiles` profile
JOIN `auth_user_permissions` web_map ON web_map.user_id=profile.user_id
JOIN `auth_permissions` web_permission
  ON web_permission.id=web_map.permission_id
 AND web_permission.permission_key='web.ledger.book.vehicle_log'
JOIN `auth_permissions` api_permission
  ON api_permission.permission_key LIKE 'api.ledger.vehicle_log.%'
LEFT JOIN `auth_user_permissions` existing
  ON existing.user_id=profile.user_id
 AND existing.permission_id=api_permission.id
WHERE profile.permission_mode='REPLACE'
  AND existing.id IS NULL;
