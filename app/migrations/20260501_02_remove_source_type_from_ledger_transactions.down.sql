ALTER TABLE `ledger_transactions`
    ADD COLUMN IF NOT EXISTS `source_type` VARCHAR(30) NOT NULL DEFAULT 'MANUAL'
        COMMENT '거래 발생 원천 구분 (FIELD, TAX, BANK, CARD, SHOP, MANUAL)'
        COLLATE 'utf8mb4_general_ci'
        AFTER `sort_no`;

CREATE INDEX IF NOT EXISTS `idx_ledger_transactions_source_type`
    ON `ledger_transactions` (`source_type`);
