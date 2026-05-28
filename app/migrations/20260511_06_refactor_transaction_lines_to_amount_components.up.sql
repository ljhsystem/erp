ALTER TABLE `ledger_transactions`
    ADD COLUMN IF NOT EXISTS `adjustment_amount` DECIMAL(18,2) NOT NULL DEFAULT 0 COMMENT 'Adjustment amount sum from transaction lines'
        AFTER `supply_amount`;

UPDATE `ledger_transactions`
SET `adjustment_amount` = COALESCE(NULLIF(`adjustment_amount`, 0), COALESCE(`total_amount`, 0) - COALESCE(`supply_amount`, 0))
WHERE `deleted_at` IS NULL;

ALTER TABLE `ledger_transaction_lines`
    ADD COLUMN IF NOT EXISTS `line_type` VARCHAR(40) NOT NULL DEFAULT 'GOODS' COMMENT 'Business amount component type'
        AFTER `processing_item_id`,
    ADD COLUMN IF NOT EXISTS `currency_code` VARCHAR(10) NULL DEFAULT NULL COMMENT 'Actual line currency'
        AFTER `unit_price`,
    ADD COLUMN IF NOT EXISTS `exchange_rate` DECIMAL(18,6) NULL DEFAULT NULL COMMENT 'Actual line exchange rate'
        AFTER `currency_code`,
    ADD COLUMN IF NOT EXISTS `amount` DECIMAL(18,2) NOT NULL DEFAULT 0 COMMENT 'KRW line amount'
        AFTER `foreign_amount`;

UPDATE `ledger_transaction_lines` l
INNER JOIN `ledger_transactions` t
    ON t.id = l.transaction_id
SET l.`line_type` = CASE
        WHEN UPPER(COALESCE(NULLIF(l.`line_type`, ''), 'GOODS')) = 'ITEM' THEN 'GOODS'
        WHEN UPPER(COALESCE(NULLIF(l.`line_type`, ''), 'GOODS')) IN ('DEBIT', 'CREDIT', 'CASH', 'SALES', 'COGS') THEN 'GOODS'
        ELSE UPPER(COALESCE(NULLIF(l.`line_type`, ''), 'GOODS'))
    END,
    l.`currency_code` = COALESCE(NULLIF(l.`currency_code`, ''), NULLIF(t.`currency`, ''), 'KRW'),
    l.`exchange_rate` = COALESCE(NULLIF(l.`exchange_rate`, 0), NULLIF(t.`exchange_rate`, 0)),
    l.`amount` = CASE
        WHEN COALESCE(l.`amount`, 0) <> 0 THEN l.`amount`
        WHEN COALESCE(l.`total_amount`, 0) <> 0 THEN l.`total_amount`
        ELSE COALESCE(l.`supply_amount`, 0) + COALESCE(l.`vat_amount`, 0)
    END
WHERE l.`deleted_at` IS NULL;

UPDATE `ledger_transactions` t
LEFT JOIN (
    SELECT
        transaction_id,
        SUM(CASE WHEN line_type IN ('GOODS', 'ITEM', 'SERVICE') THEN amount ELSE 0 END) AS base_sum,
        SUM(CASE WHEN line_type NOT IN ('GOODS', 'ITEM', 'SERVICE') THEN amount ELSE 0 END) AS adjustment_sum,
        SUM(amount) AS total_sum
    FROM `ledger_transaction_lines`
    WHERE `deleted_at` IS NULL
    GROUP BY transaction_id
) s
    ON s.transaction_id = t.id
SET t.`supply_amount` = COALESCE(s.base_sum, t.`supply_amount`, 0),
    t.`adjustment_amount` = COALESCE(s.adjustment_sum, t.`adjustment_amount`, 0),
    t.`total_amount` = COALESCE(s.total_sum, t.`total_amount`, 0)
WHERE t.`deleted_at` IS NULL;

CREATE INDEX IF NOT EXISTS `idx_ledger_transaction_lines_type`
    ON `ledger_transaction_lines` (`transaction_id`, `line_type`);
