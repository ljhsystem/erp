ALTER TABLE `ledger_accounts_sub`
  DROP COLUMN `deleted_by`,
  DROP COLUMN `deleted_at`,
  DROP COLUMN `is_active`,
  DROP COLUMN `custom_group_code`,
  DROP COLUMN `ref_id`,
  DROP COLUMN `ref_target`,
  DROP COLUMN `sub_type`;
