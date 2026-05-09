ALTER TABLE `ledger_sub_accounts`
  ADD COLUMN `sub_type` varchar(30) NOT NULL DEFAULT 'custom' AFTER `sub_name`,
  ADD COLUMN `ref_type` varchar(30) DEFAULT NULL AFTER `sub_type`,
  ADD COLUMN `ref_id` char(36) DEFAULT NULL AFTER `ref_type`,
  ADD COLUMN `custom_group_code` varchar(50) DEFAULT NULL AFTER `ref_id`,
  ADD COLUMN `is_active` tinyint(1) NOT NULL DEFAULT '1' AFTER `custom_group_code`,
  ADD COLUMN `deleted_at` datetime DEFAULT NULL AFTER `updated_by`,
  ADD COLUMN `deleted_by` varchar(100) DEFAULT NULL AFTER `deleted_at`;
