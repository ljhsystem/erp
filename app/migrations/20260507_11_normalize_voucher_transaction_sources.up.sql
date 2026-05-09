UPDATE `ledger_vouchers` v
LEFT JOIN (
    SELECT
        sr.`transaction_id`,
        MIN(sr.`id`) AS `seed_row_id`,
        MIN(sr.`source_type`) AS `import_type`
    FROM `ledger_data_seed_rows` sr
    WHERE sr.`deleted_at` IS NULL
      AND sr.`transaction_id` IS NOT NULL
    GROUP BY sr.`transaction_id`
) seed
    ON seed.`transaction_id` = v.`transaction_id`
SET
    v.`import_type` = COALESCE(v.`import_type`, seed.`import_type`),
    v.`source_id` = seed.`seed_row_id`,
    v.`source_type` = CASE COALESCE(v.`import_type`, seed.`import_type`)
        WHEN 'TAX_INVOICE' THEN 'TAX'
        WHEN 'CASH_RECEIPT' THEN 'TAX'
        WHEN 'CARD_APPROVAL' THEN 'CARD'
        WHEN 'BANK_TRANSACTION' THEN 'BANK'
        WHEN 'SHOPPING_ORDER' THEN 'SHOPPING'
        WHEN 'IMPORT_INVOICE' THEN 'TRADE'
        ELSE v.`source_type`
    END
WHERE v.`transaction_id` IS NOT NULL
  AND seed.`transaction_id` IS NOT NULL;
