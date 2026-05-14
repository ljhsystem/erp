DELETE FROM `system_codes`
WHERE `code_group` = 'TRANSACTION_LINE_TYPE'
  AND `code` IN (
      'ITEM',
      'VAT',
      'SERVICE',
      'WITHHOLDING_INCOME',
      'WITHHOLDING_LOCAL',
      'PENSION',
      'HEALTH',
      'LONGTERM_CARE',
      'EMPLOYMENT',
      'SUPPORT',
      'DISCOUNT',
      'FREIGHT',
      'CUSTOMS',
      'ETC'
  )
  AND `created_by` = 'SYSTEM';
