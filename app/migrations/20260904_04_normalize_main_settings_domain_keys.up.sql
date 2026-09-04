UPDATE `auth_permissions`
SET `permission_key` = CASE `permission_key`
    WHEN 'web.settings.statutory_standards.manage' THEN 'web.settings.standard.statutory-standard'
    WHEN 'web.settings.base-info.brand_logo' THEN 'web.settings.base-info.brand'
    WHEN 'web.settings.base-info.clients' THEN 'web.settings.base-info.client'
    WHEN 'web.settings.base-info.projects' THEN 'web.settings.base-info.project'
    WHEN 'web.settings.base-info.accounts' THEN 'web.settings.base-info.bank-account'
    WHEN 'web.settings.base-info.cards' THEN 'web.settings.base-info.card'
    WHEN 'web.settings.organization.employees' THEN 'web.settings.organization.employee'
    WHEN 'web.settings.organization.departments' THEN 'web.settings.organization.department'
    WHEN 'web.settings.organization.positions' THEN 'web.settings.organization.position'
    WHEN 'web.settings.organization.roles' THEN 'web.settings.organization.role'
    WHEN 'web.settings.organization.role_permissions' THEN 'web.settings.organization.permission-assignment'
    WHEN 'web.settings.organization.approval' THEN 'web.settings.organization.approval-template'
    ELSE `permission_key`
END
WHERE `permission_key` IN (
    'web.settings.statutory_standards.manage','web.settings.base-info.brand_logo',
    'web.settings.base-info.clients','web.settings.base-info.projects','web.settings.base-info.accounts',
    'web.settings.base-info.cards','web.settings.organization.employees',
    'web.settings.organization.departments','web.settings.organization.positions','web.settings.organization.roles',
    'web.settings.organization.role_permissions','web.settings.organization.approval'
);

UPDATE `system_page_registry`
SET `default_route_key` = CASE `page_key`
        WHEN 'settings.statutory_standards.manage' THEN 'web.settings.standard.statutory-standard'
        WHEN 'settings.base_info.brand' THEN 'web.settings.base-info.brand'
        WHEN 'settings.base_info.clients' THEN 'web.settings.base-info.client'
        WHEN 'settings.base_info.projects' THEN 'web.settings.base-info.project'
        WHEN 'settings.base_info.bank_accounts' THEN 'web.settings.base-info.bank-account'
        WHEN 'settings.base_info.cards' THEN 'web.settings.base-info.card'
        WHEN 'settings.organization.employees' THEN 'web.settings.organization.employee'
        WHEN 'settings.organization.departments' THEN 'web.settings.organization.department'
        WHEN 'settings.organization.positions' THEN 'web.settings.organization.position'
        WHEN 'settings.organization.roles' THEN 'web.settings.organization.role'
        WHEN 'settings.organization.role_permissions' THEN 'web.settings.organization.permission-assignment'
        WHEN 'settings.organization.approval' THEN 'web.settings.organization.approval-template'
        ELSE `default_route_key`
    END,
    `default_route_url` = CASE `page_key`
        WHEN 'settings.statutory_standards.manage' THEN '/main/settings/standard/statutory-standards'
        WHEN 'settings.base_info.brand' THEN '/main/settings/base-info/brand'
        WHEN 'settings.system.codes' THEN '/main/settings/standard/code'
        WHEN 'settings.base_info.clients' THEN '/main/settings/base-info/client'
        WHEN 'settings.base_info.projects' THEN '/main/settings/base-info/project'
        WHEN 'settings.base_info.bank_accounts' THEN '/main/settings/base-info/bank-account'
        WHEN 'settings.base_info.cards' THEN '/main/settings/base-info/card'
        WHEN 'settings.base_info.work_teams' THEN '/main/settings/base-info/work-team'
        WHEN 'settings.organization.employees' THEN '/main/settings/organization/employee'
        WHEN 'settings.organization.departments' THEN '/main/settings/organization/department'
        WHEN 'settings.organization.positions' THEN '/main/settings/organization/position'
        WHEN 'settings.organization.roles' THEN '/main/settings/organization/role'
        WHEN 'settings.organization.role_permissions' THEN '/main/settings/organization/permission-assignment'
        WHEN 'settings.organization.approval' THEN '/main/settings/organization/approval-template'
        ELSE `default_route_url`
    END,
    `updated_at` = CURRENT_TIMESTAMP
WHERE `page_key` IN (
    'settings.statutory_standards.manage','settings.base_info.brand','settings.system.codes',
    'settings.base_info.clients','settings.base_info.projects','settings.base_info.bank_accounts',
    'settings.base_info.cards','settings.base_info.work_teams','settings.organization.employees',
    'settings.organization.departments','settings.organization.positions','settings.organization.roles',
    'settings.organization.role_permissions','settings.organization.approval'
);

UPDATE `system_menu_registry` smr
INNER JOIN `system_page_registry` spr ON spr.`page_key` = smr.`page_key`
SET smr.`default_entry` = spr.`default_route_url`, smr.`updated_at` = CURRENT_TIMESTAMP
WHERE smr.`page_key` LIKE 'settings.%';
