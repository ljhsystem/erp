SET @add_transaction_settlement_amount := (
    SELECT IF(
        EXISTS(
            SELECT 1
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'ledger_transactions'
              AND COLUMN_NAME = 'transaction_settlement_amount'
        ),
        'SELECT 1',
        'ALTER TABLE ledger_transactions ADD COLUMN transaction_settlement_amount DECIMAL(18,2) NOT NULL DEFAULT 0.00 AFTER transaction_supply_amount'
    )
);
PREPARE stmt_add_transaction_settlement_amount FROM @add_transaction_settlement_amount;
EXECUTE stmt_add_transaction_settlement_amount;
DEALLOCATE PREPARE stmt_add_transaction_settlement_amount;

SET @add_transaction_final_amount := (
    SELECT IF(
        EXISTS(
            SELECT 1
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'ledger_transactions'
              AND COLUMN_NAME = 'transaction_final_amount'
        ),
        'SELECT 1',
        'ALTER TABLE ledger_transactions ADD COLUMN transaction_final_amount DECIMAL(18,2) NOT NULL DEFAULT 0.00 AFTER transaction_settlement_amount'
    )
);
PREPARE stmt_add_transaction_final_amount FROM @add_transaction_final_amount;
EXECUTE stmt_add_transaction_final_amount;
DEALLOCATE PREPARE stmt_add_transaction_final_amount;

CREATE TABLE IF NOT EXISTS `ledger_transaction_settlements` (
    `id` CHAR(36) NOT NULL,
    `sort_no` INT NOT NULL DEFAULT 1,
    `transaction_id` CHAR(36) NOT NULL,
    `transaction_item_id` CHAR(36) NULL,
    `settlement_type` VARCHAR(50) NOT NULL,
    `amount_sign` VARCHAR(20) NOT NULL,
    `amount` DECIMAL(18,2) NOT NULL DEFAULT 0.00,
    `currency` CHAR(3) NOT NULL DEFAULT 'KRW',
    `exchange_rate` DECIMAL(18,6) NULL,
    `settlement_description` VARCHAR(255) NULL,
    `meta_json` JSON NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `created_by` VARCHAR(100) NULL,
    `updated_at` DATETIME NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `updated_by` VARCHAR(100) NULL,
    `deleted_at` DATETIME NULL,
    `deleted_by` VARCHAR(100) NULL,
    PRIMARY KEY (`id`),
    KEY `idx_transaction_settlements_transaction` (`transaction_id`, `deleted_at`, `sort_no`),
    KEY `idx_transaction_settlements_item` (`transaction_item_id`),
    KEY `idx_transaction_settlements_type` (`settlement_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
