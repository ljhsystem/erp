INSERT INTO `system_page_registry` (
    `page_key`, `module_key`, `module_label`, `menu_key`, `menu_label`,
    `page_label`, `page_description`, `breadcrumb`,
    `default_route_key`, `default_route_url`, `source_description`, `is_active`
) VALUES (
    'approval.inbox', 'approval', '전자결재', 'approval.inbox', '결재함',
    '결재함', '통합 전자결재 문서함', '전자결재 > 결재함',
    'web.approval.inbox', '/approval/status', '통합 결재함 화면', 1
)
ON DUPLICATE KEY UPDATE
    `module_key` = VALUES(`module_key`),
    `module_label` = VALUES(`module_label`),
    `menu_key` = VALUES(`menu_key`),
    `menu_label` = VALUES(`menu_label`),
    `page_label` = VALUES(`page_label`),
    `page_description` = VALUES(`page_description`),
    `breadcrumb` = VALUES(`breadcrumb`),
    `default_route_key` = VALUES(`default_route_key`),
    `default_route_url` = VALUES(`default_route_url`),
    `source_description` = VALUES(`source_description`),
    `is_active` = VALUES(`is_active`);

UPDATE `system_menu_registry`
SET `menu_key` = 'approval.inbox',
    `page_key` = 'approval.inbox',
    `menu_label` = '결재함',
    `menu_icon` = 'bi-inbox',
    `default_entry` = '/approval/status',
    `updated_at` = CURRENT_TIMESTAMP
WHERE `menu_key` = 'approval.status';

UPDATE `auth_permissions`
SET `is_active` = 0
WHERE `permission_key` IN (
    'api.approval.request.create',
    'api.approval.request.detail',
    'api.approval.request.status',
    'api.approval.step.approve',
    'api.approval.step.reject',
    'api.approval.step.delete',
    'api.approval.personal-expense.pending',
    'api.approval.personal-expense.act'
);
