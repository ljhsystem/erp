ALTER TABLE `ledger_data_evidences`
    ADD COLUMN IF NOT EXISTS `voucher_id` VARCHAR(36) NULL DEFAULT NULL COMMENT 'Linked voucher ID' AFTER `voucher_generation_type`,
    ADD COLUMN IF NOT EXISTS `voucher_no` VARCHAR(50) NULL DEFAULT NULL COMMENT 'Linked voucher number' AFTER `voucher_id`;

CREATE INDEX IF NOT EXISTS `idx_ledger_data_evidences_voucher_id`
    ON `ledger_data_evidences` (`voucher_id`);

