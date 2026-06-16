UPDATE `system_page_registry`
SET
    `default_route_key` = 'web.settings.base-info.codes',
    `default_route_url` = NULL
WHERE `page_key` = 'settings.system.codes';
