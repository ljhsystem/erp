INSERT INTO `auth_permissions` (
    id, sort_no, page, permission_source, category, permission_key,
    permission_name, description, page_key, is_active,
    created_at, created_by, updated_at, updated_by
)
SELECT
    UUID(),
    (SELECT COALESCE(MAX(existing.sort_no), 0) + 1 FROM `auth_permissions` existing),
    '거래입력',
    'ROUTE',
    '회계관리 > 전표관리',
    'api.ledger.transaction.reorder',
    '정렬저장',
    '거래 목록 순서 저장',
    source.page_key,
    1,
    NOW(),
    'SYSTEM:MIGRATION',
    NOW(),
    'SYSTEM:MIGRATION'
FROM `auth_permissions` source
WHERE source.permission_key = 'api.ledger.transaction.save'
  AND NOT EXISTS (
      SELECT 1 FROM `auth_permissions`
      WHERE permission_key = 'api.ledger.transaction.reorder'
  );

INSERT INTO `auth_role_permissions` (id, role_id, permission_id, created_at, created_by)
SELECT UUID(), source.role_id, target.id, NOW(), 'SYSTEM:MIGRATION'
FROM `auth_role_permissions` source
INNER JOIN `auth_permissions` save_permission
    ON save_permission.id = source.permission_id
   AND save_permission.permission_key = 'api.ledger.transaction.save'
INNER JOIN `auth_permissions` target
    ON target.permission_key = 'api.ledger.transaction.reorder'
LEFT JOIN `auth_role_permissions` existing
    ON existing.role_id = source.role_id
   AND existing.permission_id = target.id
WHERE existing.id IS NULL;

INSERT INTO `auth_user_permissions` (id, user_id, permission_id, created_at, created_by)
SELECT UUID(), source.user_id, target.id, NOW(), 'SYSTEM:MIGRATION'
FROM `auth_user_permissions` source
INNER JOIN `auth_permissions` save_permission
    ON save_permission.id = source.permission_id
   AND save_permission.permission_key = 'api.ledger.transaction.save'
INNER JOIN `auth_permissions` target
    ON target.permission_key = 'api.ledger.transaction.reorder'
LEFT JOIN `auth_user_permissions` existing
    ON existing.user_id = source.user_id
   AND existing.permission_id = target.id
WHERE existing.id IS NULL;
