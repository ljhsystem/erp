ALTER TABLE `ledger_bank_transactions`
    ADD COLUMN IF NOT EXISTS `check_bill_amount` DECIMAL(18,2) NULL DEFAULT NULL
    COMMENT '수표어음금액'
    AFTER `balance_amount`;
