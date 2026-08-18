UPDATE `system_user_settings` old_setting
SET old_setting.`page_key` = 'ledger.funds.bank_transactions',
    old_setting.`updated_at` = CURRENT_TIMESTAMP
WHERE old_setting.`page_key` = 'funds.bank-transactions'
  AND NOT EXISTS (
      SELECT 1
      FROM `system_user_settings` canonical
      WHERE canonical.`user_id` = old_setting.`user_id`
        AND canonical.`setting_type` = old_setting.`setting_type`
        AND canonical.`page_key` = 'ledger.funds.bank_transactions'
  );

UPDATE `auth_permissions`
SET `permission_key` = 'web.ledger.funds.bank_transactions',
    `page_key` = 'ledger.funds.bank_transactions',
    `page` = '계좌별거래내역',
    `category` = '회계관리 > 자금관리',
    `description` = '계좌별거래내역 화면 조회',
    `updated_at` = CURRENT_TIMESTAMP,
    `updated_by` = 'SYSTEM:MIGRATION'
WHERE `id` = 'f1d17438-c51d-4147-827c-dfd98fa5600e'
  AND `permission_key` = 'web.ledger.funds.account_transactions';

UPDATE `system_page_registry`
SET `page_label` = '자금현황',
    `menu_label` = '자금관리',
    `page_description` = '회계관리 > 자금관리 > 자금현황',
    `breadcrumb` = '회계관리 > 자금관리 > 자금현황',
    `default_route_key` = 'web.ledger.funds.account_balances',
    `default_route_url` = '/ledger/funds',
    `source_description` = '회계관리 > 자금관리 > 자금현황',
    `updated_at` = CURRENT_TIMESTAMP
WHERE `page_key` = 'ledger.funds.account_balances';

UPDATE `system_menu_registry`
SET `menu_label` = '자금현황',
    `default_entry` = '/ledger/funds',
    `updated_at` = CURRENT_TIMESTAMP
WHERE `menu_key` = 'ledger.funds.account_balances';

UPDATE `system_page_registry`
SET `page_label` = '자금일보',
    `page_description` = '회계관리 > 자금관리 > 자금일보',
    `breadcrumb` = '회계관리 > 자금관리 > 자금일보',
    `source_description` = '회계관리 > 자금관리 > 자금일보',
    `updated_at` = CURRENT_TIMESTAMP
WHERE `page_key` = 'ledger.funds.daily_report';

UPDATE `system_menu_registry`
SET `menu_label` = '자금일보',
    `updated_at` = CURRENT_TIMESTAMP
WHERE `menu_key` = 'ledger.funds.daily_report';

UPDATE `auth_permissions`
SET `page` = '지급예정현황',
    `page_key` = 'ledger.funds.payment_schedule',
    `category` = '회계관리 > 자금관리',
    `updated_at` = CURRENT_TIMESTAMP,
    `updated_by` = 'SYSTEM:MIGRATION'
WHERE `permission_key` LIKE 'api.ledger.funds.payment_schedule.%';

DELETE FROM `auth_role_permissions`
WHERE `id` IN (
    'a8654f72-6668-44d0-9198-c48f5e571a3e',
    'ace5e9d1-3b49-409f-9d12-b301f777becc',
    '5eeaa67a-3be4-49c6-a29e-f1fe26eb17d5',
    '8d235167-f49a-4eb7-ae3e-342630f98cab',
    '2535597b-c282-45c9-bc48-eda2bd52b683',
    'dc15f82f-0e8f-40de-a75b-cbf5d6453ce2'
);

DELETE FROM `auth_permissions`
WHERE (`id` = 'c808abc7-c508-481d-9a63-5206d5f43607' AND `permission_key` = 'web.ledger.funds.cash_ledger')
   OR (`id` = '01689b43-e4df-430d-86c6-33ad736a4325' AND `permission_key` = 'web.ledger.funds.deposit_ledger')
   OR (`id` = '52736d14-c85e-4bca-9b66-741e80985bdb' AND `permission_key` = 'web.ledger.funds.payment_info')
   OR (`id` = 'e5a89cff-28ca-42f9-858f-d19426a86d26' AND `permission_key` = 'web.ledger.funds.reconciliation');

DELETE FROM `system_menu_registry`
WHERE `menu_key` IN ('ledger.funds.payment_info', 'ledger.funds.reconciliation');

DELETE FROM `system_page_registry`
WHERE `page_key` IN ('ledger.funds.payment_info', 'ledger.funds.reconciliation');
