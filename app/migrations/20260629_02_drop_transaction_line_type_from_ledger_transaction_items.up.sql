DELETE FROM `system_codes`
WHERE `code_group` = 'TRANSACTION_LINE_TYPE';

ALTER TABLE `ledger_transaction_items`
DROP COLUMN `transaction_line_type`;
