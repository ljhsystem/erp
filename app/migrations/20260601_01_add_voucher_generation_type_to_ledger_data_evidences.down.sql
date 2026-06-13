DROP INDEX IF EXISTS `idx_ledger_data_evidences_voucher_generation_type`
    ON `ledger_data_evidences`;

ALTER TABLE `ledger_data_evidences`
    DROP COLUMN IF EXISTS `voucher_generation_type`;

