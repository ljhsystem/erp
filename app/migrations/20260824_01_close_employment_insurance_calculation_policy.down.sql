DELETE FROM system_statutory_standard_sources
WHERE id LIKE 'a8240001-%';

UPDATE system_statutory_standards
SET value_data = JSON_REMOVE(value_data, '$.calculation_policy'),
    updated_at = NOW(),
    updated_by = 'SYSTEM:EMPLOYMENT_INSURANCE_POLICY_CLOSURE_ROLLBACK'
WHERE standard_type_code = 'EMPLOYMENT_INSURANCE'
  AND JSON_UNQUOTE(JSON_EXTRACT(value_data, '$.calculation_policy.qualification_rule_code')) = 'CONFIRMED_COVERAGE_EXCLUSION_OVERRIDES';

UPDATE system_codes
SET extra_data = JSON_SET(extra_data, '$.calculation_policy.fields', JSON_ARRAY()),
    updated_at = NOW(),
    updated_by = 'SYSTEM:EMPLOYMENT_INSURANCE_POLICY_CLOSURE_ROLLBACK'
WHERE code_group = 'STATUTORY_STANDARD_TYPE'
  AND code = 'EMPLOYMENT_INSURANCE';
