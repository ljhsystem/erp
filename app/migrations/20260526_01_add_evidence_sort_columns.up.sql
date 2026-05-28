ALTER TABLE `ledger_data_evidences`
    ADD COLUMN IF NOT EXISTS `create_sort_no` INT UNSIGNED NULL DEFAULT NULL COMMENT 'Create center sequence',
    ADD COLUMN IF NOT EXISTS `status_sort_no` INT UNSIGNED NULL DEFAULT NULL COMMENT 'Evidence type sequence';

UPDATE `ledger_data_evidences`
SET `create_sort_no` = CAST(JSON_UNQUOTE(JSON_EXTRACT(`mapped_payload_json`, '$._create_sort_no')) AS UNSIGNED)
WHERE (`create_sort_no` IS NULL OR `create_sort_no` < 1)
  AND JSON_EXTRACT(`mapped_payload_json`, '$._create_sort_no') IS NOT NULL;

UPDATE `ledger_data_evidences`
SET `status_sort_no` = CAST(JSON_UNQUOTE(JSON_EXTRACT(`mapped_payload_json`, '$._status_sort_no')) AS UNSIGNED)
WHERE (`status_sort_no` IS NULL OR `status_sort_no` < 1)
  AND JSON_EXTRACT(`mapped_payload_json`, '$._status_sort_no') IS NOT NULL;

CREATE INDEX IF NOT EXISTS `idx_ledger_data_evidences_create_sort`
    ON `ledger_data_evidences` (`deleted_at`, `create_sort_no`, `id`);

CREATE INDEX IF NOT EXISTS `idx_ledger_data_evidences_status_sort`
    ON `ledger_data_evidences` (`source_type`, `deleted_at`, `status_sort_no`, `id`);
