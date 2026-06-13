DROP INDEX IF EXISTS `idx_ledger_data_evidences_voucher_id`
    ON `ledger_data_evidences`;

ALTER TABLE `ledger_data_evidences`
    DROP COLUMN IF EXISTS `voucher_no`,
    DROP COLUMN IF EXISTS `voucher_id`;

