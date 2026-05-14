ALTER TABLE `ledger_data_formats`
    MODIFY COLUMN `data_type` VARCHAR(20) NOT NULL COMMENT '자료유형 (TAX, BANK, CARD)';

