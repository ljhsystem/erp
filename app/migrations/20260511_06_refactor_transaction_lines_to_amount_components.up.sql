ALTER TABLE `ledger_transactions`
    ADD COLUMN IF NOT EXISTS `base_amount` DECIMAL(18,2) NOT NULL DEFAULT 0 COMMENT 'ITEM line amount sum'
        AFTER `exchange_rate`,
    ADD COLUMN IF NOT EXISTS `adjustment_amount` DECIMAL(18,2) NOT NULL DEFAULT 0 COMMENT 'Non-ITEM adjustment line amount sum'
        AFTER `base_amount`;

UPDATE `ledger_transactions`
SET `base_amount` = COALESCE(NULLIF(`base_amount`, 0), COALESCE(`supply_amount`, 0)),
    `adjustment_amount` = COALESCE(NULLIF(`adjustment_amount`, 0), COALESCE(`total_amount`, 0) - COALESCE(`supply_amount`, 0))
WHERE `deleted_at` IS NULL;

ALTER TABLE `ledger_transaction_lines`
    ADD COLUMN IF NOT EXISTS `line_type` VARCHAR(40) NOT NULL DEFAULT 'ITEM' COMMENT 'Amount component type'
        AFTER `transaction_id`,
    ADD COLUMN IF NOT EXISTS `amount` DECIMAL(18,2) NOT NULL DEFAULT 0 COMMENT 'Line amount component'
        AFTER `foreign_amount`;

UPDATE `ledger_transaction_lines`
SET `line_type` = COALESCE(NULLIF(`line_type`, ''), 'ITEM'),
    `amount` = CASE
        WHEN COALESCE(`amount`, 0) <> 0 THEN `amount`
        WHEN COALESCE(`total_amount`, 0) <> 0 THEN `total_amount`
        ELSE COALESCE(`supply_amount`, 0) + COALESCE(`vat_amount`, 0)
    END
WHERE `deleted_at` IS NULL;

CREATE INDEX IF NOT EXISTS `idx_ledger_transaction_lines_type`
    ON `ledger_transaction_lines` (`transaction_id`, `line_type`);
