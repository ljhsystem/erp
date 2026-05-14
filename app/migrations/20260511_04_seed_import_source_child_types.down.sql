DELETE FROM `system_codes`
WHERE `code_group` = 'IMPORT_TYPE'
  AND `code` = 'CARD_HOMETAX';

UPDATE `system_codes`
SET `code_name` = '카드',
    `description` = '법인카드/개인카드 승인 원본 데이터. 거래 및 전표 동시 생성 가능',
    `updated_by` = 'SYSTEM',
    `updated_at` = NOW()
WHERE `code_group` = 'IMPORT_TYPE'
  AND `code` = 'CARD_APPROVAL';
