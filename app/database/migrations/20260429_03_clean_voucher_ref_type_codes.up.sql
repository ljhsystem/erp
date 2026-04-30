UPDATE system_codes
SET deleted_at = NOW(),
    deleted_by = 'SYSTEM:migration',
    is_active = 0
WHERE code_group = 'REF_TYPE'
  AND deleted_at IS NULL
  AND (
    code IN ('CLIENT', 'PROJECT', 'EMPLOYEE', 'ACCOUNT', 'CARD')
    OR code_name IN ('거래처', '프로젝트', '직원', '계좌', '카드')
  );

