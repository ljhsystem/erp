ALTER TABLE `ledger_data_evidences`
    ADD COLUMN IF NOT EXISTS `voucher_generation_type` VARCHAR(20) NOT NULL DEFAULT 'NORMAL' COMMENT 'Voucher generation type: NORMAL or GROUP' AFTER `voucher_status`;

CREATE INDEX IF NOT EXISTS `idx_ledger_data_evidences_voucher_generation_type`
    ON `ledger_data_evidences` (`voucher_generation_type`);

