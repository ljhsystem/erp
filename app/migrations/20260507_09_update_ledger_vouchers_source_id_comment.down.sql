ALTER TABLE `ledger_vouchers`
    MODIFY COLUMN `source_id` VARCHAR(36) NULL DEFAULT NULL
        COMMENT '원본 자료 ID (홈택스/카드/은행 데이터 PK)';
