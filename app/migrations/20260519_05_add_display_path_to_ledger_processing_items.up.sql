ALTER TABLE `ledger_processing_items`
    ADD COLUMN `display_path` VARCHAR(80) NULL DEFAULT NULL
        COMMENT '사용자에게 표시되는 workflow lineage 번호(예: 7-1-2). row 정렬 변경과 무관하게 item 계보 추적에 사용'
        COLLATE 'utf8mb4_general_ci'
        AFTER `lineage_root_id`;

CREATE INDEX `idx_ledger_processing_items_display_path`
    ON `ledger_processing_items` (`source_table`, `source_id`, `display_path`);

UPDATE `ledger_processing_items` i
LEFT JOIN `ledger_data_evidences` e
       ON i.`source_table` = 'ledger_data_evidences'
      AND i.`source_id` = e.`id`
SET i.`display_path` = COALESCE(NULLIF(i.`display_path`, ''), CAST(COALESCE(e.`id`, i.`sort_no`, 1) AS CHAR))
WHERE i.`parent_item_id` IS NULL
  AND (i.`display_path` IS NULL OR i.`display_path` = '');

UPDATE `ledger_processing_items` i
LEFT JOIN `ledger_bank_transactions` b
       ON i.`source_table` = 'ledger_bank_transactions'
      AND i.`source_id` = b.`id`
SET i.`display_path` = COALESCE(NULLIF(i.`display_path`, ''), CAST(COALESCE(b.`sort_no`, i.`sort_no`, 1) AS CHAR))
WHERE i.`parent_item_id` IS NULL
  AND (i.`display_path` IS NULL OR i.`display_path` = '');
