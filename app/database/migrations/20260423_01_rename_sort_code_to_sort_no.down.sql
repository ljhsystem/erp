/*
  Roll back sorting-only sort_no columns to the legacy code name.
  Run the 20260423_02 index rollback before this migration when rolling back.
*/

ALTER TABLE `ledger_transaction_links` RENAME COLUMN `sort_no` TO `code`;
ALTER TABLE `ledger_transaction_items` RENAME COLUMN `sort_no` TO `code`;
ALTER TABLE `ledger_voucher_lines` RENAME COLUMN `sort_no` TO `code`;
ALTER TABLE `ledger_vouchers` RENAME COLUMN `sort_no` TO `code`;
ALTER TABLE `ledger_transactions` RENAME COLUMN `sort_no` TO `code`;
ALTER TABLE `ledger_accounts` RENAME COLUMN `sort_no` TO `code`;

ALTER TABLE `system_coverimage_assets` RENAME COLUMN `sort_no` TO `code`;
ALTER TABLE `system_bank_accounts` RENAME COLUMN `sort_no` TO `code`;
ALTER TABLE `system_cards` RENAME COLUMN `sort_no` TO `code`;
ALTER TABLE `system_projects` RENAME COLUMN `sort_no` TO `code`;
ALTER TABLE `system_clients` RENAME COLUMN `sort_no` TO `code`;

ALTER TABLE `user_employees` RENAME COLUMN `sort_no` TO `code`;
ALTER TABLE `user_positions` RENAME COLUMN `sort_no` TO `code`;
ALTER TABLE `user_departments` RENAME COLUMN `sort_no` TO `code`;

ALTER TABLE `auth_roles` RENAME COLUMN `sort_no` TO `code`;
ALTER TABLE `auth_permissions` RENAME COLUMN `sort_no` TO `code`;
