DELETE FROM system_statutory_standard_sources WHERE id LIKE 'a8240002-%' OR id LIKE 'a8240003-%';

UPDATE system_statutory_standards
SET value_data=JSON_REMOVE(value_data,
      '$.calculation_policy.automatic_fallback_base_value_code',
      '$.calculation_policy.pay_item_basis_rule_code'),
    updated_at=NOW(),updated_by='SYSTEM:REGULAR_INCOME_BASIS_CLOSURE_ROLLBACK'
WHERE standard_type_code='NATIONAL_PENSION';

UPDATE system_statutory_standards
SET value_data=JSON_REMOVE(value_data,
      '$.calculation_policy.stage','$.calculation_policy.base_value_code',
      '$.calculation_policy.automatic_fallback_base_value_code','$.calculation_policy.pay_item_basis_rule_code',
      '$.calculation_policy.aggregation_unit','$.calculation_policy.application_order'),
    updated_at=NOW(),updated_by='SYSTEM:REGULAR_INCOME_BASIS_CLOSURE_ROLLBACK'
WHERE standard_type_code='HEALTH_INSURANCE';

UPDATE system_codes
SET extra_data=JSON_REMOVE(extra_data,'$.calculation_policy.fields[5]','$.calculation_policy.fields[4]'),
    updated_at=NOW(),updated_by='SYSTEM:REGULAR_INCOME_BASIS_CLOSURE_ROLLBACK'
WHERE code_group='STATUTORY_STANDARD_TYPE' AND code='NATIONAL_PENSION';

UPDATE system_codes
SET extra_data=JSON_SET(extra_data,'$.calculation_policy.fields',JSON_ARRAY(
      JSON_OBJECT('code','method','name','계산 처리방법','type','rounding','required',TRUE),
      JSON_OBJECT('code','discard_below_unit','name','버림 기준단위','type','number','required',TRUE,'min',0,'unit_label','원')
    )),updated_at=NOW(),updated_by='SYSTEM:REGULAR_INCOME_BASIS_CLOSURE_ROLLBACK'
WHERE code_group='STATUTORY_STANDARD_TYPE' AND code='HEALTH_INSURANCE';
