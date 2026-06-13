ALTER TABLE `ledger_data_evidences`
    ADD COLUMN IF NOT EXISTS `create_sort_no` INT UNSIGNED NULL DEFAULT NULL COMMENT 'Create center sequence',
    ADD COLUMN IF NOT EXISTS `status_sort_no` INT UNSIGNED NULL DEFAULT NULL COMMENT 'Evidence type sequence';

UPDATE `ledger_data_evidences` e
JOIN `ledger_evidence_payloads` p
  ON p.`evidence_type` = e.`source_type` COLLATE utf8mb4_unicode_ci
 AND p.`evidence_id` = e.`id` COLLATE utf8mb4_unicode_ci
SET e.`create_sort_no` = CAST(JSON_UNQUOTE(JSON_EXTRACT(p.`mapped_payload_json`, '$._create_sort_no')) AS UNSIGNED)
WHERE (e.`create_sort_no` IS NULL OR e.`create_sort_no` < 1)
  AND JSON_EXTRACT(p.`mapped_payload_json`, '$._create_sort_no') IS NOT NULL;

UPDATE `ledger_data_evidences` e
JOIN `ledger_evidence_payloads` p
  ON p.`evidence_type` = e.`source_type` COLLATE utf8mb4_unicode_ci
 AND p.`evidence_id` = e.`id` COLLATE utf8mb4_unicode_ci
SET e.`status_sort_no` = CAST(JSON_UNQUOTE(JSON_EXTRACT(p.`mapped_payload_json`, '$._status_sort_no')) AS UNSIGNED)
WHERE (e.`status_sort_no` IS NULL OR e.`status_sort_no` < 1)
  AND JSON_EXTRACT(p.`mapped_payload_json`, '$._status_sort_no') IS NOT NULL;

CREATE INDEX IF NOT EXISTS `idx_ledger_data_evidences_create_sort`
    ON `ledger_data_evidences` (`deleted_at`, `create_sort_no`, `id`);

CREATE INDEX IF NOT EXISTS `idx_ledger_data_evidences_status_sort`
    ON `ledger_data_evidences` (`source_type`, `deleted_at`, `status_sort_no`, `id`);
