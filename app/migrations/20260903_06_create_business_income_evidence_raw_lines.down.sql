-- 운영 적용 후 자동 Down 실행 금지. 승인된 복구 절차에서만 사용한다.
ALTER TABLE institution_business_income_artifact_links DROP FOREIGN KEY fk_business_income_artifact_transaction;
ALTER TABLE institution_business_income_artifact_links DROP FOREIGN KEY fk_business_income_artifact_evidence;
DROP TABLE IF EXISTS ledger_evidence_business_income_raw_lines;
