ALTER TABLE `ledger_data_formats`
    MODIFY COLUMN `data_type` VARCHAR(50) NOT NULL COMMENT '자료유형 (예: TAX_INVOICE, CASH_RECEIPT, BANK_TRANSACTION). 현금영수증 매입/매출은 IMPORT_TYPE이 아니라 transaction_direction으로 구분';
