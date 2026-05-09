ALTER TABLE `ledger_vouchers`
    MODIFY COLUMN `source_id` VARCHAR(36) NULL DEFAULT NULL
        COMMENT '생성 원본 객체 ID (Seed/거래/입출금 등)';
