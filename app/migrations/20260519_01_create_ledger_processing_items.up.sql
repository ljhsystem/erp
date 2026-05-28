CREATE TABLE IF NOT EXISTS `ledger_processing_items` (
    `id` VARCHAR(36) NOT NULL,
    `source_table` VARCHAR(100) NOT NULL,
    `source_id` VARCHAR(36) NOT NULL,
    `source_type` VARCHAR(30) NOT NULL,
    `parent_item_id` VARCHAR(36) NULL DEFAULT NULL,
    `source_item_id` VARCHAR(36) NULL DEFAULT NULL,
    `split_group_id` VARCHAR(36) NULL DEFAULT NULL,
    `merge_group_id` VARCHAR(36) NULL DEFAULT NULL,
    `sort_no` INT NOT NULL DEFAULT 1,
    `item_type` VARCHAR(30) NOT NULL DEFAULT 'DEFAULT',
    `line_type` VARCHAR(30) NULL DEFAULT NULL,
    `item_status` VARCHAR(30) NOT NULL DEFAULT 'ACTIVE',
    `transaction_status` VARCHAR(30) NOT NULL DEFAULT 'NONE',
    `voucher_status` VARCHAR(30) NOT NULL DEFAULT 'NONE',
    `readiness_status` VARCHAR(30) NOT NULL DEFAULT 'UNKNOWN',
    `correction_status` VARCHAR(30) NOT NULL DEFAULT 'NONE',
    `item_date` DATE NULL DEFAULT NULL,
    `client_id` VARCHAR(36) NULL DEFAULT NULL,
    `project_id` VARCHAR(36) NULL DEFAULT NULL,
    `employee_id` VARCHAR(36) NULL DEFAULT NULL,
    `bank_account_id` VARCHAR(36) NULL DEFAULT NULL,
    `card_id` VARCHAR(36) NULL DEFAULT NULL,
    `account_id` VARCHAR(36) NULL DEFAULT NULL,
    `quantity` DECIMAL(18,3) NULL DEFAULT NULL,
    `unit_price` DECIMAL(18,2) NULL DEFAULT NULL,
    `supply_amount` DECIMAL(18,2) NULL DEFAULT NULL,
    `vat_amount` DECIMAL(18,2) NULL DEFAULT NULL,
    `total_amount` DECIMAL(18,2) NULL DEFAULT NULL,
    `currency` VARCHAR(10) NULL DEFAULT 'KRW',
    `description` TEXT NULL,
    `memo` TEXT NULL,
    `raw_json` LONGTEXT NULL,
    `mapped_payload_json` LONGTEXT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `created_by` VARCHAR(100) NULL DEFAULT NULL,
    `updated_at` DATETIME NULL DEFAULT NULL,
    `updated_by` VARCHAR(100) NULL DEFAULT NULL,
    `deleted_at` DATETIME NULL DEFAULT NULL,
    `deleted_by` VARCHAR(100) NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    INDEX `idx_ledger_processing_items_source` (`source_table`, `source_id`, `deleted_at`, `sort_no`),
    INDEX `idx_ledger_processing_items_type` (`source_type`, `deleted_at`),
    INDEX `idx_ledger_processing_items_parent` (`parent_item_id`),
    INDEX `idx_ledger_processing_items_source_item` (`source_item_id`),
    INDEX `idx_ledger_processing_items_split_group` (`split_group_id`),
    INDEX `idx_ledger_processing_items_merge_group` (`merge_group_id`),
    INDEX `idx_ledger_processing_items_transaction_status` (`transaction_status`),
    INDEX `idx_ledger_processing_items_voucher_status` (`voucher_status`),
    INDEX `idx_ledger_processing_items_readiness_status` (`readiness_status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `ledger_processing_items` (
    `id`,
    `source_table`,
    `source_id`,
    `source_type`,
    `sort_no`,
    `item_type`,
    `line_type`,
    `item_status`,
    `transaction_status`,
    `voucher_status`,
    `readiness_status`,
    `correction_status`,
    `item_date`,
    `client_id`,
    `project_id`,
    `employee_id`,
    `bank_account_id`,
    `card_id`,
    `supply_amount`,
    `vat_amount`,
    `total_amount`,
    `currency`,
    `raw_json`,
    `mapped_payload_json`,
    `created_at`,
    `created_by`,
    `updated_at`,
    `updated_by`,
    `deleted_at`,
    `deleted_by`
)
SELECT
    UUID(),
    'ledger_data_evidences',
    e.`id`,
    e.`source_type`,
    1,
    'DEFAULT',
    e.`source_type`,
    e.`evidence_status`,
    e.`transaction_status`,
    e.`voucher_status`,
    'UNKNOWN',
    CASE WHEN e.`error_message` IS NULL OR e.`error_message` = '' THEN 'NONE' ELSE 'NEEDS_CORRECTION' END,
    e.`evidence_date`,
    e.`client_id`,
    e.`project_id`,
    e.`employee_id`,
    e.`bank_account_id`,
    e.`card_id`,
    e.`supply_amount`,
    e.`vat_amount`,
    e.`total_amount`,
    e.`currency`,
    e.`raw_json`,
    e.`mapped_payload_json`,
    COALESCE(e.`created_at`, NOW()),
    e.`created_by`,
    e.`updated_at`,
    e.`updated_by`,
    e.`deleted_at`,
    e.`deleted_by`
FROM `ledger_data_evidences` e
WHERE NOT EXISTS (
    SELECT 1
    FROM `ledger_processing_items` i
    WHERE i.`source_table` = 'ledger_data_evidences'
      AND i.`source_id` = e.`id`
      AND i.`deleted_at` IS NULL
);

INSERT INTO `ledger_processing_items` (
    `id`,
    `source_table`,
    `source_id`,
    `source_type`,
    `sort_no`,
    `item_type`,
    `line_type`,
    `item_status`,
    `transaction_status`,
    `voucher_status`,
    `readiness_status`,
    `correction_status`,
    `item_date`,
    `bank_account_id`,
    `supply_amount`,
    `vat_amount`,
    `total_amount`,
    `currency`,
    `description`,
    `memo`,
    `created_at`,
    `created_by`,
    `updated_at`,
    `updated_by`,
    `deleted_at`,
    `deleted_by`
)
SELECT
    UUID(),
    'ledger_bank_transactions',
    b.`id`,
    'BANK_TRANSACTION',
    1,
    'DEFAULT',
    b.`transaction_type`,
    b.`status`,
    CASE WHEN b.`status` = 'MATCHED' THEN 'CREATED' ELSE 'NONE' END,
    'WAITING',
    'UNKNOWN',
    'NONE',
    b.`transaction_date`,
    b.`bank_account_id`,
    0,
    0,
    GREATEST(COALESCE(b.`deposit_amount`, 0), COALESCE(b.`withdraw_amount`, 0)),
    b.`currency_code`,
    b.`description`,
    b.`memo`,
    COALESCE(b.`created_at`, NOW()),
    b.`created_by`,
    b.`updated_at`,
    b.`updated_by`,
    b.`deleted_at`,
    b.`deleted_by`
FROM `ledger_bank_transactions` b
WHERE NOT EXISTS (
    SELECT 1
    FROM `ledger_processing_items` i
    WHERE i.`source_table` = 'ledger_bank_transactions'
      AND i.`source_id` = b.`id`
      AND i.`deleted_at` IS NULL
);

ALTER TABLE `ledger_data_evidence_links`
    ADD COLUMN IF NOT EXISTS `processing_item_id` VARCHAR(36) NULL DEFAULT NULL
        AFTER `evidence_id`;

CREATE INDEX IF NOT EXISTS `idx_ledger_data_evidence_links_processing_item`
    ON `ledger_data_evidence_links` (`processing_item_id`);

ALTER TABLE `ledger_transaction_lines`
    ADD COLUMN IF NOT EXISTS `processing_item_id` VARCHAR(36) NULL DEFAULT NULL
        AFTER `transaction_id`;

CREATE INDEX IF NOT EXISTS `idx_ledger_transaction_lines_processing_item`
    ON `ledger_transaction_lines` (`processing_item_id`);

ALTER TABLE `ledger_voucher_lines`
    ADD COLUMN IF NOT EXISTS `processing_item_id` VARCHAR(36) NULL DEFAULT NULL
        AFTER `voucher_id`;

CREATE INDEX IF NOT EXISTS `idx_ledger_voucher_lines_processing_item`
    ON `ledger_voucher_lines` (`processing_item_id`);
