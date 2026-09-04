INSERT INTO `system_page_registry` (
    `page_key`,`module_key`,`module_label`,`menu_key`,`menu_label`,`page_label`,
    `page_description`,`breadcrumb`,`default_route_key`,`default_route_url`,
    `source_description`,`is_active`,`created_at`,`updated_at`
)
SELECT
    CASE `page_key`
        WHEN 'dashboard.main' THEN 'main.dashboard'
        ELSE CONCAT('main.', SUBSTRING_INDEX(`page_key`, '.', -1))
    END,
    'main',
    '메인',
    'main.dashboard',
    '메인',
    `page_label`,
    `page_description`,
    CONCAT('메인 > ', `page_label`),
    REPLACE(`default_route_key`, 'web.dashboard.', 'web.main.'),
    REPLACE(`default_route_url`, '/dashboard', '/main'),
    CONCAT('메인 > ', `page_label`),
    `is_active`,
    `created_at`,
    NOW()
FROM `system_page_registry`
WHERE `page_key` LIKE 'dashboard.%'
ON DUPLICATE KEY UPDATE
    `module_key`=VALUES(`module_key`),
    `module_label`=VALUES(`module_label`),
    `menu_key`=VALUES(`menu_key`),
    `menu_label`=VALUES(`menu_label`),
    `page_label`=VALUES(`page_label`),
    `page_description`=VALUES(`page_description`),
    `breadcrumb`=VALUES(`breadcrumb`),
    `default_route_key`=VALUES(`default_route_key`),
    `default_route_url`=VALUES(`default_route_url`),
    `source_description`=VALUES(`source_description`),
    `is_active`=VALUES(`is_active`),
    `updated_at`=NOW();

INSERT INTO `system_menu_registry` (
    `menu_key`,`page_key`,`module_key`,`menu_label`,`module_order`,`menu_order`,
    `page_order`,`menu_icon`,`default_entry`,`is_menu`,`visible_in_sidebar`,
    `visible_in_settings`,`visible_in_sitemap`,`visible_in_navbar`,`is_active`,
    `created_at`,`updated_at`
)
SELECT
    CASE `menu_key`
        WHEN 'dashboard.main' THEN 'main.dashboard'
        ELSE CONCAT('main.', SUBSTRING_INDEX(`menu_key`, '.', -1))
    END,
    CASE `page_key`
        WHEN 'dashboard.main' THEN 'main.dashboard'
        ELSE CONCAT('main.', SUBSTRING_INDEX(`page_key`, '.', -1))
    END,
    'main',
    `menu_label`,
    `module_order`,
    `menu_order`,
    `page_order`,
    `menu_icon`,
    REPLACE(`default_entry`, '/dashboard', '/main'),
    `is_menu`,
    `visible_in_sidebar`,
    `visible_in_settings`,
    `visible_in_sitemap`,
    `visible_in_navbar`,
    `is_active`,
    `created_at`,
    NOW()
FROM `system_menu_registry`
WHERE `menu_key` LIKE 'dashboard.%'
ON DUPLICATE KEY UPDATE
    `page_key`=VALUES(`page_key`),
    `module_key`=VALUES(`module_key`),
    `menu_label`=VALUES(`menu_label`),
    `module_order`=VALUES(`module_order`),
    `menu_order`=VALUES(`menu_order`),
    `page_order`=VALUES(`page_order`),
    `menu_icon`=VALUES(`menu_icon`),
    `default_entry`=VALUES(`default_entry`),
    `is_menu`=VALUES(`is_menu`),
    `visible_in_sidebar`=VALUES(`visible_in_sidebar`),
    `visible_in_settings`=VALUES(`visible_in_settings`),
    `visible_in_sitemap`=VALUES(`visible_in_sitemap`),
    `visible_in_navbar`=VALUES(`visible_in_navbar`),
    `is_active`=VALUES(`is_active`),
    `updated_at`=NOW();

UPDATE `auth_permissions`
SET `page_key` = CASE `page_key`
        WHEN 'dashboard.main' THEN 'main.dashboard'
        ELSE CONCAT('main.', SUBSTRING_INDEX(`page_key`, '.', -1))
    END,
    `updated_at` = NOW(),
    `updated_by` = 'SYSTEM:MIGRATION'
WHERE `page_key` LIKE 'dashboard.%';

UPDATE `system_notification_recipients`
SET `action_page_key` = CASE `action_page_key`
        WHEN 'dashboard.main' THEN 'main.dashboard'
        ELSE CONCAT('main.', SUBSTRING_INDEX(`action_page_key`, '.', -1))
    END,
    `action_url_fallback` = REPLACE(`action_url_fallback`, '/dashboard', '/main')
WHERE `action_page_key` LIKE 'dashboard.%'
   OR `action_url_fallback` LIKE '/dashboard%';

DELETE legacy_setting
FROM `system_user_settings` legacy_setting
JOIN `system_user_settings` canonical_setting
  ON canonical_setting.`user_id` = legacy_setting.`user_id`
 AND canonical_setting.`setting_type` = legacy_setting.`setting_type`
 AND canonical_setting.`page_key` = CONCAT('main.', SUBSTRING(legacy_setting.`page_key`, LENGTH('dashboard.') + 1))
WHERE legacy_setting.`page_key` LIKE 'dashboard.%';

UPDATE `system_user_settings`
SET `page_key` = CONCAT('main.', SUBSTRING(`page_key`, LENGTH('dashboard.') + 1)),
    `updated_at` = NOW(),
    `updated_by` = 'SYSTEM:MIGRATION'
WHERE `page_key` LIKE 'dashboard.%';

UPDATE `system_page_registry`
SET `default_route_url` = REPLACE(`default_route_url`, '/dashboard/settings', '/main/settings'),
    `updated_at` = NOW()
WHERE `default_route_url` LIKE '/dashboard/settings%';

UPDATE `system_menu_registry`
SET `default_entry` = REPLACE(`default_entry`, '/dashboard/settings', '/main/settings'),
    `updated_at` = NOW()
WHERE `default_entry` LIKE '/dashboard/settings%';

DELETE FROM `system_menu_registry` WHERE `menu_key` LIKE 'dashboard.%';
DELETE FROM `system_page_registry` WHERE `page_key` LIKE 'dashboard.%';
