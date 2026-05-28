DROP INDEX IF EXISTS `idx_ledger_data_evidences_status_sort`
    ON `ledger_data_evidences`;

DROP INDEX IF EXISTS `idx_ledger_data_evidences_create_sort`
    ON `ledger_data_evidences`;

ALTER TABLE `ledger_data_evidences`
    DROP COLUMN IF EXISTS `status_sort_no`,
    DROP COLUMN IF EXISTS `create_sort_no`;
