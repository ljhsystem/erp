-- 운영 적용 후 자동 Down 실행 금지. 승인된 복구 절차에서만 사용한다.
DROP TRIGGER IF EXISTS trg_client_tax_profile_no_overlap_update;
DROP TRIGGER IF EXISTS trg_client_tax_profile_no_overlap_insert;
DROP TABLE IF EXISTS system_client_tax_profiles;
DELETE FROM system_codes WHERE note='사업소득 소득자 세무 적격성 SSOT' AND code_group IN ('TAXPAYER_ENTITY_TYPE','RESIDENCY_STATUS','INCOME_RECIPIENT_TYPE','WITHHOLDING_POLICY','CLIENT_TAX_PROFILE_VERIFICATION');
