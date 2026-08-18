INSERT IGNORE INTO `system_page_registry`
(`page_key`,`module_key`,`module_label`,`menu_key`,`menu_label`,`page_label`,`page_description`,`breadcrumb`,`default_route_key`,`default_route_url`,`source_description`,`is_active`,`created_at`,`updated_at`)
VALUES
('ledger.funds.cash_ledger','ledger','회계관리','ledger.funds','자금관리','현금출납장','회계관리 > 자금관리 > 현금출납장','회계관리 > 자금관리 > 현금출납장','web.ledger.funds.cash_ledger',NULL,'회계관리 > 자금관리 > 현금출납장',1,'2026-06-08 20:07:19','2026-06-08 20:07:19'),
('ledger.funds.deposit_ledger','ledger','회계관리','ledger.funds','자금관리','예금출납장','회계관리 > 자금관리 > 예금출납장','회계관리 > 자금관리 > 예금출납장','web.ledger.funds.deposit_ledger',NULL,'회계관리 > 자금관리 > 예금출납장',1,'2026-06-08 20:07:19','2026-06-08 20:07:19');

INSERT IGNORE INTO `system_menu_registry`
(`menu_key`,`page_key`,`module_key`,`menu_label`,`module_order`,`menu_order`,`page_order`,`menu_icon`,`default_entry`,`is_menu`,`visible_in_sidebar`,`visible_in_settings`,`visible_in_sitemap`,`visible_in_navbar`,`is_active`,`created_at`,`updated_at`)
VALUES
('ledger.funds.cash_ledger','ledger.funds.cash_ledger','ledger','현금출납장',30,80,40,'bi-cash-stack','/ledger/funds/cash-ledger',1,1,0,1,0,1,'2026-06-08 21:12:54','2026-06-08 21:12:54'),
('ledger.funds.deposit_ledger','ledger.funds.deposit_ledger','ledger','예금출납장',30,80,30,'bi-journal-check','/ledger/funds/deposit-ledger',1,1,0,1,0,1,'2026-06-08 21:12:54','2026-06-08 21:12:54');

INSERT IGNORE INTO `system_user_settings`
(`id`,`user_id`,`page_key`,`setting_type`,`settings_json`,`description`,`created_at`,`created_by`,`updated_at`,`updated_by`)
VALUES
('75f7afce-27b0-4baa-8769-cb1b6ae656aa','f113b666-ff40-4f93-a7e7-8cea4cdc9c28','ledger.funds.deposit_ledger','TABLE','{"version":5,"visibleColumns":["row_no","bank_account_name","transaction_datetime","transaction_direction","description","client_name","project_name","deposit_amount","withdraw_amount","running_balance","counterparty_account_number","counterparty_bank_name","original_transaction_type","check_bill_amount","cms_code","voucher_no","voucher_status","link_status","memo"],"columnOrder":["row_no","bank_account_name","transaction_datetime","transaction_direction","description","client_name","project_name","deposit_amount","withdraw_amount","running_balance","counterparty_account_number","counterparty_bank_name","original_transaction_type","check_bill_amount","cms_code","voucher_no","voucher_status","link_status","memo"],"requiredColumns":[],"columnDisplayName":{"row_no":"순번","bank_account_name":"계좌","transaction_datetime":"거래일시","transaction_direction":"거래구분","description":"거래내용","client_name":"거래처","project_name":"프로젝트","deposit_amount":"입금","withdraw_amount":"출금","running_balance":"거래후잔액","counterparty_account_number":"상대계좌번호","counterparty_bank_name":"상대은행","original_transaction_type":"원본거래구분","check_bill_amount":"수표어음금액","cms_code":"CMS코드","voucher_no":"전표번호","voucher_status":"전표상태","link_status":"연결상태","memo":"메모"},"columnRequirementPolicy":{"row_no":"none","bank_account_name":"none","transaction_datetime":"none","transaction_direction":"none","description":"none","client_name":"none","project_name":"none","deposit_amount":"none","withdraw_amount":"none","running_balance":"none","counterparty_account_number":"none","counterparty_bank_name":"none","original_transaction_type":"none","check_bill_amount":"none","cms_code":"none","voucher_no":"none","voucher_status":"none","link_status":"none","memo":"none"},"updatedAt":""}','회계관리 > 자금관리 > 예금출납장','2026-07-26 16:58:45','f113b666-ff40-4f93-a7e7-8cea4cdc9c28','2026-07-26 16:58:45','f113b666-ff40-4f93-a7e7-8cea4cdc9c28'),
('7f4f03c7-dc60-4b4d-89cc-ff051aca8422','f113b666-ff40-4f93-a7e7-8cea4cdc9c28','ledger.funds.deposit_ledger','VIEW','{"version":5,"columnWidths":[],"currentPage":0,"searchFormExpanded":null,"searchFormState":null,"sortSettings":[],"pageLength":100,"updatedAt":""}','회계관리 > 자금관리 > 예금출납장','2026-07-26 16:58:45','f113b666-ff40-4f93-a7e7-8cea4cdc9c28','2026-07-26 16:58:45','f113b666-ff40-4f93-a7e7-8cea4cdc9c28');

INSERT IGNORE INTO `auth_permissions`
(`id`,`sort_no`,`page`,`permission_source`,`category`,`permission_key`,`permission_name`,`description`,`page_key`,`is_active`,`created_at`,`created_by`,`updated_at`,`updated_by`)
VALUES
('14ae9e36-27ea-4fc3-a702-fa3a58e8af7e',408,'현금출납장','web','회계관리 > 자금관리','web.ledger.funds.cash_ledger','화면조회','현금출납장 화면 조회','ledger.funds.cash_ledger',1,'2026-05-22 11:58:57','SYSTEM:자동','2026-07-26 13:59:38','SYSTEM:자동'),
('cfd89d7a-580c-4073-b1b6-c6dc386500a7',410,'예금출납장','web','회계관리 > 자금관리','web.ledger.funds.deposit_ledger','화면조회','예금출납장 화면 조회','ledger.funds.deposit_ledger',1,'2026-05-22 11:58:57','SYSTEM:자동','2026-07-26 13:59:38','SYSTEM:자동');

INSERT IGNORE INTO `auth_role_permissions`
(`id`,`role_id`,`permission_id`,`created_at`,`created_by`)
VALUES
('3f805326-f5c8-4f24-be41-0acc7a80c21f','08361618-c06d-4fd5-b18d-be61e1b1058e','14ae9e36-27ea-4fc3-a702-fa3a58e8af7e','2026-07-25 15:44:02','USER:f113b666-ff40-4f93-a7e7-8cea4cdc9c28'),
('4d8c9c37-5283-4800-b9db-24323b0764f9','c1c90ecf-1a44-470c-8d9c-4d6e671cdcfa','14ae9e36-27ea-4fc3-a702-fa3a58e8af7e','2026-06-08 16:41:49','USER:f113b666-ff40-4f93-a7e7-8cea4cdc9c28'),
('891f3926-93e4-4b5a-b741-eacb14c390af','08361618-c06d-4fd5-b18d-be61e1b1058e','cfd89d7a-580c-4073-b1b6-c6dc386500a7','2026-07-25 15:44:03','USER:f113b666-ff40-4f93-a7e7-8cea4cdc9c28'),
('53b9f846-6d74-46b9-aead-279f48855a91','c1c90ecf-1a44-470c-8d9c-4d6e671cdcfa','cfd89d7a-580c-4073-b1b6-c6dc386500a7','2026-06-08 16:41:48','USER:f113b666-ff40-4f93-a7e7-8cea4cdc9c28');
