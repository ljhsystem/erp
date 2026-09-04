UPDATE system_statutory_standards
SET value_data=JSON_REMOVE(value_data,'$.calculation_policy'),
 updated_at=NOW(),updated_by='SYSTEM:MIGRATION'
WHERE standard_type_code='HEALTH_INSURANCE'
 AND effective_from='2026-01-01' AND effective_to='2026-12-31'
 AND JSON_UNQUOTE(JSON_EXTRACT(value_data,'$.calculation_policy.method'))='TRUNCATE'
 AND JSON_EXTRACT(value_data,'$.calculation_policy.discard_below_unit')=10;
