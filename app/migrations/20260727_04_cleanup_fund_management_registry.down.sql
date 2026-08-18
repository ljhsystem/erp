UPDATE `system_user_settings` old_setting
SET old_setting.`page_key` = 'funds.bank-transactions',
    old_setting.`updated_at` = CURRENT_TIMESTAMP
WHERE old_setting.`page_key` = 'ledger.funds.bank_transactions'
  AND NOT EXISTS (
      SELECT 1
      FROM `system_user_settings` legacy
      WHERE legacy.`user_id` = old_setting.`user_id`
        AND legacy.`setting_type` = old_setting.`setting_type`
        AND legacy.`page_key` = 'funds.bank-transactions'
  );

UPDATE `auth_permissions`
SET `permission_key` = 'web.ledger.funds.account_transactions',
    `page_key` = 'ledger.funds.bank_transactions',
    `updated_at` = CURRENT_TIMESTAMP,
    `updated_by` = 'SYSTEM:MIGRATION'
WHERE `id` = 'f1d17438-c51d-4147-827c-dfd98fa5600e'
  AND `permission_key` = 'web.ledger.funds.bank_transactions';

UPDATE `system_page_registry`
SET `page_label` = '계정잔액',
    `page_description` = '회계관리 > 자금관리 > 계정잔액',
    `breadcrumb` = '회계관리 > 자금관리 > 계정잔액',
    `default_route_url` = NULL,
    `source_description` = '회계관리 > 자금관리 > 계정잔액',
    `updated_at` = CURRENT_TIMESTAMP
WHERE `page_key` = 'ledger.funds.account_balances';

UPDATE `system_menu_registry`
SET `menu_label` = '계정잔액',
    `default_entry` = '/ledger/funds/account-balances',
    `updated_at` = CURRENT_TIMESTAMP
WHERE `menu_key` = 'ledger.funds.account_balances';

UPDATE `system_page_registry`
SET `page_label` = '일일자금보고',
    `page_description` = '회계관리 > 자금관리 > 일일자금보고',
    `breadcrumb` = '회계관리 > 자금관리 > 일일자금보고',
    `source_description` = '회계관리 > 자금관리 > 일일자금보고',
    `updated_at` = CURRENT_TIMESTAMP
WHERE `page_key` = 'ledger.funds.daily_report';

UPDATE `system_menu_registry`
SET `menu_label` = '일일자금보고',
    `updated_at` = CURRENT_TIMESTAMP
WHERE `menu_key` = 'ledger.funds.daily_report';

INSERT INTO `system_page_registry`
(`page_key`,`module_key`,`module_label`,`menu_key`,`menu_label`,`page_label`,`page_description`,`breadcrumb`,`default_route_key`,`default_route_url`,`source_description`,`is_active`,`created_at`,`updated_at`)
VALUES
('ledger.funds.payment_info','ledger','회계관리','ledger.funds','자금관리','결제정보','회계관리 > 자금관리 > 결제정보','회계관리 > 자금관리 > 결제정보','web.ledger.funds.payment_info','/ledger/funds/payment-info','회계관리 > 자금관리 > 결제정보',1,'2026-06-08 20:07:19','2026-06-08 20:07:19'),
('ledger.funds.reconciliation','ledger','회계관리','ledger.funds','자금관리','계좌대사','회계관리 > 자금관리 > 계좌대사','회계관리 > 자금관리 > 계좌대사','web.ledger.funds.reconciliation',NULL,'회계관리 > 자금관리 > 계좌대사',1,'2026-06-08 20:07:19','2026-06-08 20:07:19')
ON DUPLICATE KEY UPDATE `page_key` = VALUES(`page_key`);

INSERT INTO `system_menu_registry`
(`menu_key`,`page_key`,`module_key`,`menu_label`,`module_order`,`menu_order`,`page_order`,`menu_icon`,`default_entry`,`is_menu`,`visible_in_sidebar`,`visible_in_settings`,`visible_in_sitemap`,`visible_in_navbar`,`is_active`,`created_at`,`updated_at`)
VALUES
('ledger.funds.payment_info','ledger.funds.payment_info','ledger','결제정보',30,80,20,'bi-credit-card-2-front','/ledger/funds/payment-info',1,1,0,1,0,1,'2026-06-08 21:12:54','2026-06-08 21:12:54'),
('ledger.funds.reconciliation','ledger.funds.reconciliation','ledger','계좌대사',30,80,90,NULL,'/ledger/funds/reconciliation',1,0,0,1,0,1,'2026-06-08 21:12:54','2026-06-08 21:12:54')
ON DUPLICATE KEY UPDATE `menu_key` = VALUES(`menu_key`);

