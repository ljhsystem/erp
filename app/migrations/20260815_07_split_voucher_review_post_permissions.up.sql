INSERT INTO `auth_permissions` (
    id, sort_no, page, permission_source, category, permission_key,
    permission_name, description, page_key, is_active,
    created_at, created_by, updated_at, updated_by
)
SELECT UUID(), (SELECT COALESCE(MAX(p.sort_no), 0) + 1 FROM auth_permissions p),
       '전표검토·전기', 'ROUTE', '회계관리 > 전표관리',
       'api.ledger.voucher.review', '검토', '전표 검토완료 및 반려',
       'ledger.vouchers.review', 1, NOW(), 'SYSTEM:MIGRATION', NOW(), 'SYSTEM:MIGRATION'
WHERE NOT EXISTS (
    SELECT 1 FROM auth_permissions WHERE permission_key = 'api.ledger.voucher.review'
);

INSERT INTO `auth_permissions` (
    id, sort_no, page, permission_source, category, permission_key,
    permission_name, description, page_key, is_active,
    created_at, created_by, updated_at, updated_by
)
SELECT UUID(), (SELECT COALESCE(MAX(p.sort_no), 0) + 1 FROM auth_permissions p),
       '전표검토·전기', 'ROUTE', '회계관리 > 전표관리',
       'api.ledger.voucher.review_cancel', '검토완료 취소', '전표 검토완료 취소',
       'ledger.vouchers.review', 1, NOW(), 'SYSTEM:MIGRATION', NOW(), 'SYSTEM:MIGRATION'
WHERE NOT EXISTS (
    SELECT 1 FROM auth_permissions WHERE permission_key = 'api.ledger.voucher.review_cancel'
);

INSERT INTO auth_role_permissions (id, role_id, permission_id, created_at, created_by)
SELECT UUID(), source.role_id, target.id, NOW(), 'SYSTEM:MIGRATION'
FROM auth_role_permissions source
INNER JOIN auth_permissions old_permission ON old_permission.id = source.permission_id
INNER JOIN auth_permissions target ON target.permission_key = 'api.ledger.voucher.review'
LEFT JOIN auth_role_permissions existing
    ON existing.role_id = source.role_id AND existing.permission_id = target.id
WHERE old_permission.permission_key IN ('api.ledger.voucher.complete_review', 'api.ledger.voucher.reject')
  AND existing.id IS NULL
GROUP BY source.role_id, target.id;

INSERT INTO auth_user_permissions (id, user_id, permission_id, created_at, created_by)
SELECT UUID(), source.user_id, target.id, NOW(), 'SYSTEM:MIGRATION'
FROM auth_user_permissions source
INNER JOIN auth_permissions old_permission ON old_permission.id = source.permission_id
INNER JOIN auth_permissions target ON target.permission_key = 'api.ledger.voucher.review'
LEFT JOIN auth_user_permissions existing
    ON existing.user_id = source.user_id AND existing.permission_id = target.id
WHERE old_permission.permission_key IN ('api.ledger.voucher.complete_review', 'api.ledger.voucher.reject')
  AND existing.id IS NULL
GROUP BY source.user_id, target.id;

INSERT INTO auth_role_permissions (id, role_id, permission_id, created_at, created_by)
SELECT UUID(), source.role_id, target.id, NOW(), 'SYSTEM:MIGRATION'
FROM auth_role_permissions source
INNER JOIN auth_permissions old_permission ON old_permission.id = source.permission_id
INNER JOIN auth_permissions target ON target.permission_key = 'api.ledger.voucher.review_cancel'
LEFT JOIN auth_role_permissions existing
    ON existing.role_id = source.role_id AND existing.permission_id = target.id
WHERE old_permission.permission_key = 'api.ledger.voucher.cancel_complete_review'
  AND existing.id IS NULL;

INSERT INTO auth_user_permissions (id, user_id, permission_id, created_at, created_by)
SELECT UUID(), source.user_id, target.id, NOW(), 'SYSTEM:MIGRATION'
FROM auth_user_permissions source
INNER JOIN auth_permissions old_permission ON old_permission.id = source.permission_id
INNER JOIN auth_permissions target ON target.permission_key = 'api.ledger.voucher.review_cancel'
LEFT JOIN auth_user_permissions existing
    ON existing.user_id = source.user_id AND existing.permission_id = target.id
WHERE old_permission.permission_key = 'api.ledger.voucher.cancel_complete_review'
  AND existing.id IS NULL;

UPDATE auth_permissions
SET page = '전표검토·전기', description = '전표 전기', page_key = 'ledger.vouchers.review',
    updated_at = NOW(), updated_by = 'SYSTEM:MIGRATION'
WHERE permission_key = 'api.ledger.voucher.post';

UPDATE auth_permissions
SET page = '전표검토·전기', permission_name = '취소전표 작성', description = '전표 취소전표 작성(역분개)',
    page_key = 'ledger.vouchers.review', updated_at = NOW(), updated_by = 'SYSTEM:MIGRATION'
WHERE permission_key = 'api.ledger.voucher.reverse';

UPDATE system_page_registry
SET page_label = '전표검토·전기', page_description = '전표 검토 및 전기', updated_at = NOW()
WHERE page_key = 'ledger.vouchers.review';

DELETE rp FROM auth_role_permissions rp
INNER JOIN auth_permissions p ON p.id = rp.permission_id
WHERE p.permission_key IN ('api.ledger.voucher.cancel_review', 'api.ledger.voucher.cancel_review_completion');

DELETE up FROM auth_user_permissions up
INNER JOIN auth_permissions p ON p.id = up.permission_id
WHERE p.permission_key IN ('api.ledger.voucher.cancel_review', 'api.ledger.voucher.cancel_review_completion');

DELETE FROM auth_permissions
WHERE permission_key IN ('api.ledger.voucher.cancel_review', 'api.ledger.voucher.cancel_review_completion');
