INSERT INTO `ledger_account_sub_policies` (
  `id`,
  `account_id`,
  `sub_account_type`,
  `is_required`,
  `is_multiple`,
  `sort_order`,
  `custom_group_code`,
  `note`,
  `created_at`,
  `created_by`,
  `updated_at`,
  `updated_by`,
  `deleted_at`
)
SELECT
  UUID() AS `id`,
  a.`id` AS `account_id`,
  'custom' AS `sub_account_type`,
  0 AS `is_required`,
  1 AS `is_multiple`,
  1 AS `sort_order`,
  NULL AS `custom_group_code`,
  'legacy allow_sub_account backfill' AS `note`,
  NOW() AS `created_at`,
  'MIGRATION:20260421_04' AS `created_by`,
  NOW() AS `updated_at`,
  'MIGRATION:20260421_04' AS `updated_by`,
  NULL AS `deleted_at`
FROM `ledger_accounts` a
WHERE a.`allow_sub_account` = 1
  AND NOT EXISTS (
    SELECT 1
    FROM `ledger_account_sub_policies` p
    WHERE p.`account_id` = a.`id`
      AND p.`sub_account_type` = 'custom'
      AND p.`deleted_at` IS NULL
  );

UPDATE `ledger_sub_accounts`
SET `sub_type` = 'custom',
    `ref_type` = NULL,
    `ref_id` = NULL,
    `custom_group_code` = COALESCE(`custom_group_code`, NULL),
    `is_active` = 1,
    `deleted_at` = NULL,
    `deleted_by` = NULL
WHERE `sub_type` <> 'custom'
   OR `sub_type` IS NULL;
