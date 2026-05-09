ALTER TABLE `ledger_data_format_columns`
    ADD COLUMN `excel_column_index` INT NULL COMMENT '엑셀 컬럼 위치' AFTER `excel_column_name`;

ALTER TABLE `ledger_data_format_columns`
    MODIFY COLUMN `system_field_name` VARCHAR(100) NULL COMMENT '시스템 필드명';

UPDATE `ledger_data_format_columns`
SET `system_field_name` = NULL
WHERE TRIM(COALESCE(`system_field_name`, '')) = '';

DELETE c1
FROM `ledger_data_format_columns` c1
JOIN `ledger_data_format_columns` c2
  ON c2.`format_id` = c1.`format_id`
 AND c2.`system_field_name` = c1.`system_field_name`
 AND (
      c2.`column_order` < c1.`column_order`
      OR (c2.`column_order` = c1.`column_order` AND c2.`id` < c1.`id`)
 )
WHERE c1.`system_field_name` IS NOT NULL
  AND c1.`system_field_name` <> '';

SET @format_id := '';
SET @row_no := 0;

UPDATE `ledger_data_format_columns` c
JOIN (
    SELECT
        ordered.`id`,
        @row_no := IF(@format_id = ordered.`format_id`, @row_no + 1, 1) AS `new_order`,
        @format_id := ordered.`format_id` AS `_format_marker`
    FROM (
        SELECT `id`, `format_id`
        FROM `ledger_data_format_columns`
        ORDER BY `format_id`, `column_order`, `id`
    ) ordered
) seq ON seq.`id` = c.`id`
SET c.`column_order` = seq.`new_order`,
    c.`excel_column_index` = seq.`new_order`;

ALTER TABLE `ledger_data_format_columns`
    ADD UNIQUE KEY `uk_format_system_field` (`format_id`, `system_field_name`);