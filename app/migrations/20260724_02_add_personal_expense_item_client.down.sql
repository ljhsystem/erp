ALTER TABLE `approval_personal_expense_items`
    DROP FOREIGN KEY `fk_personal_expense_items_client`,
    DROP INDEX `idx_personal_expense_items_client`,
    DROP COLUMN `client_id`;
