-- 기존 BUSINESS_INCOME 요청은 API 읽기 경계의 Alias로만 유지한다.
UPDATE system_codes SET code='BUSINESS_INCOME_REPORT',code_name='사업소득',updated_at=NOW(),updated_by='SYSTEM:MIGRATION'
WHERE code_group='IMPORT_TYPE' AND code='BUSINESS_INCOME'
  AND NOT EXISTS(SELECT 1 FROM system_codes canonical WHERE canonical.code_group='IMPORT_TYPE' AND canonical.code='BUSINESS_INCOME_REPORT');

UPDATE system_codes SET is_active=0,note='Legacy API 읽기 호환 Alias. 신규 저장 금지',updated_at=NOW(),updated_by='SYSTEM:MIGRATION'
WHERE code_group='IMPORT_TYPE' AND code='BUSINESS_INCOME'
  AND EXISTS(SELECT 1 FROM (SELECT id FROM system_codes WHERE code_group='IMPORT_TYPE' AND code='BUSINESS_INCOME_REPORT') canonical);

UPDATE system_user_settings
SET page_key='evidence-business-income-report',updated_at=NOW()
WHERE setting_type='TABLE' AND page_key='evidence-business-income';

INSERT INTO ledger_evidence_metadata (id,sort_no,import_type,source_table,evidence_type,process_role,transaction_cardinality,created_at,created_by,updated_at,updated_by)
SELECT UUID(),COALESCE(MAX(sort_no),0)+1,'BUSINESS_INCOME_REPORT','ledger_evidence_business_income','DATA','TRANSACTION_REPORT_SSOT','SINGLE_TRANSACTION',NOW(),'SYSTEM:MIGRATION',NOW(),'SYSTEM:MIGRATION'
FROM ledger_evidence_metadata
WHERE NOT EXISTS(SELECT 1 FROM ledger_evidence_metadata WHERE import_type='BUSINESS_INCOME_REPORT' AND deleted_at IS NULL);
