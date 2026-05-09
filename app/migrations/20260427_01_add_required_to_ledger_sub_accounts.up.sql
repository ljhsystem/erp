ALTER TABLE `ledger_sub_accounts`
  ADD COLUMN `is_required` tinyint(1) NOT NULL DEFAULT '0' AFTER `custom_group_code`;
