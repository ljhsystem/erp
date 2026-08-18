DELIMITER $$

DROP PROCEDURE IF EXISTS migrate_personal_expense_header_aggregates_down$$
CREATE PROCEDURE migrate_personal_expense_header_aggregates_down()
BEGIN
    IF EXISTS (
        SELECT 1
          FROM information_schema.TABLES
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = 'approval_personal_expenses'
    ) THEN
        ALTER TABLE approval_personal_expenses
            DROP COLUMN IF EXISTS total_amount,
            DROP COLUMN IF EXISTS vat_amount,
            DROP COLUMN IF EXISTS supply_amount,
            DROP COLUMN IF EXISTS item_count;
    END IF;
END$$

CALL migrate_personal_expense_header_aggregates_down()$$
DROP PROCEDURE migrate_personal_expense_header_aggregates_down$$

DELIMITER ;
