-- 운영 적용 후 자동 Down 실행 금지. 승인된 복구 절차에서만 사용한다.
DROP TRIGGER IF EXISTS trg_business_income_evidence_canonical_insert;
DROP TABLE IF EXISTS ledger_evidence_business_income;
