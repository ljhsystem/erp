ALTER TABLE `ledger_vouchers`
    ADD COLUMN IF NOT EXISTS `import_type` VARCHAR(30) NULL DEFAULT NULL
        COMMENT 'Original import data type, e.g. TAX_INVOICE, CASH_RECEIPT, CARD_APPROVAL'
        AFTER `source_type`;

CREATE INDEX IF NOT EXISTS `idx_ledger_vouchers_import_type`
    ON `ledger_vouchers` (`import_type`);

UPDATE `ledger_vouchers` v
LEFT JOIN (
    SELECT
        l.`voucher_id`,
        MIN(sr.`source_type`) AS `import_type`
    FROM `ledger_transaction_links` l
    INNER JOIN `ledger_data_seed_rows` sr
        ON sr.`transaction_id` = l.`transaction_id`
       AND sr.`deleted_at` IS NULL
    WHERE l.`deleted_at` IS NULL
      AND l.`is_active` = 1
    GROUP BY l.`voucher_id`
) linked_seed
    ON linked_seed.`voucher_id` = v.`id`
LEFT JOIN (
    SELECT
        `transaction_id`,
        MIN(`source_type`) AS `import_type`
    FROM `ledger_data_seed_rows`
    WHERE `deleted_at` IS NULL
      AND `transaction_id` IS NOT NULL
    GROUP BY `transaction_id`
) source_seed
    ON source_seed.`transaction_id` = v.`source_id`
SET v.`import_type` = COALESCE(linked_seed.`import_type`, source_seed.`import_type`, v.`import_type`);
