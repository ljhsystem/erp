DELETE c
FROM ledger_data_format_columns c
JOIN ledger_data_formats f ON f.id = c.format_id
WHERE f.data_type = 'TAX_INVOICE'
  AND c.system_field_name IN ('business_number', 'company_name');

INSERT INTO ledger_data_format_columns
    (id, format_id, excel_column_name, system_field_name, column_order, is_required)
SELECT UUID(), f.id, '거래처명', 'client_name', 2, 0
FROM ledger_data_formats f
WHERE f.data_type = 'TAX_INVOICE'
  AND NOT EXISTS (
      SELECT 1 FROM ledger_data_format_columns c
      WHERE c.format_id = f.id AND c.system_field_name = 'client_name'
  );
