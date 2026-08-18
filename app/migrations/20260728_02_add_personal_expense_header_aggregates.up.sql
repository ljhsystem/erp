DELIMITER $$

DROP PROCEDURE IF EXISTS migrate_personal_expense_header_aggregates_up$$
CREATE PROCEDURE migrate_personal_expense_header_aggregates_up()
BEGIN
    IF NOT EXISTS (
        SELECT 1
          FROM information_schema.TABLES
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = 'approval_personal_expenses'
    ) OR NOT EXISTS (
        SELECT 1
          FROM information_schema.TABLES
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = 'approval_personal_expense_items'
    ) THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = '개인경비 신청 헤더 또는 항목 테이블을 찾을 수 없습니다.';
    END IF;

    ALTER TABLE approval_personal_expenses
        ADD COLUMN IF NOT EXISTS item_count INT UNSIGNED NOT NULL DEFAULT 0
            COMMENT '활성 항목수' AFTER memo,
        ADD COLUMN IF NOT EXISTS supply_amount DECIMAL(18,2) NOT NULL DEFAULT 0
            COMMENT '공급가액 합계' AFTER item_count,
        ADD COLUMN IF NOT EXISTS vat_amount DECIMAL(18,2) NOT NULL DEFAULT 0
            COMMENT '부가세 합계' AFTER supply_amount,
        ADD COLUMN IF NOT EXISTS total_amount DECIMAL(18,2) NOT NULL DEFAULT 0
            COMMENT '신청금액 합계' AFTER vat_amount;

    ALTER TABLE approval_personal_expenses
        MODIFY COLUMN item_count INT UNSIGNED NOT NULL DEFAULT 0
            COMMENT '활성 항목수' AFTER memo,
        MODIFY COLUMN supply_amount DECIMAL(18,2) NOT NULL DEFAULT 0
            COMMENT '공급가액 합계' AFTER item_count,
        MODIFY COLUMN vat_amount DECIMAL(18,2) NOT NULL DEFAULT 0
            COMMENT '부가세 합계' AFTER supply_amount,
        MODIFY COLUMN total_amount DECIMAL(18,2) NOT NULL DEFAULT 0
            COMMENT '신청금액 합계' AFTER vat_amount;

    UPDATE approval_personal_expenses header_row
    LEFT JOIN (
        SELECT personal_expense_id,
               COUNT(*) AS item_count,
               COALESCE(SUM(item_supply_amount), 0) AS supply_amount,
               COALESCE(SUM(item_vat_amount), 0) AS vat_amount,
               COALESCE(SUM(item_total_amount), 0) AS total_amount
          FROM approval_personal_expense_items
         WHERE deleted_at IS NULL
         GROUP BY personal_expense_id
    ) aggregate_row
      ON aggregate_row.personal_expense_id = header_row.id
       SET header_row.item_count = COALESCE(aggregate_row.item_count, 0),
           header_row.supply_amount = COALESCE(aggregate_row.supply_amount, 0),
           header_row.vat_amount = COALESCE(aggregate_row.vat_amount, 0),
           header_row.total_amount = COALESCE(aggregate_row.total_amount, 0);
END$$

CALL migrate_personal_expense_header_aggregates_up()$$
DROP PROCEDURE migrate_personal_expense_header_aggregates_up$$

DELIMITER ;
