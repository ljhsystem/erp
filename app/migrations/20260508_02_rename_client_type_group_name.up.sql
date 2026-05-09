UPDATE `system_codes`
SET `group_name` = '거래처구분'
WHERE `code_group` = 'CLIENT_TYPE'
  AND (
      `group_name` IS NULL
      OR TRIM(`group_name`) = ''
      OR `group_name` IN ('거래처', '거래유형')
  );
