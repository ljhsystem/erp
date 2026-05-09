UPDATE system_codes
SET deleted_at = NULL,
    deleted_by = NULL,
    is_active = 1
WHERE code_group = 'REF_TYPE'
  AND (
    code IN ('CLIENT', 'PROJECT', 'EMPLOYEE', 'ACCOUNT', 'CARD')
    OR code_name IN ('거래처', '프로젝트', '직원', '계좌', '카드')
  );

