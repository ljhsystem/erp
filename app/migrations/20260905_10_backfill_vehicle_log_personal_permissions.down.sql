DELETE user_permission
FROM `auth_user_permissions` user_permission
JOIN `auth_permissions` permission ON permission.id=user_permission.permission_id
WHERE permission.permission_key LIKE 'api.ledger.vehicle_log.%'
  AND user_permission.created_by='SYSTEM:MIGRATION:VEHICLE_LOG_PERMISSION_BACKFILL';
