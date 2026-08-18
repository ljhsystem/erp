DELETE FROM `auth_role_permissions`
WHERE `id` IN (
    '3f805326-f5c8-4f24-be41-0acc7a80c21f',
    '4d8c9c37-5283-4800-b9db-24323b0764f9',
    '891f3926-93e4-4b5a-b741-eacb14c390af',
    '53b9f846-6d74-46b9-aead-279f48855a91'
);

DELETE FROM `auth_permissions`
WHERE `id` IN (
    '14ae9e36-27ea-4fc3-a702-fa3a58e8af7e',
    'cfd89d7a-580c-4073-b1b6-c6dc386500a7'
);

DELETE FROM `system_user_settings`
WHERE `id` IN (
    '75f7afce-27b0-4baa-8769-cb1b6ae656aa',
    '7f4f03c7-dc60-4b4d-89cc-ff051aca8422'
);

DELETE FROM `system_menu_registry`
WHERE `menu_key` IN (
    'ledger.funds.cash_ledger',
    'ledger.funds.deposit_ledger'
);

DELETE FROM `system_page_registry`
WHERE `page_key` IN (
    'ledger.funds.cash_ledger',
    'ledger.funds.deposit_ledger'
);
