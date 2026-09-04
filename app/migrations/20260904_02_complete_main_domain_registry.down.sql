INSERT INTO `system_page_registry` (
    `page_key`,`module_key`,`module_label`,`menu_key`,`menu_label`,`page_label`,
    `page_description`,`breadcrumb`,`default_route_key`,`default_route_url`,
    `source_description`,`is_active`,`created_at`,`updated_at`
)
SELECT
    CASE `page_key`
        WHEN 'main.dashboard' THEN 'dashboard.main'
        ELSE CONCAT('dashboard.', SUBSTRING_INDEX(`page_key`, '.', -1))
    END,
    'dashboard','대시보드','dashboard.main','대시보드',`page_label`,`page_description`,
    CONCAT('대시보드 > 대시보드 > ', `page_label`),
    REPLACE(`default_route_key`, 'web.main.', 'web.dashboard.'),
    REPLACE(`default_route_url`, '/main', '/dashboard'),
    CONCAT('대시보드 > 대시보드 > ', `page_label`),
    `is_active`,`created_at`,NOW()
FROM `system_page_registry`
WHERE `page_key` IN ('main.dashboard','main.report','main.activity','main.notifications','main.kpi','main.calendar','main.settings')
ON DUPLICATE KEY UPDATE `updated_at`=NOW();

INSERT INTO `system_menu_registry` (
    `menu_key`,`page_key`,`module_key`,`menu_label`,`module_order`,`menu_order`,
    `page_order`,`menu_icon`,`default_entry`,`is_menu`,`visible_in_sidebar`,
    `visible_in_settings`,`visible_in_sitemap`,`visible_in_navbar`,`is_active`,
    `created_at`,`updated_at`
)
SELECT
    CASE `menu_key`
        WHEN 'main.dashboard' THEN 'dashboard.main'
        ELSE CONCAT('dashboard.', SUBSTRING_INDEX(`menu_key`, '.', -1))
    END,
    CASE `page_key`
        WHEN 'main.dashboard' THEN 'dashboard.main'
        ELSE CONCAT('dashboard.', SUBSTRING_INDEX(`page_key`, '.', -1))
    END,
    'dashboard',`menu_label`,`module_order`,`menu_order`,`page_order`,`menu_icon`,
    REPLACE(`default_entry`, '/main', '/dashboard'),`is_menu`,`visible_in_sidebar`,
    `visible_in_settings`,`visible_in_sitemap`,`visible_in_navbar`,`is_active`,`created_at`,NOW()
FROM `system_menu_registry`
WHERE `menu_key` IN ('main.dashboard','main.report','main.activity','main.notifications','main.kpi','main.calendar','main.settings')
ON DUPLICATE KEY UPDATE `updated_at`=NOW();

UPDATE `auth_permissions`
SET `page_key` = CASE `page_key`
        WHEN 'main.dashboard' THEN 'dashboard.main'
        ELSE CONCAT('dashboard.', SUBSTRING_INDEX(`page_key`, '.', -1))
    END,
    `updated_at`=NOW(),
    `updated_by`='SYSTEM:MIGRATION'
WHERE `page_key` IN ('main.dashboard','main.report','main.activity','main.notifications','main.kpi','main.calendar','main.settings');

UPDATE `system_notification_recipients`
SET `action_page_key` = CASE `action_page_key`
        WHEN 'main.dashboard' THEN 'dashboard.main'
        ELSE CONCAT('dashboard.', SUBSTRING_INDEX(`action_page_key`, '.', -1))
    END,
    `action_url_fallback` = REPLACE(`action_url_fallback`, '/main', '/dashboard')
WHERE `action_page_key` IN ('main.dashboard','main.report','main.activity','main.notifications','main.kpi','main.calendar','main.settings')
   OR `action_url_fallback` LIKE '/main%';

DELETE legacy_setting
FROM `system_user_settings` legacy_setting
JOIN `system_user_settings` canonical_setting
  ON canonical_setting.`user_id` = legacy_setting.`user_id`
 AND canonical_setting.`setting_type` = legacy_setting.`setting_type`
 AND canonical_setting.`page_key` = CONCAT('dashboard.', SUBSTRING(legacy_setting.`page_key`, LENGTH('main.') + 1))
WHERE legacy_setting.`page_key` LIKE 'main.settings.%';

UPDATE `system_user_settings`
SET `page_key`=CONCAT('dashboard.', SUBSTRING(`page_key`, LENGTH('main.') + 1)),
    `updated_at`=NOW(),
    `updated_by`='SYSTEM:MIGRATION'
WHERE `page_key` LIKE 'main.settings.%';

UPDATE `system_page_registry`
SET `default_route_url`=REPLACE(`default_route_url`, '/main/settings', '/dashboard/settings'),`updated_at`=NOW()
WHERE `default_route_url` LIKE '/main/settings%';

UPDATE `system_menu_registry`
SET `default_entry`=REPLACE(`default_entry`, '/main/settings', '/dashboard/settings'),`updated_at`=NOW()
WHERE `default_entry` LIKE '/main/settings%';

DELETE FROM `system_menu_registry`
WHERE `menu_key` IN ('main.dashboard','main.report','main.activity','main.notifications','main.kpi','main.calendar','main.settings');
DELETE FROM `system_page_registry`
WHERE `page_key` IN ('main.dashboard','main.report','main.activity','main.notifications','main.kpi','main.calendar','main.settings');
