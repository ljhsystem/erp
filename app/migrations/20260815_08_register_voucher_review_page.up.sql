INSERT INTO system_page_registry (
    page_key, module_key, module_label, menu_key, menu_label,
    page_label, page_description, breadcrumb,
    default_route_key, default_route_url, source_description,
    is_active, created_at, updated_at
)
SELECT
    'ledger.vouchers.review', 'ledger', '회계관리', 'ledger.vouchers', '전표관리',
    '전표검토·전기', '회계관리 > 전표관리 > 전표검토·전기',
    '회계관리 > 전표관리 > 전표검토·전기',
    'web.ledger.vouchers.review', '/ledger/vouchers/review',
    '회계관리 > 전표관리 > 전표검토·전기',
    1, NOW(), NOW()
WHERE NOT EXISTS (
    SELECT 1 FROM system_page_registry WHERE page_key = 'ledger.vouchers.review'
);
