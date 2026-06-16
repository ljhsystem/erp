UPDATE `system_page_registry`
SET
    `default_route_key` = 'code.view',
    `default_route_url` = '/dashboard/settings/system/codes'
WHERE `page_key` = 'settings.system.codes';
