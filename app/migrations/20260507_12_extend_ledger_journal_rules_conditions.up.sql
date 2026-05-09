ALTER TABLE `ledger_journal_rules`
    ADD COLUMN IF NOT EXISTS `business_unit` VARCHAR(30) NOT NULL DEFAULT 'HQ' COMMENT '사업유형(BUSINESS_UNIT)' AFTER `rule_name`,
    ADD COLUMN IF NOT EXISTS `transaction_type` VARCHAR(30) NOT NULL DEFAULT 'GENERAL' COMMENT '거래유형(TRANSACTION_TYPE)' AFTER `business_unit`;

CREATE INDEX IF NOT EXISTS `idx_ledger_journal_rules_conditions`
    ON `ledger_journal_rules` (`business_unit`, `transaction_type`, `import_type`, `transaction_direction`, `is_active`);

INSERT INTO `system_codes`
    (`id`, `code_group`, `code`, `code_name`, `description`, `sort_no`, `is_active`, `created_at`, `updated_at`)
SELECT UUID(), 'TRANSACTION_DIRECTION', 'PURCHASE', '매입', '거래방향/매입', 1, 1, NOW(), NOW()
WHERE NOT EXISTS (
    SELECT 1 FROM `system_codes`
    WHERE `code_group` = 'TRANSACTION_DIRECTION'
      AND `code` = 'PURCHASE'
);

INSERT INTO `system_codes`
    (`id`, `code_group`, `code`, `code_name`, `description`, `sort_no`, `is_active`, `created_at`, `updated_at`)
SELECT UUID(), 'TRANSACTION_DIRECTION', 'SALES', '매출', '거래방향/매출', 2, 1, NOW(), NOW()
WHERE NOT EXISTS (
    SELECT 1 FROM `system_codes`
    WHERE `code_group` = 'TRANSACTION_DIRECTION'
      AND `code` = 'SALES'
);

INSERT INTO `system_codes`
    (`id`, `code_group`, `code`, `code_name`, `description`, `sort_no`, `is_active`, `created_at`, `updated_at`)
SELECT UUID(), 'TRANSACTION_DIRECTION', 'IN', '입금', '거래방향/입금', 3, 1, NOW(), NOW()
WHERE NOT EXISTS (
    SELECT 1 FROM `system_codes`
    WHERE `code_group` = 'TRANSACTION_DIRECTION'
      AND `code` = 'IN'
);

INSERT INTO `system_codes`
    (`id`, `code_group`, `code`, `code_name`, `description`, `sort_no`, `is_active`, `created_at`, `updated_at`)
SELECT UUID(), 'TRANSACTION_DIRECTION', 'OUT', '출금', '거래방향/출금', 4, 1, NOW(), NOW()
WHERE NOT EXISTS (
    SELECT 1 FROM `system_codes`
    WHERE `code_group` = 'TRANSACTION_DIRECTION'
      AND `code` = 'OUT'
);
