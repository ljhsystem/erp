DELETE FROM system_statutory_standard_sources
WHERE id IN(
  'a8202205-0001-4000-8000-000000000001',
  'a8202205-0002-4000-8000-000000000002',
  'a8202205-0003-4000-8000-000000000003'
);

UPDATE system_codes
SET extra_data = JSON_REMOVE(
      extra_data,
      JSON_UNQUOTE(JSON_SEARCH(extra_data,'one','qualification_month_rule_code',NULL,'$.fields[*].code')),
      JSON_UNQUOTE(JSON_SEARCH(extra_data,'one','result_limit_application_stage',NULL,'$.fields[*].code')),
      JSON_UNQUOTE(JSON_SEARCH(extra_data,'one','maximum_result_amount',NULL,'$.fields[*].code')),
      JSON_UNQUOTE(JSON_SEARCH(extra_data,'one','minimum_result_amount',NULL,'$.fields[*].code'))
    ),
    updated_at=NOW(),updated_by='SYSTEM:MIGRATION'
WHERE code_group='STATUTORY_STANDARD_TYPE' AND code='HEALTH_INSURANCE';

UPDATE system_statutory_standards standard_row
INNER JOIN system_codes type_code
  ON type_code.code_group='STATUTORY_STANDARD_TYPE' AND type_code.code=standard_row.standard_type_code
SET standard_row.value_data = JSON_SET(
      JSON_REMOVE(
        standard_row.value_data,
        '$.minimum_result_amount','$.maximum_result_amount',
        '$.result_limit_application_stage','$.qualification_month_rule_code'
      ),
      '$._schema',JSON_OBJECT(
        'version',2,
        'fields',JSON_EXTRACT(type_code.extra_data,'$.fields'),
        'calculation_policy',JSON_OBJECT(
          'fields',COALESCE(JSON_EXTRACT(type_code.extra_data,'$.calculation_policy.fields'),JSON_ARRAY())
        )
      )
    ),
    standard_row.note='2026년 직장가입자 건강보험료율',
    standard_row.updated_at=NOW(),standard_row.updated_by='SYSTEM:MIGRATION'
WHERE standard_row.standard_type_code='HEALTH_INSURANCE'
  AND standard_row.effective_from='2026-01-01'
  AND standard_row.effective_to='2026-12-31';
