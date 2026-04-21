DELETE FROM `ledger_account_sub_policies`
WHERE `created_by` = 'MIGRATION:20260421_04'
  AND `note` = 'legacy allow_sub_account backfill';

UPDATE `ledger_sub_accounts`
SET `sub_type` = 'custom',
    `ref_type` = NULL,
    `ref_id` = NULL,
    `custom_group_code` = NULL,
    `is_active` = 1,
    `deleted_at` = NULL,
    `deleted_by` = NULL;
