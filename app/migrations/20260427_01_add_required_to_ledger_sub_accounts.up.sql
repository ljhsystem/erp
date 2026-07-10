ALTER TABLE `ledger_accounts_sub`
  ADD COLUMN `is_required` tinyint(1) NOT NULL DEFAULT '0' AFTER `custom_group_code`;
