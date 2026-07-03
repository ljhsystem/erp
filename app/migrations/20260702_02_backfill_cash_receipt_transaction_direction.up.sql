-- NOTE:
-- CASH_RECEIPT_PURCHASE and CASH_RECEIPT_SALES are legacy values kept only as
-- compatibility inputs while backfilling old rows. They are not active IMPORT_TYPE
-- codes. The normalized SSOT is IMPORT_TYPE = CASH_RECEIPT, with purchase/sales
-- represented by transaction_direction.
UPDATE `ledger_evidence_cash_receipt` body
LEFT JOIN `ledger_evidence_payloads` payload
    ON payload.`evidence_id` COLLATE utf8mb4_general_ci = body.`id` COLLATE utf8mb4_general_ci
   AND payload.`deleted_at` IS NULL
   AND payload.`evidence_type` IN ('CASH_RECEIPT', 'CASH_RECEIPT_PURCHASE', 'CASH_RECEIPT_SALES')
SET
    body.`transaction_direction` = CASE
        WHEN UPPER(TRIM(COALESCE(JSON_UNQUOTE(JSON_EXTRACT(payload.`mapped_payload_json`, '$.transaction_direction')), ''))) IN ('INCOME', 'SALES', 'SALE', 'SELL', 'OUT_SALE') THEN 'INCOME'
        WHEN UPPER(TRIM(COALESCE(JSON_UNQUOTE(JSON_EXTRACT(payload.`mapped_payload_json`, '$.transaction_direction')), ''))) IN ('EXPENSE', 'PURCHASE', 'BUY', 'IN_PURCHASE') THEN 'EXPENSE'
        WHEN UPPER(COALESCE(payload.`evidence_type`, body.`import_type`, body.`source_type`, '')) = 'CASH_RECEIPT_SALES' THEN 'INCOME'
        WHEN UPPER(COALESCE(body.`source_type`, '')) IN ('CASH_RECEIPT_SALES', 'SALES') THEN 'INCOME'
        ELSE 'EXPENSE'
    END,
    body.`import_type` = 'CASH_RECEIPT'
WHERE body.`deleted_at` IS NULL
  AND body.`transaction_direction` IS NULL;

UPDATE `ledger_evidence_cash_receipt`
SET `import_type` = 'CASH_RECEIPT'
WHERE `deleted_at` IS NULL
  AND (
      `import_type` IS NULL
      OR `import_type` IN ('CASH_RECEIPT_PURCHASE', 'CASH_RECEIPT_SALES')
  );
