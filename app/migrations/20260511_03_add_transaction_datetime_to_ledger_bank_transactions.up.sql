ALTER TABLE `ledger_bank_transactions`
    ADD COLUMN `transaction_datetime` DATETIME NULL DEFAULT NULL COMMENT 'Transaction datetime' AFTER `transaction_date`;

UPDATE `ledger_bank_transactions`
SET `transaction_datetime` = CASE
    WHEN `transaction_date` IS NULL THEN NULL
    WHEN `transaction_time` IS NULL THEN TIMESTAMP(`transaction_date`)
    ELSE TIMESTAMP(`transaction_date`, `transaction_time`)
END
WHERE `transaction_datetime` IS NULL;
