INSERT INTO `system_codes` (`id`, `sort_no`, `code_group`, `code`, `code_name`, `description`, `is_active`, `created_by`, `updated_by`)
SELECT UUID(), (SELECT COALESCE(MAX(sc.sort_no), 0) + 1 FROM `system_codes` sc), 'SOURCE_TYPE', 'HOMETAX', '홈택스', '홈택스 수집 원본 자료의 부모 출처', 1, 'SYSTEM', 'SYSTEM'
WHERE NOT EXISTS (
    SELECT 1 FROM `system_codes` WHERE `code_group` = 'SOURCE_TYPE' AND `code` = 'HOMETAX'
);

INSERT INTO `system_codes` (`id`, `sort_no`, `code_group`, `code`, `code_name`, `description`, `is_active`, `created_by`, `updated_by`)
SELECT UUID(), (SELECT COALESCE(MAX(sc.sort_no), 0) + 1 FROM `system_codes` sc), 'SOURCE_TYPE', 'CARD', '카드사', '카드사 수집 원본 자료의 부모 출처', 1, 'SYSTEM', 'SYSTEM'
WHERE NOT EXISTS (
    SELECT 1 FROM `system_codes` WHERE `code_group` = 'SOURCE_TYPE' AND `code` = 'CARD'
);

INSERT INTO `system_codes` (`id`, `sort_no`, `code_group`, `code`, `code_name`, `description`, `is_active`, `created_by`, `updated_by`)
SELECT UUID(), (SELECT COALESCE(MAX(sc.sort_no), 0) + 1 FROM `system_codes` sc), 'SOURCE_TYPE', 'BANK', '은행', '은행 수집 원본 자료의 부모 출처', 1, 'SYSTEM', 'SYSTEM'
WHERE NOT EXISTS (
    SELECT 1 FROM `system_codes` WHERE `code_group` = 'SOURCE_TYPE' AND `code` = 'BANK'
);

INSERT INTO `system_codes` (`id`, `sort_no`, `code_group`, `code`, `code_name`, `description`, `is_active`, `created_by`, `updated_by`)
SELECT UUID(), (SELECT COALESCE(MAX(sc.sort_no), 0) + 1 FROM `system_codes` sc), 'IMPORT_TYPE', 'CARD_HOMETAX', '카드(홈택스)', '홈택스 카드 사용내역. 부모 자료출처: HOMETAX', 1, 'SYSTEM', 'SYSTEM'
WHERE NOT EXISTS (
    SELECT 1 FROM `system_codes` WHERE `code_group` = 'IMPORT_TYPE' AND `code` = 'CARD_HOMETAX'
);

UPDATE `system_codes`
SET `code_name` = '카드(카드사)',
    `description` = '카드사 승인 원본 데이터. 부모 자료출처: CARD',
    `updated_by` = 'SYSTEM',
    `updated_at` = NOW()
WHERE `code_group` = 'IMPORT_TYPE'
  AND `code` = 'CARD_APPROVAL';
