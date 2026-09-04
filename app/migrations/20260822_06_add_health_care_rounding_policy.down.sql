DELETE FROM system_statutory_standard_sources
WHERE id IN(
 'a8202206-0001-4000-8000-000000000001','a8202206-0002-4000-8000-000000000002',
 'a8202206-0003-4000-8000-000000000003','a8202206-0004-4000-8000-000000000004',
 'a8202206-0005-4000-8000-000000000005'
);

UPDATE system_codes
SET extra_data=JSON_REMOVE(
      extra_data,
      JSON_UNQUOTE(JSON_SEARCH(extra_data,'one','discard_below_unit',NULL,'$.calculation_policy.fields[*].code')),
      JSON_UNQUOTE(JSON_SEARCH(extra_data,'one','method',NULL,'$.calculation_policy.fields[*].code'))
    ),updated_at=NOW(),updated_by='SYSTEM:MIGRATION'
WHERE code_group='STATUTORY_STANDARD_TYPE' AND code IN('HEALTH_INSURANCE','LONG_TERM_CARE');

UPDATE system_statutory_standards standard_row
INNER JOIN system_codes type_code
 ON type_code.code_group='STATUTORY_STANDARD_TYPE' AND type_code.code=standard_row.standard_type_code
SET standard_row.value_data=JSON_SET(
      JSON_REMOVE(standard_row.value_data,'$.calculation_policy.method','$.calculation_policy.discard_below_unit'),
      '$._schema',JSON_OBJECT(
        'version',3,
        'fields',JSON_EXTRACT(type_code.extra_data,'$.fields'),
        'calculation_policy',JSON_OBJECT(
          'fields',COALESCE(JSON_EXTRACT(type_code.extra_data,'$.calculation_policy.fields'),JSON_ARRAY())
        )
      )
    ),standard_row.updated_at=NOW(),standard_row.updated_by='SYSTEM:MIGRATION'
WHERE standard_row.standard_type_code IN('HEALTH_INSURANCE','LONG_TERM_CARE')
 AND standard_row.effective_from='2026-01-01' AND standard_row.effective_to='2026-12-31';
