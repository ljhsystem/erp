DELIMITER $$
CREATE PROCEDURE rollback_20260903_17_add_business_income_header_memo()
BEGIN
    IF EXISTS (SELECT 1 FROM institution_business_incomes WHERE memo IS NOT NULL AND TRIM(memo)<>'') THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='사업소득 메모 데이터가 있어 자동 제거할 수 없습니다.';
    END IF;
    ALTER TABLE institution_business_incomes DROP COLUMN memo, ALGORITHM=INSTANT, LOCK=NONE;
END$$
DELIMITER ;

CALL rollback_20260903_17_add_business_income_header_memo();
DROP PROCEDURE rollback_20260903_17_add_business_income_header_memo;
