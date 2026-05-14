DELETE FROM `system_codes`
WHERE `code_group` = 'IMPORT_TYPE'
  AND `code` IN ('CARD_STATEMENT', 'BUSINESS_DATA')
  AND `created_by` = 'SYSTEM';

UPDATE `system_codes`
SET `is_active` = 1,
    `updated_by` = 'SYSTEM',
    `updated_at` = NOW()
WHERE `code_group` = 'IMPORT_TYPE'
  AND `code` IN ('CARD_APPROVAL', 'SHOPPING_ORDER', 'IMPORT_INVOICE', 'PAYROLL', 'PAYROLL_WITHHOLDING', 'BUSINESS_INCOME', 'EMPLOYEE_EXPENSE', 'CONSTRUCTION');
