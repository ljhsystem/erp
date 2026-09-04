UPDATE `auth_permissions`
SET `permission_key` = CASE `permission_key`
    WHEN 'web.settings.standard.statutory-standard' THEN 'web.settings.statutory_standards.manage'
    WHEN 'web.settings.base-info.brand' THEN 'web.settings.base-info.brand_logo'
    WHEN 'web.settings.base-info.client' THEN 'web.settings.base-info.clients'
    WHEN 'web.settings.base-info.project' THEN 'web.settings.base-info.projects'
    WHEN 'web.settings.base-info.bank-account' THEN 'web.settings.base-info.accounts'
    WHEN 'web.settings.base-info.card' THEN 'web.settings.base-info.cards'
    WHEN 'web.settings.organization.employee' THEN 'web.settings.organization.employees'
    WHEN 'web.settings.organization.department' THEN 'web.settings.organization.departments'
    WHEN 'web.settings.organization.position' THEN 'web.settings.organization.positions'
    WHEN 'web.settings.organization.role' THEN 'web.settings.organization.roles'
    WHEN 'web.settings.organization.permission-assignment' THEN 'web.settings.organization.role_permissions'
    WHEN 'web.settings.organization.approval-template' THEN 'web.settings.organization.approval'
    ELSE `permission_key`
END
WHERE `permission_key` IN (
    'web.settings.standard.statutory-standard','web.settings.base-info.brand',
    'web.settings.base-info.client','web.settings.base-info.project','web.settings.base-info.bank-account',
    'web.settings.base-info.card','web.settings.organization.employee',
    'web.settings.organization.department','web.settings.organization.position','web.settings.organization.role',
    'web.settings.organization.permission-assignment','web.settings.organization.approval-template'
);

UPDATE `system_page_registry`
SET `default_route_key` = CASE `page_key`
        WHEN 'settings.statutory_standards.manage' THEN 'web.settings.statutory_standards.manage'
        WHEN 'settings.base_info.brand' THEN 'web.settings.base-info.brand_logo'
        WHEN 'settings.base_info.clients' THEN 'web.settings.base-info.clients'
        WHEN 'settings.base_info.projects' THEN 'web.settings.base-info.projects'
        WHEN 'settings.base_info.bank_accounts' THEN 'web.settings.base-info.accounts'
        WHEN 'settings.base_info.cards' THEN 'web.settings.base-info.cards'
        WHEN 'settings.organization.employees' THEN 'web.settings.organization.employees'
        WHEN 'settings.organization.departments' THEN 'web.settings.organization.departments'
        WHEN 'settings.organization.positions' THEN 'web.settings.organization.positions'
        WHEN 'settings.organization.roles' THEN 'web.settings.organization.roles'
        WHEN 'settings.organization.role_permissions' THEN 'web.settings.organization.role_permissions'
        WHEN 'settings.organization.approval' THEN 'web.settings.organization.approval'
        ELSE `default_route_key`
    END,
    `default_route_url` = CASE `page_key`
        WHEN 'settings.statutory_standards.manage' THEN '/main/settings/standard/statutory-standards'
        WHEN 'settings.base_info.brand' THEN '/main/settings/base-info/brand-logo'
        WHEN 'settings.system.codes' THEN '/main/settings/standard/code'
        WHEN 'settings.base_info.clients' THEN '/main/settings/base-info/clients'
        WHEN 'settings.base_info.projects' THEN '/main/settings/base-info/projects'
        WHEN 'settings.base_info.bank_accounts' THEN '/main/settings/base-info/bank-accounts'
        WHEN 'settings.base_info.cards' THEN '/main/settings/base-info/cards'
        WHEN 'settings.base_info.work_teams' THEN '/main/settings/base-info/work-teams'
        WHEN 'settings.organization.employees' THEN '/main/settings/organization/employees'
        WHEN 'settings.organization.departments' THEN '/main/settings/organization/departments'
        WHEN 'settings.organization.positions' THEN '/main/settings/organization/positions'
        WHEN 'settings.organization.roles' THEN '/main/settings/organization/roles'
        WHEN 'settings.organization.role_permissions' THEN '/main/settings/organization/permission-assignment'
        WHEN 'settings.organization.approval' THEN '/main/settings/organization/approval'
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
