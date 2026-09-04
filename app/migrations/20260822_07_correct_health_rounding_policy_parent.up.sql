UPDATE system_statutory_standards standard_row
INNER JOIN system_codes type_code
 ON type_code.code_group='STATUTORY_STANDARD_TYPE' AND type_code.code=standard_row.standard_type_code
SET standard_row.value_data=JSON_SET(
      standard_row.value_data,
      '$.calculation_policy',JSON_OBJECT('method','TRUNCATE','discard_below_unit',10),
      '$._schema',JSON_OBJECT(
        'version',4,
        'fields',JSON_EXTRACT(type_code.extra_data,'$.fields'),
        'calculation_policy',JSON_OBJECT('fields',JSON_EXTRACT(type_code.extra_data,'$.calculation_policy.fields'))
      )
    ),standard_row.updated_at=NOW(),standard_row.updated_by='SYSTEM:MIGRATION'
WHERE standard_row.standard_type_code='HEALTH_INSURANCE'
 AND standard_row.effective_from='2026-01-01' AND standard_row.effective_to='2026-12-31'
 AND JSON_EXTRACT(standard_row.value_data,'$.calculation_policy') IS NULL;
