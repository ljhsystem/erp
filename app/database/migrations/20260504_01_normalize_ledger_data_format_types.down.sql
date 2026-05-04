UPDATE ledger_data_formats
SET data_type = 'TAX'
WHERE data_type = 'TAX_INVOICE';

UPDATE ledger_data_formats
SET data_type = 'CARD'
WHERE data_type = 'CARD_PURCHASE';
