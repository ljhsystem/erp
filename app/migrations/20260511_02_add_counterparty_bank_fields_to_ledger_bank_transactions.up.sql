ALTER TABLE `ledger_bank_transactions`
    ADD COLUMN `counterparty_account_number` VARCHAR(100) NULL DEFAULT NULL COMMENT 'Counterparty account number' AFTER `counterparty_name`,
    ADD COLUMN `counterparty_bank_name` VARCHAR(100) NULL DEFAULT NULL COMMENT 'Counterparty bank name' AFTER `counterparty_account_number`;

CREATE INDEX `idx_ledger_bank_transactions_counterparty_account`
    ON `ledger_bank_transactions` (`counterparty_account_number`);
