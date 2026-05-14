DROP INDEX `idx_ledger_bank_transactions_counterparty_account`
    ON `ledger_bank_transactions`;

ALTER TABLE `ledger_bank_transactions`
    DROP COLUMN `counterparty_bank_name`,
    DROP COLUMN `counterparty_account_number`;
