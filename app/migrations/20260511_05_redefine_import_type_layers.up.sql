INSERT INTO `system_codes` (`id`, `sort_no`, `code_group`, `code`, `code_name`, `note`, `is_active`, `created_by`, `updated_by`)
SELECT UUID(), (SELECT COALESCE(MAX(sc.sort_no), 0) + 1 FROM `system_codes` sc), 'IMPORT_TYPE', 'CARD_STATEMENT', '카드(카드사)', '자료출처(카드사)/실제 청구 원본 데이터. 거래/전표/카드대금 관리 기준', 1, 'SYSTEM', 'SYSTEM'
WHERE NOT EXISTS (
    SELECT 1 FROM `system_codes` WHERE `code_group` = 'IMPORT_TYPE' AND `code` = 'CARD_STATEMENT'
);

INSERT INTO `system_codes` (`id`, `sort_no`, `code_group`, `code`, `code_name`, `note`, `is_active`, `created_by`, `updated_by`)
SELECT UUID(), (SELECT COALESCE(MAX(sc.sort_no), 0) + 1 FROM `system_codes` sc), 'IMPORT_TYPE', 'BUSINESS_DATA', '업무데이터', 'ERP 내부 업무시스템 공통 상위유형. 자료업로드가 아닌 업무시스템 내부 흐름에서 처리', 1, 'SYSTEM', 'SYSTEM'
WHERE NOT EXISTS (
    SELECT 1 FROM `system_codes` WHERE `code_group` = 'IMPORT_TYPE' AND `code` = 'BUSINESS_DATA'
);

UPDATE `system_codes`
SET `code_name` = '카드(카드사)',
    `note` = '자료출처(카드사)/실제 청구 원본 데이터. 거래/전표/카드대금 관리 기준',
    `description` = '자료출처(카드사)/실제 청구 원본 데이터. 거래/전표/카드대금 관리 기준',
    `is_active` = 1,
    `updated_by` = 'SYSTEM',
    `updated_at` = NOW()
WHERE `code_group` = 'IMPORT_TYPE'
  AND `code` = 'CARD_STATEMENT';

UPDATE `system_codes`
SET `code_name` = '카드(홈택스)',
    `note` = '자료출처(홈택스)/세무 검증 및 카드사 데이터 대사용 카드 사용내역',
    `description` = '자료출처(홈택스)/세무 검증 및 카드사 데이터 대사용 카드 사용내역',
    `is_active` = 1,
    `updated_by` = 'SYSTEM',
    `updated_at` = NOW()
WHERE `code_group` = 'IMPORT_TYPE'
  AND `code` = 'CARD_HOMETAX';

UPDATE `system_codes`
SET `is_active` = 0,
    `note` = '레거시 카드 자료유형. 신규 업로드는 CARD_STATEMENT 또는 CARD_HOMETAX 사용',
    `description` = '레거시 카드 자료유형. 신규 업로드는 CARD_STATEMENT 또는 CARD_HOMETAX 사용',
    `updated_by` = 'SYSTEM',
    `updated_at` = NOW()
WHERE `code_group` = 'IMPORT_TYPE'
  AND `code` = 'CARD_APPROVAL';

UPDATE `system_codes`
SET `is_active` = 0,
    `note` = '업무데이터형은 자료업로드가 아닌 별도 업무시스템에서 처리',
    `description` = '업무데이터형은 자료업로드가 아닌 별도 업무시스템에서 처리',
    `updated_by` = 'SYSTEM',
    `updated_at` = NOW()
WHERE `code_group` = 'IMPORT_TYPE'
  AND `code` IN ('SHOPPING_ORDER', 'IMPORT_INVOICE', 'PAYROLL', 'PAYROLL_WITHHOLDING', 'BUSINESS_INCOME', 'EMPLOYEE_EXPENSE', 'CONSTRUCTION');
