UPDATE `system_codes`
SET `group_name` = '거래처'
WHERE `code_group` = 'CLIENT_TYPE'
  AND `group_name` = '거래처구분';
