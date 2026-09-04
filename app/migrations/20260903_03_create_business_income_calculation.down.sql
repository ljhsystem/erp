-- 운영 적용 후 자동 Down 실행 금지. 승인된 복구 절차에서만 사용한다.
ALTER TABLE institution_business_incomes DROP FOREIGN KEY fk_business_income_current_revision;
DROP TABLE IF EXISTS institution_business_income_calculation_lines;
DROP TABLE IF EXISTS institution_business_income_calculation_revisions;
