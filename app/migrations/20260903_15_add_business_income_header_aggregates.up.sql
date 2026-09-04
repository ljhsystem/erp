SET NAMES utf8mb4;

DELIMITER $$
CREATE PROCEDURE migrate_20260903_15_business_income_header_aggregates()
BEGIN
    IF NOT EXISTS (SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='institution_business_incomes') THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='사업소득 Header 테이블을 찾을 수 없습니다.';
    END IF;
    IF EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='institution_business_incomes'
          AND COLUMN_NAME IN ('group_count','item_count','total_gross_payment_amount','total_income_tax_amount','total_local_income_tax_amount','total_other_deduction_amount','total_deduction_amount','total_net_payment_amount')
    ) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='사업소득 Header 집계컬럼이 이미 존재합니다.';
    END IF;

    ALTER TABLE institution_business_incomes
        ADD COLUMN group_count INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '소득그룹 수' AFTER description,
        ADD COLUMN item_count INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '소득자 지급내역 수' AFTER group_count,
        ADD COLUMN total_gross_payment_amount DECIMAL(18,2) NOT NULL DEFAULT 0 COMMENT '총지급액 합계' AFTER item_count,
        ADD COLUMN total_income_tax_amount DECIMAL(18,2) NOT NULL DEFAULT 0 COMMENT '사업소득세 합계' AFTER total_gross_payment_amount,
        ADD COLUMN total_local_income_tax_amount DECIMAL(18,2) NOT NULL DEFAULT 0 COMMENT '개인지방소득세 합계' AFTER total_income_tax_amount,
        ADD COLUMN total_other_deduction_amount DECIMAL(18,2) NOT NULL DEFAULT 0 COMMENT '기타공제액 합계' AFTER total_local_income_tax_amount,
        ADD COLUMN total_deduction_amount DECIMAL(18,2) NOT NULL DEFAULT 0 COMMENT '총공제액 합계' AFTER total_other_deduction_amount,
        ADD COLUMN total_net_payment_amount DECIMAL(18,2) NOT NULL DEFAULT 0 COMMENT '최종지급액 합계' AFTER total_deduction_amount,
        ALGORITHM=INSTANT, LOCK=NONE;

    UPDATE institution_business_incomes header_row
    LEFT JOIN (
        SELECT business_group.business_income_id,
               COUNT(DISTINCT business_group.id) group_count,
               COUNT(item.id) item_count,
               COALESCE(SUM(item.gross_payment_amount),0) total_gross_payment_amount,
               COALESCE(SUM(item.income_tax_amount),0) total_income_tax_amount,
               COALESCE(SUM(item.local_income_tax_amount),0) total_local_income_tax_amount,
               COALESCE(SUM(item.other_deduction_amount),0) total_other_deduction_amount,
               COALESCE(SUM(item.total_deduction_amount),0) total_deduction_amount,
               COALESCE(SUM(item.net_payment_amount),0) total_net_payment_amount
        FROM institution_business_income_groups business_group
        LEFT JOIN institution_business_income_items item ON item.group_id=business_group.id AND item.deleted_at IS NULL
        WHERE business_group.deleted_at IS NULL
        GROUP BY business_group.business_income_id
    ) aggregate_row ON aggregate_row.business_income_id=header_row.id
    SET header_row.group_count=COALESCE(aggregate_row.group_count,0),
        header_row.item_count=COALESCE(aggregate_row.item_count,0),
        header_row.total_gross_payment_amount=COALESCE(aggregate_row.total_gross_payment_amount,0),
        header_row.total_income_tax_amount=COALESCE(aggregate_row.total_income_tax_amount,0),
        header_row.total_local_income_tax_amount=COALESCE(aggregate_row.total_local_income_tax_amount,0),
        header_row.total_other_deduction_amount=COALESCE(aggregate_row.total_other_deduction_amount,0),
        header_row.total_deduction_amount=COALESCE(aggregate_row.total_deduction_amount,0),
        header_row.total_net_payment_amount=COALESCE(aggregate_row.total_net_payment_amount,0);

    ALTER TABLE institution_business_incomes
        ADD CONSTRAINT chk_business_income_header_counts CHECK (group_count>=0 AND item_count>=0),
        ADD CONSTRAINT chk_business_income_header_amounts CHECK (total_gross_payment_amount>=0 AND total_income_tax_amount>=0 AND total_local_income_tax_amount>=0 AND total_other_deduction_amount>=0 AND total_deduction_amount>=0 AND total_net_payment_amount>=0),
        ADD CONSTRAINT chk_business_income_header_reconciliation CHECK (total_deduction_amount=total_income_tax_amount+total_local_income_tax_amount+total_other_deduction_amount AND total_net_payment_amount=total_gross_payment_amount-total_deduction_amount);
END$$
DELIMITER ;

CALL migrate_20260903_15_business_income_header_aggregates();
DROP PROCEDURE migrate_20260903_15_business_income_header_aggregates;
