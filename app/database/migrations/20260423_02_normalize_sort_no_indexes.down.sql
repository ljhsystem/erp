/*
  Roll back sort_no index names to the legacy code index names.
  This file intentionally leaves AUTO_INCREMENT intact because the legacy
  code columns also acted as sequence/order columns in most target tables.
*/

ALTER TABLE `ledger_transaction_links` RENAME INDEX `uk_ledger_transaction_links_sort_no` TO `uk_ledger_transaction_links_code`;
ALTER TABLE `ledger_transaction_items` RENAME INDEX `uk_ledger_transaction_items_sort_no` TO `uk_ledger_transaction_items_code`;
ALTER TABLE `ledger_voucher_lines` RENAME INDEX `uk_sort_no` TO `uk_code`;
ALTER TABLE `ledger_vouchers` RENAME INDEX `uk_sort_no` TO `uk_code`;
ALTER TABLE `ledger_transactions` RENAME INDEX `uk_ledger_transactions_sort_no` TO `uk_ledger_transactions_code`;
ALTER TABLE `ledger_accounts` RENAME INDEX `idx_sort_no` TO `idx_code`;
ALTER TABLE `system_coverimage_assets` RENAME INDEX `idx_sort_no` TO `idx_code`;
ALTER TABLE `system_bank_accounts` RENAME INDEX `uk_sort_no` TO `uk_code`;
ALTER TABLE `system_cards` RENAME INDEX `uk_sort_no` TO `uk_code`;
ALTER TABLE `system_projects` RENAME INDEX `idx_sort_no` TO `idx_code`;
ALTER TABLE `system_clients` RENAME INDEX `idx_sort_no` TO `idx_code`;
ALTER TABLE `user_employees` RENAME INDEX `idx_sort_no` TO `idx_code`;
ALTER TABLE `user_positions` RENAME INDEX `uk_sort_no` TO `uk_code`;
ALTER TABLE `user_departments` RENAME INDEX `uk_sort_no` TO `uk_code`;
ALTER TABLE `auth_roles` RENAME INDEX `uk_sort_no` TO `uk_code`;
ALTER TABLE `auth_permissions` RENAME INDEX `uk_sort_no` TO `uk_code`;
