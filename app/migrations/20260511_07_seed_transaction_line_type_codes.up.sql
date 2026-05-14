INSERT INTO `system_codes` (`id`, `sort_no`, `code_group`, `group_name`, `code`, `code_name`, `note`, `is_active`, `created_by`, `updated_by`)
SELECT UUID(), 10, 'TRANSACTION_LINE_TYPE', '거래라인유형', 'ITEM', '품목', '거래의 기본 품목/용역 금액', 1, 'SYSTEM', 'SYSTEM'
WHERE NOT EXISTS (SELECT 1 FROM `system_codes` WHERE `code_group` = 'TRANSACTION_LINE_TYPE' AND `code` = 'ITEM');

INSERT INTO `system_codes` (`id`, `sort_no`, `code_group`, `group_name`, `code`, `code_name`, `note`, `is_active`, `created_by`, `updated_by`)
SELECT UUID(), 20, 'TRANSACTION_LINE_TYPE', '거래라인유형', 'VAT', '부가세', '부가가치세 금액', 1, 'SYSTEM', 'SYSTEM'
WHERE NOT EXISTS (SELECT 1 FROM `system_codes` WHERE `code_group` = 'TRANSACTION_LINE_TYPE' AND `code` = 'VAT');

INSERT INTO `system_codes` (`id`, `sort_no`, `code_group`, `group_name`, `code`, `code_name`, `note`, `is_active`, `created_by`, `updated_by`)
SELECT UUID(), 30, 'TRANSACTION_LINE_TYPE', '거래라인유형', 'SERVICE', '봉사료', '봉사료 또는 서비스 차지', 1, 'SYSTEM', 'SYSTEM'
WHERE NOT EXISTS (SELECT 1 FROM `system_codes` WHERE `code_group` = 'TRANSACTION_LINE_TYPE' AND `code` = 'SERVICE');

INSERT INTO `system_codes` (`id`, `sort_no`, `code_group`, `group_name`, `code`, `code_name`, `note`, `is_active`, `created_by`, `updated_by`)
SELECT UUID(), 40, 'TRANSACTION_LINE_TYPE', '거래라인유형', 'WITHHOLDING_INCOME', '소득세', '원천징수 소득세', 1, 'SYSTEM', 'SYSTEM'
WHERE NOT EXISTS (SELECT 1 FROM `system_codes` WHERE `code_group` = 'TRANSACTION_LINE_TYPE' AND `code` = 'WITHHOLDING_INCOME');

INSERT INTO `system_codes` (`id`, `sort_no`, `code_group`, `group_name`, `code`, `code_name`, `note`, `is_active`, `created_by`, `updated_by`)
SELECT UUID(), 50, 'TRANSACTION_LINE_TYPE', '거래라인유형', 'WITHHOLDING_LOCAL', '주민세', '원천징수 지방소득세', 1, 'SYSTEM', 'SYSTEM'
WHERE NOT EXISTS (SELECT 1 FROM `system_codes` WHERE `code_group` = 'TRANSACTION_LINE_TYPE' AND `code` = 'WITHHOLDING_LOCAL');

INSERT INTO `system_codes` (`id`, `sort_no`, `code_group`, `group_name`, `code`, `code_name`, `note`, `is_active`, `created_by`, `updated_by`)
SELECT UUID(), 60, 'TRANSACTION_LINE_TYPE', '거래라인유형', 'PENSION', '국민연금', '국민연금 공제/부담 금액', 1, 'SYSTEM', 'SYSTEM'
WHERE NOT EXISTS (SELECT 1 FROM `system_codes` WHERE `code_group` = 'TRANSACTION_LINE_TYPE' AND `code` = 'PENSION');

INSERT INTO `system_codes` (`id`, `sort_no`, `code_group`, `group_name`, `code`, `code_name`, `note`, `is_active`, `created_by`, `updated_by`)
SELECT UUID(), 70, 'TRANSACTION_LINE_TYPE', '거래라인유형', 'HEALTH', '건강보험', '건강보험 공제/부담 금액', 1, 'SYSTEM', 'SYSTEM'
WHERE NOT EXISTS (SELECT 1 FROM `system_codes` WHERE `code_group` = 'TRANSACTION_LINE_TYPE' AND `code` = 'HEALTH');

