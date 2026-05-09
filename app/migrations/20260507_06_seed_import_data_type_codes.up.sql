INSERT INTO `system_codes` (`id`, `sort_no`, `code_group`, `code`, `code_name`, `note`, `is_active`, `created_by`, `updated_by`)
SELECT UUID(), (SELECT COALESCE(MAX(sc.sort_no), 0) + 1 FROM `system_codes` sc), 'IMPORT_TYPE', 'TAX_INVOICE', '세금계산서', '홈택스 또는 전자세금계산서 원본 데이터. 거래헤더/거래내역 생성 대상', 1, 'SYSTEM', 'SYSTEM'
WHERE NOT EXISTS (
    SELECT 1 FROM `system_codes` WHERE `code_group` = 'IMPORT_TYPE' AND `code` = 'TAX_INVOICE'
);

INSERT INTO `system_codes` (`id`, `sort_no`, `code_group`, `code`, `code_name`, `note`, `is_active`, `created_by`, `updated_by`)
SELECT UUID(), (SELECT COALESCE(MAX(sc.sort_no), 0) + 1 FROM `system_codes` sc), 'IMPORT_TYPE', 'CASH_RECEIPT', '현금영수증', '홈택스 현금영수증 원본 데이터. 거래 생성 및 필요 시 전표 생성 가능', 1, 'SYSTEM', 'SYSTEM'
WHERE NOT EXISTS (
    SELECT 1 FROM `system_codes` WHERE `code_group` = 'IMPORT_TYPE' AND `code` = 'CASH_RECEIPT'
);

INSERT INTO `system_codes` (`id`, `sort_no`, `code_group`, `code`, `code_name`, `note`, `is_active`, `created_by`, `updated_by`)
SELECT UUID(), (SELECT COALESCE(MAX(sc.sort_no), 0) + 1 FROM `system_codes` sc), 'IMPORT_TYPE', 'CARD_APPROVAL', '카드', '법인카드/개인카드 승인 원본 데이터. 거래 및 전표 동시 생성 가능', 1, 'SYSTEM', 'SYSTEM'
WHERE NOT EXISTS (
    SELECT 1 FROM `system_codes` WHERE `code_group` = 'IMPORT_TYPE' AND `code` = 'CARD_APPROVAL'
);

INSERT INTO `system_codes` (`id`, `sort_no`, `code_group`, `code`, `code_name`, `note`, `is_active`, `created_by`, `updated_by`)
SELECT UUID(), (SELECT COALESCE(MAX(sc.sort_no), 0) + 1 FROM `system_codes` sc), 'IMPORT_TYPE', 'BANK_TRANSACTION', '입출금', '은행 입출금 원본 데이터. 거래 없이 전표 중심 처리 대상', 1, 'SYSTEM', 'SYSTEM'
WHERE NOT EXISTS (
    SELECT 1 FROM `system_codes` WHERE `code_group` = 'IMPORT_TYPE' AND `code` = 'BANK_TRANSACTION'
);

INSERT INTO `system_codes` (`id`, `sort_no`, `code_group`, `code`, `code_name`, `note`, `is_active`, `created_by`, `updated_by`)
SELECT UUID(), (SELECT COALESCE(MAX(sc.sort_no), 0) + 1 FROM `system_codes` sc), 'IMPORT_TYPE', 'SHOPPING_ORDER', '주문', '쇼핑몰 주문/판매 원본 데이터. 거래헤더 및 거래내역 생성 대상', 1, 'SYSTEM', 'SYSTEM'
WHERE NOT EXISTS (
    SELECT 1 FROM `system_codes` WHERE `code_group` = 'IMPORT_TYPE' AND `code` = 'SHOPPING_ORDER'
);

INSERT INTO `system_codes` (`id`, `sort_no`, `code_group`, `code`, `code_name`, `note`, `is_active`, `created_by`, `updated_by`)
SELECT UUID(), (SELECT COALESCE(MAX(sc.sort_no), 0) + 1 FROM `system_codes` sc), 'IMPORT_TYPE', 'IMPORT_INVOICE', '수입인보이스', '수입/무역 인보이스 원본 데이터. 거래 및 원가 처리 대상', 1, 'SYSTEM', 'SYSTEM'
WHERE NOT EXISTS (
    SELECT 1 FROM `system_codes` WHERE `code_group` = 'IMPORT_TYPE' AND `code` = 'IMPORT_INVOICE'
);
