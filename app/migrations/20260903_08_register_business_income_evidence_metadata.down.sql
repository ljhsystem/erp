-- 운영 적용 후 자동 Down 실행 금지. 승인된 복구 절차에서만 사용한다.
UPDATE system_user_settings SET page_key='evidence-business-income',updated_at=NOW() WHERE setting_type='TABLE' AND page_key='evidence-business-income-report';
UPDATE system_codes SET is_active=1,note='업무데이터형',updated_at=NOW(),updated_by='SYSTEM:MIGRATION' WHERE code_group='IMPORT_TYPE' AND code='BUSINESS_INCOME';
UPDATE system_codes SET code='BUSINESS_INCOME',updated_at=NOW(),updated_by='SYSTEM:MIGRATION'
WHERE code_group='IMPORT_TYPE' AND code='BUSINESS_INCOME_REPORT'
  AND NOT EXISTS(SELECT 1 FROM (SELECT id FROM system_codes WHERE code_group='IMPORT_TYPE' AND code='BUSINESS_INCOME') legacy);
DELETE FROM ledger_evidence_metadata WHERE import_type='BUSINESS_INCOME_REPORT' AND created_by='SYSTEM:MIGRATION';
