ALTER TABLE `ledger_bank_transactions`
    MODIFY COLUMN `balance_amount` DECIMAL(18,2) NULL DEFAULT NULL COMMENT 'Actual bank balance after transaction';

ALTER TABLE `ledger_bank_transactions`
    ADD COLUMN IF NOT EXISTS `balance_status` VARCHAR(20) NULL DEFAULT 'EMPTY' COMMENT 'ACTUAL, EMPTY, ESTIMATED, INVALID' AFTER `balance_amount`;

UPDATE `ledger_bank_transactions`
SET `balance_status` = CASE
    WHEN `balance_amount` IS NULL THEN 'EMPTY'
    ELSE 'ACTUAL'
END
WHERE `balance_status` IS NULL OR `balance_status` = '';
