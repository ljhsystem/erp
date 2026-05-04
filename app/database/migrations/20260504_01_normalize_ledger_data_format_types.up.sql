UPDATE ledger_data_formats
SET data_type = 'TAX_INVOICE'
WHERE data_type IN ('DATA', 'TAX');

UPDATE ledger_data_formats
SET data_type = 'CARD_PURCHASE'
WHERE data_type = 'CARD';
