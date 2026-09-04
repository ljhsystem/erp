SET NAMES utf8mb4;

DELIMITER $$
CREATE PROCEDURE migrate_20260903_21_remove_business_income_other_deduction()
BEGIN
    IF EXISTS (SELECT 1 FROM institution_business_incomes)
       OR EXISTS (SELECT 1 FROM institution_business_income_items)
       OR EXISTS (SELECT 1 FROM ledger_evidence_business_income)
       OR EXISTS (SELECT 1 FROM institution_business_income_calculation_lines WHERE line_code='OTHER_DEDUCTION') THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='기존 사업소득 자료가 있어 기타공제를 자동 제거할 수 없습니다.';
    END IF;

    ALTER TABLE institution_business_incomes
        DROP CONSTRAINT chk_business_income_header_reconciliation,
        DROP CONSTRAINT chk_business_income_header_amounts,
        DROP COLUMN total_other_deduction_amount,
        ADD CONSTRAINT chk_business_income_header_amounts CHECK (total_gross_payment_amount>=0 AND total_income_tax_amount>=0 AND total_local_income_tax_amount>=0 AND total_deduction_amount>=0 AND total_net_payment_amount>=0),
        ADD CONSTRAINT chk_business_income_header_reconciliation CHECK (total_deduction_amount=total_income_tax_amount+total_local_income_tax_amount AND total_net_payment_amount=total_gross_payment_amount-total_deduction_amount);

    ALTER TABLE institution_business_income_items
        DROP CONSTRAINT chk_business_income_item_reconciliation,
        DROP CONSTRAINT chk_business_income_item_amounts,
        DROP COLUMN other_deduction_reason,
        DROP COLUMN other_deduction_amount,
        ADD CONSTRAINT chk_business_income_item_amounts CHECK (gross_payment_amount>=0 AND income_tax_amount>=0 AND local_income_tax_amount>=0 AND total_deduction_amount>=0 AND net_payment_amount>=0),
        ADD CONSTRAINT chk_business_income_item_reconciliation CHECK (total_deduction_amount=income_tax_amount+local_income_tax_amount AND net_payment_amount=gross_payment_amount-total_deduction_amount);

    ALTER TABLE ledger_evidence_business_income
        DROP COLUMN raw_other_deduction_reason,
        DROP COLUMN raw_other_deduction_amount;

    ALTER TABLE institution_business_income_calculation_lines
        DROP CONSTRAINT chk_business_income_calc_line_code,
        ADD CONSTRAINT chk_business_income_calc_line_code CHECK (line_code IN ('GROSS_PAYMENT','INCOME_TAX','LOCAL_INCOME_TAX'));
END$$
DELIMITER ;

CALL migrate_20260903_21_remove_business_income_other_deduction();
DROP PROCEDURE migrate_20260903_21_remove_business_income_other_deduction;