INSERT INTO `system_codes` (`id`, `sort_no`, `code_group`, `group_name`, `code`, `code_name`, `note`, `is_active`, `created_by`, `updated_by`)
SELECT UUID(), 80, 'TRANSACTION_LINE_TYPE', '거래라인유형', 'LONGTERM_CARE', '장기요양', '장기요양보험 금액', 1, 'SYSTEM', 'SYSTEM'
WHERE NOT EXISTS (SELECT 1 FROM `system_codes` WHERE `code_group` = 'TRANSACTION_LINE_TYPE' AND `code` = 'LONGTERM_CARE');

INSERT INTO `system_codes` (`id`, `sort_no`, `code_group`, `group_name`, `code`, `code_name`, `note`, `is_active`, `created_by`, `updated_by`)
SELECT UUID(), 90, 'TRANSACTION_LINE_TYPE', '거래라인유형', 'EMPLOYMENT', '고용보험', '고용보험 금액', 1, 'SYSTEM', 'SYSTEM'
WHERE NOT EXISTS (SELECT 1 FROM `system_codes` WHERE `code_group` = 'TRANSACTION_LINE_TYPE' AND `code` = 'EMPLOYMENT');

INSERT INTO `system_codes` (`id`, `sort_no`, `code_group`, `group_name`, `code`, `code_name`, `note`, `is_active`, `created_by`, `updated_by`)
SELECT UUID(), 100, 'TRANSACTION_LINE_TYPE', '거래라인유형', 'SUPPORT', '지원금', '지원금/보조금 금액', 1, 'SYSTEM', 'SYSTEM'
WHERE NOT EXISTS (SELECT 1 FROM `system_codes` WHERE `code_group` = 'TRANSACTION_LINE_TYPE' AND `code` = 'SUPPORT');

INSERT INTO `system_codes` (`id`, `sort_no`, `code_group`, `group_name`, `code`, `code_name`, `note`, `is_active`, `created_by`, `updated_by`)
SELECT UUID(), 110, 'TRANSACTION_LINE_TYPE', '거래라인유형', 'DISCOUNT', '할인', '할인/차감 금액', 1, 'SYSTEM', 'SYSTEM'
WHERE NOT EXISTS (SELECT 1 FROM `system_codes` WHERE `code_group` = 'TRANSACTION_LINE_TYPE' AND `code` = 'DISCOUNT');

INSERT INTO `system_codes` (`id`, `sort_no`, `code_group`, `group_name`, `code`, `code_name`, `note`, `is_active`, `created_by`, `updated_by`)
SELECT UUID(), 120, 'TRANSACTION_LINE_TYPE', '거래라인유형', 'FREIGHT', '운임', '운임/배송비 금액', 1, 'SYSTEM', 'SYSTEM'
WHERE NOT EXISTS (SELECT 1 FROM `system_codes` WHERE `code_group` = 'TRANSACTION_LINE_TYPE' AND `code` = 'FREIGHT');

INSERT INTO `system_codes` (`id`, `sort_no`, `code_group`, `group_name`, `code`, `code_name`, `note`, `is_active`, `created_by`, `updated_by`)
SELECT UUID(), 130, 'TRANSACTION_LINE_TYPE', '거래라인유형', 'CUSTOMS', '관세', '관세/통관 관련 금액', 1, 'SYSTEM', 'SYSTEM'
WHERE NOT EXISTS (SELECT 1 FROM `system_codes` WHERE `code_group` = 'TRANSACTION_LINE_TYPE' AND `code` = 'CUSTOMS');

INSERT INTO `system_codes` (`id`, `sort_no`, `code_group`, `group_name`, `code`, `code_name`, `note`, `is_active`, `created_by`, `updated_by`)
SELECT UUID(), 999, 'TRANSACTION_LINE_TYPE', '거래라인유형', 'ETC', '기타', '기타 거래 금액 구성요소', 1, 'SYSTEM', 'SYSTEM'
WHERE NOT EXISTS (SELECT 1 FROM `system_codes` WHERE `code_group` = 'TRANSACTION_LINE_TYPE' AND `code` = 'ETC');
