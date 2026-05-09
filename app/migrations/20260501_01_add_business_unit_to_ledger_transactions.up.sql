ALTER TABLE `ledger_transactions`
    ADD COLUMN IF NOT EXISTS `business_unit` VARCHAR(30) NOT NULL DEFAULT 'HQ'
        COMMENT 'Business unit (CONSTRUCTION, ECOMMERCE, HQ)'
        COLLATE 'utf8mb4_general_ci'
        AFTER `transaction_type`;

CREATE INDEX IF NOT EXISTS `idx_ledger_transactions_business_unit`
    ON `ledger_transactions` (`business_unit`);

INSERT INTO `system_codes` (
    `id`, `sort_no`, `code_group`, `code`, `code_name`,
    `note`, `memo`, `is_active`, `created_by`, `updated_by`
)
SELECT UUID(), COALESCE((SELECT MAX(sc.sort_no) FROM `system_codes` sc), 0) + 1,
       'BUSINESS_UNIT', 'CONSTRUCTION', 'Construction',
       NULL, NULL, 1, 'migration', 'migration'
WHERE NOT EXISTS (
    SELECT 1 FROM `system_codes`
    WHERE `code_group` = 'BUSINESS_UNIT'
      AND `code` = 'CONSTRUCTION'
);

INSERT INTO `system_codes` (
    `id`, `sort_no`, `code_group`, `code`, `code_name`,
    `note`, `memo`, `is_active`, `created_by`, `updated_by`
)
SELECT UUID(), COALESCE((SELECT MAX(sc.sort_no) FROM `system_codes` sc), 0) + 1,
       'BUSINESS_UNIT', 'ECOMMERCE', 'E-commerce',
       NULL, NULL, 1, 'migration', 'migration'
WHERE NOT EXISTS (
    SELECT 1 FROM `system_codes`
    WHERE `code_group` = 'BUSINESS_UNIT'
      AND `code` = 'ECOMMERCE'
);

INSERT INTO `system_codes` (
    `id`, `sort_no`, `code_group`, `code`, `code_name`,
    `note`, `memo`, `is_active`, `created_by`, `updated_by`
)
SELECT UUID(), COALESCE((SELECT MAX(sc.sort_no) FROM `system_codes` sc), 0) + 1,
       'BUSINESS_UNIT', 'HQ', 'HQ',
       NULL, NULL, 1, 'migration', 'migration'
WHERE NOT EXISTS (
    SELECT 1 FROM `system_codes`
    WHERE `code_group` = 'BUSINESS_UNIT'
      AND `code` = 'HQ'
);
