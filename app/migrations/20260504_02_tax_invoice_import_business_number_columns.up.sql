DELETE c
FROM ledger_data_format_columns c
JOIN ledger_data_formats f ON f.id = c.format_id
WHERE f.data_type = 'TAX_INVOICE'
  AND c.system_field_name = 'client_name';

UPDATE ledger_data_format_columns c
JOIN ledger_data_formats f ON f.id = c.format_id
SET c.excel_column_name = '작성일자', c.column_order = 1, c.is_required = 1
WHERE f.data_type = 'TAX_INVOICE'
  AND c.system_field_name = 'transaction_date';

INSERT INTO ledger_data_format_columns
    (id, format_id, excel_column_name, system_field_name, column_order, is_required)
SELECT UUID(), f.id, '사업자등록번호', 'business_number', 2, 1
FROM ledger_data_formats f
WHERE f.data_type = 'TAX_INVOICE'
  AND NOT EXISTS (
      SELECT 1 FROM ledger_data_format_columns c
      WHERE c.format_id = f.id AND c.system_field_name = 'business_number'
  );

INSERT INTO ledger_data_format_columns
    (id, format_id, excel_column_name, system_field_name, column_order, is_required)
SELECT UUID(), f.id, '상호', 'company_name', 3, 1
FROM ledger_data_formats f
WHERE f.data_type = 'TAX_INVOICE'
  AND NOT EXISTS (
      SELECT 1 FROM ledger_data_format_columns c
      WHERE c.format_id = f.id AND c.system_field_name = 'company_name'
  );

UPDATE ledger_data_format_columns c
JOIN ledger_data_formats f ON f.id = c.format_id
SET c.excel_column_name = '사업자등록번호', c.column_order = 2, c.is_required = 1
WHERE f.data_type = 'TAX_INVOICE'
  AND c.system_field_name = 'business_number';

UPDATE ledger_data_format_columns c
JOIN ledger_data_formats f ON f.id = c.format_id
SET c.excel_column_name = '상호', c.column_order = 3, c.is_required = 1
WHERE f.data_type = 'TAX_INVOICE'
  AND c.system_field_name = 'company_name';