INSERT INTO `auth_permissions`
(`id`,`sort_no`,`page`,`permission_source`,`category`,`permission_key`,`permission_name`,`description`,`page_key`,`is_active`,`created_at`,`created_by`,`updated_at`,`updated_by`)
VALUES
('c808abc7-c508-481d-9a63-5206d5f43607',580,NULL,NULL,'회계관리 > 자금관리','web.ledger.funds.cash_ledger','화면조회','현금출납장 화면 조회',NULL,1,'2026-07-27 10:52:07','SYSTEM:자동','2026-07-27 16:47:23','SYSTEM:자동'),
('01689b43-e4df-430d-86c6-33ad736a4325',581,NULL,NULL,'회계관리 > 자금관리','web.ledger.funds.deposit_ledger','화면조회','예금출납장 화면 조회',NULL,1,'2026-07-27 10:52:07','SYSTEM:자동','2026-07-27 16:47:23','SYSTEM:자동'),
('52736d14-c85e-4bca-9b66-741e80985bdb',582,'결제정보','web','회계관리 > 자금관리','web.ledger.funds.payment_info','화면조회','결제정보 화면 조회','ledger.funds.payment_info',1,'2026-05-23 10:39:10','SYSTEM:자동','2026-07-27 16:47:23','SYSTEM:자동'),
('e5a89cff-28ca-42f9-858f-d19426a86d26',583,'계좌대사','web','system','web.ledger.funds.reconciliation','계좌대사','회계관리 > 자금관리 > 계좌대사','ledger.funds.reconciliation',1,'2026-05-22 11:58:57','SYSTEM:자동','2026-07-27 16:47:23','SYSTEM:자동')
ON DUPLICATE KEY UPDATE `id` = VALUES(`id`);

INSERT INTO `auth_role_permissions`
(`id`,`role_id`,`permission_id`,`created_at`,`created_by`)
VALUES
('a8654f72-6668-44d0-9198-c48f5e571a3e','c1c90ecf-1a44-470c-8d9c-4d6e671cdcfa','c808abc7-c508-481d-9a63-5206d5f43607',CURRENT_TIMESTAMP,'SYSTEM:MIGRATION'),
('ace5e9d1-3b49-409f-9d12-b301f777becc','c1c90ecf-1a44-470c-8d9c-4d6e671cdcfa','01689b43-e4df-430d-86c6-33ad736a4325',CURRENT_TIMESTAMP,'SYSTEM:MIGRATION'),
('5eeaa67a-3be4-49c6-a29e-f1fe26eb17d5','c1c90ecf-1a44-470c-8d9c-4d6e671cdcfa','52736d14-c85e-4bca-9b66-741e80985bdb',CURRENT_TIMESTAMP,'SYSTEM:MIGRATION'),
('8d235167-f49a-4eb7-ae3e-342630f98cab','08361618-c06d-4fd5-b18d-be61e1b1058e','52736d14-c85e-4bca-9b66-741e80985bdb',CURRENT_TIMESTAMP,'SYSTEM:MIGRATION'),
('2535597b-c282-45c9-bc48-eda2bd52b683','08361618-c06d-4fd5-b18d-be61e1b1058e','e5a89cff-28ca-42f9-858f-d19426a86d26',CURRENT_TIMESTAMP,'SYSTEM:MIGRATION'),
('dc15f82f-0e8f-40de-a75b-cbf5d6453ce2','c1c90ecf-1a44-470c-8d9c-4d6e671cdcfa','e5a89cff-28ca-42f9-858f-d19426a86d26',CURRENT_TIMESTAMP,'SYSTEM:MIGRATION')
ON DUPLICATE KEY UPDATE `id` = VALUES(`id`);
