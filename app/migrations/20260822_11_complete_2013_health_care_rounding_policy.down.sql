DELETE FROM system_statutory_standard_sources
WHERE id IN('a8202211-0001-4000-8000-000000000001','a8202211-0002-4000-8000-000000000002');

UPDATE system_statutory_standards
SET value_data=JSON_REMOVE(value_data,'$.calculation_policy.method','$.calculation_policy.discard_below_unit'),
    updated_at=NOW(),updated_by='SYSTEM:MIGRATION'
WHERE (standard_type_code='HEALTH_INSURANCE' AND effective_from='2013-01-01' AND effective_to='2013-12-31')
   OR (standard_type_code='LONG_TERM_CARE' AND effective_from='2010-01-01' AND effective_to='2017-12-31');
