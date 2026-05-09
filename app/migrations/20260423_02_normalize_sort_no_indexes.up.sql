/*
  Normalize sort_no after renaming sorting-only code columns.

  Scope:
    - Only tables whose generic code column is used as a display/order sequence.
    - Business identifiers such as account_code, client_code, project_code remain untouched.
    - auth_users.code and auth_role_permissions.code remain untouched.

  Purpose:
    - Keep sort_no as an INT AUTO_INCREMENT sequence.
    - Rename legacy code indexes to sort_no indexes so schema names match the new role.
*/

ALTER TABLE `auth_permissions` RENAME INDEX `uk_code` TO `uk_sort_no`;
ALTER TABLE `auth_permissions` MODIFY COLUMN `sort_no` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `auth_roles` RENAME INDEX `uk_code` TO `uk_sort_no`;
ALTER TABLE `auth_roles` MODIFY COLUMN `sort_no` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `user_departments` RENAME INDEX `uk_code` TO `uk_sort_no`;
ALTER TABLE `user_departments` MODIFY COLUMN `sort_no` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `user_positions` RENAME INDEX `uk_code` TO `uk_sort_no`;
ALTER TABLE `user_positions` MODIFY COLUMN `sort_no` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `user_employees` RENAME INDEX `idx_code` TO `idx_sort_no`;
ALTER TABLE `user_employees` MODIFY COLUMN `sort_no` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `system_clients` RENAME INDEX `idx_code` TO `idx_sort_no`;
ALTER TABLE `system_clients` MODIFY COLUMN `sort_no` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `system_projects` RENAME INDEX `idx_code` TO `idx_sort_no`;
ALTER TABLE `system_projects` MODIFY COLUMN `sort_no` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `system_cards` RENAME INDEX `uk_code` TO `uk_sort_no`;
ALTER TABLE `system_cards` MODIFY COLUMN `sort_no` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `system_bank_accounts` RENAME INDEX `uk_code` TO `uk_sort_no`;
ALTER TABLE `system_bank_accounts` MODIFY COLUMN `sort_no` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `system_coverimage_assets` RENAME INDEX `idx_code` TO `idx_sort_no`;
ALTER TABLE `system_coverimage_assets` MODIFY COLUMN `sort_no` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `ledger_accounts` RENAME INDEX `idx_code` TO `idx_sort_no`;
ALTER TABLE `ledger_accounts` MODIFY COLUMN `sort_no` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `ledger_transactions` RENAME INDEX `uk_ledger_transactions_code` TO `uk_ledger_transactions_sort_no`;
ALTER TABLE `ledger_transactions` MODIFY COLUMN `sort_no` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `ledger_vouchers` RENAME INDEX `uk_code` TO `uk_sort_no`;
ALTER TABLE `ledger_vouchers` MODIFY COLUMN `sort_no` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `ledger_voucher_lines` RENAME INDEX `uk_code` TO `uk_sort_no`;
ALTER TABLE `ledger_voucher_lines` MODIFY COLUMN `sort_no` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `ledger_transaction_items` RENAME INDEX `uk_ledger_transaction_items_code` TO `uk_ledger_transaction_items_sort_no`;
ALTER TABLE `ledger_transaction_items` MODIFY COLUMN `sort_no` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `ledger_transaction_links` RENAME INDEX `uk_ledger_transaction_links_code` TO `uk_ledger_transaction_links_sort_no`;
ALTER TABLE `ledger_transaction_links` MODIFY COLUMN `sort_no` int(11) NOT NULL AUTO_INCREMENT;
