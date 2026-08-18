UPDATE `system_menu_registry`
SET `menu_key` = 'approval.status',
    `page_key` = NULL,
    `menu_label` = '결재 현황',
    `menu_icon` = 'bi-hourglass-split',
    `default_entry` = '/approval/status',
    `updated_at` = CURRENT_TIMESTAMP
WHERE `menu_key` = 'approval.inbox';

DELETE FROM `system_page_registry`
WHERE `page_key` = 'approval.inbox';

UPDATE `auth_permissions`
SET `is_active` = 1
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
