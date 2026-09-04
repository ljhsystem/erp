INSERT INTO institution_employment_contracts_pay_components (
  id, sort_no, component_code, component_name, component_type,
  default_calculation_type, default_tax_type, tax_policy_code,
  ordinary_wage_treatment, average_wage_treatment, minimum_wage_treatment,
  is_active, effective_from, effective_to, note,
  created_at, created_by, updated_at, updated_by, deleted_at, deleted_by
)
SELECT
  '0d852729-d6cb-4d4d-9b09-758b2ec3f110',
  COALESCE(MAX(component.sort_no), 0) + 10,
  'OTHER_PAY', '기타', 'OTHER_WAGE',
  'FIXED_AMOUNT', 'TAXABLE', NULL,
  'REVIEW_REQUIRED', 'INCLUDED', 'REVIEW_REQUIRED',
  1, '2013-01-01', NULL,
  '상용근로소득 기타 증액·감액용 과세 지급항목',
  NOW(), 'SYSTEM:MIGRATION', NOW(), 'SYSTEM:MIGRATION', NULL, NULL
FROM institution_employment_contracts_pay_components component
WHERE NOT EXISTS (
  SELECT 1 FROM institution_employment_contracts_pay_components existing
  WHERE existing.id = '0d852729-d6cb-4d4d-9b09-758b2ec3f110'
     OR existing.component_code = 'OTHER_PAY'
     OR (existing.component_name = '기타' AND existing.component_type = 'OTHER_WAGE')
);
