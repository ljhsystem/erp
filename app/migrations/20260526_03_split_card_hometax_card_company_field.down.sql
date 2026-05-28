UPDATE ledger_data_format_columns c
JOIN ledger_data_formats f ON f.id = c.format_id
LEFT JOIN ledger_data_format_columns existing
  ON existing.format_id = c.format_id
 AND existing.system_field_name = 'client_name'
SET c.system_field_name = 'client_name'
WHERE f.data_type = 'CARD_HOMETAX'
  AND REPLACE(TRIM(COALESCE(c.excel_column_name, '')), ' ', '') IN ('카드사', '카드사명', '카드회사')
  AND c.system_field_name = 'source_card_company_name'
  AND existing.id IS NULL;
