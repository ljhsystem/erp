/*
  Rename sorting-only code columns to sort_no.

  Scope:
    - Generic code columns used as display/order sequence numbers.
    - Business identifiers such as account_code, client_code, and project_code stay unchanged.
    - auth_users.code and auth_role_permissions.code stay unchanged.

  Target tables:
    - auth_permissions
    - auth_roles
    - user_departments
    - user_positions
    - user_employees
    - system_clients
    - system_projects
    - system_cards
    - system_bank_accounts
    - system_coverimage_assets
    - ledger_accounts
    - ledger_transactions
    - ledger_vouchers
    - ledger_voucher_lines
    - ledger_transaction_items
    - ledger_transaction_links
*/

ALTER TABLE `auth_permissions` RENAME COLUMN `code` TO `sort_no`;
ALTER TABLE `auth_roles` RENAME COLUMN `code` TO `sort_no`;

ALTER TABLE `user_departments` RENAME COLUMN `code` TO `sort_no`;
ALTER TABLE `user_positions` RENAME COLUMN `code` TO `sort_no`;
ALTER TABLE `user_employees` RENAME COLUMN `code` TO `sort_no`;

ALTER TABLE `system_clients` RENAME COLUMN `code` TO `sort_no`;
ALTER TABLE `system_projects` RENAME COLUMN `code` TO `sort_no`;
ALTER TABLE `system_cards` RENAME COLUMN `code` TO `sort_no`;
ALTER TABLE `system_bank_accounts` RENAME COLUMN `code` TO `sort_no`;
ALTER TABLE `system_coverimage_assets` RENAME COLUMN `code` TO `sort_no`;

ALTER TABLE `ledger_accounts` RENAME COLUMN `code` TO `sort_no`;
ALTER TABLE `ledger_transactions` RENAME COLUMN `code` TO `sort_no`;
ALTER TABLE `ledger_vouchers` RENAME COLUMN `code` TO `sort_no`;
ALTER TABLE `ledger_voucher_lines` RENAME COLUMN `code` TO `sort_no`;
ALTER TABLE `ledger_transaction_items` RENAME COLUMN `code` TO `sort_no`;
ALTER TABLE `ledger_transaction_links` RENAME COLUMN `code` TO `sort_no`;
