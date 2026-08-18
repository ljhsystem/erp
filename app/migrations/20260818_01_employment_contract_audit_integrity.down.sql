INSERT INTO `system_codes`
    (`id`, `sort_no`, `code_group`, `group_name`, `code`, `code_name`, `is_active`, `created_at`, `created_by`)
SELECT UUID(), COALESCE(MAX(`sort_no`), 0) + 1, 'EMPLOYMENT_CONTRACT_STATUS', '근로계약상태', 'EFFECTIVE', '효력중', 1, NOW(), 'SYSTEM:MIGRATION'
FROM `system_codes`
WHERE NOT EXISTS (SELECT 1 FROM `system_codes` WHERE `code_group`='EMPLOYMENT_CONTRACT_STATUS' AND `code`='EFFECTIVE');
INSERT INTO `system_codes`
    (`id`, `sort_no`, `code_group`, `group_name`, `code`, `code_name`, `is_active`, `created_at`, `created_by`)
SELECT UUID(), COALESCE(MAX(`sort_no`), 0) + 1, 'EMPLOYMENT_CONTRACT_STATUS', '근로계약상태', 'EXPIRED', '만료', 1, NOW(), 'SYSTEM:MIGRATION'
FROM `system_codes`
WHERE NOT EXISTS (SELECT 1 FROM `system_codes` WHERE `code_group`='EMPLOYMENT_CONTRACT_STATUS' AND `code`='EXPIRED');

ALTER TABLE `institution_regular_employment_income_items`
    DROP FOREIGN KEY `fk_institution_regular_employment_income_item_contract`;
ALTER TABLE `institution_regular_employment_income_items`
    ADD CONSTRAINT `fk_institution_regular_employment_income_item_contract`
        FOREIGN KEY (`employment_contract_id`) REFERENCES `institution_employment_contracts` (`id`)
        ON UPDATE RESTRICT ON DELETE SET NULL;

DROP TABLE `institution_employment_contracts_audits`;
