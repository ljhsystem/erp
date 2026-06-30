DROP TABLE IF EXISTS `ledger_transaction_settlements`;

SET @drop_transaction_final_amount := (
    SELECT IF(
        EXISTS(
            SELECT 1
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'ledger_transactions'
              AND COLUMN_NAME = 'transaction_final_amount'
        ),
        'ALTER TABLE ledger_transactions DROP COLUMN transaction_final_amount',
        'SELECT 1'
    )
);
PREPARE stmt_drop_transaction_final_amount FROM @drop_transaction_final_amount;
EXECUTE stmt_drop_transaction_final_amount;
DEALLOCATE PREPARE stmt_drop_transaction_final_amount;

SET @drop_transaction_settlement_amount := (
    SELECT IF(
        EXISTS(
            SELECT 1
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'ledger_transactions'
              AND COLUMN_NAME = 'transaction_settlement_amount'
        ),
        'ALTER TABLE ledger_transactions DROP COLUMN transaction_settlement_amount',
        'SELECT 1'
    )
);
PREPARE stmt_drop_transaction_settlement_amount FROM @drop_transaction_settlement_amount;
EXECUTE stmt_drop_transaction_settlement_amount;
DEALLOCATE PREPARE stmt_drop_transaction_settlement_amount;
