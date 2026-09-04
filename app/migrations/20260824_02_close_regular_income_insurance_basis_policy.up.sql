UPDATE system_codes
SET extra_data=JSON_SET(extra_data,'$.calculation_policy.fields',JSON_ARRAY(
      JSON_OBJECT('code','method','name','계산 처리방법','type','rounding','required',TRUE),
      JSON_OBJECT('code','discard_below_unit','name','버림 기준단위','type','number','required',TRUE,'min',1,'unit_label','원'),
      JSON_OBJECT('code','stage','name','적용단계','type','select','required',TRUE,'options',JSON_ARRAY(
        JSON_OBJECT('value','ASSESSMENT_BASE','label','기준소득월액 확정 단계'))),
      JSON_OBJECT('code','base_value_code','name','계산기초','type','select','required',TRUE,'options',JSON_ARRAY(
        JSON_OBJECT('value','REPORTED_MONTHLY_INCOME','label','신고 기준소득월액'))),
      JSON_OBJECT('code','automatic_fallback_base_value_code','name','확정 기준이력 미등록 시 자동산출 기초','type','select','required',TRUE,'options',JSON_ARRAY(
        JSON_OBJECT('value','TAXABLE_PAY_ITEM_FINAL_AMOUNT','label','법정 비과세 근로소득을 제외한 지급항목 최종금액'))),
      JSON_OBJECT('code','pay_item_basis_rule_code','name','지급항목 포함·제외 정책','type','select','required',TRUE,'options',JSON_ARRAY(
        JSON_OBJECT('value','EXCLUDE_NON_TAXABLE_EMPLOYMENT_INCOME','label','소득세법상 비과세 근로소득 제외'))),
      JSON_OBJECT('code','aggregation_unit','name','집계단위','type','select','required',TRUE,'options',JSON_ARRAY(
        JSON_OBJECT('value','INSURED_PERSON_MONTH','label','가입자별 월'))),
      JSON_OBJECT('code','application_order','name','적용순서','type','number','required',TRUE,'min',1)
    ),'$.preserve_schema_in_value',TRUE),
    updated_at=NOW(),updated_by='SYSTEM:REGULAR_INCOME_BASIS_CLOSURE'
WHERE code_group='STATUTORY_STANDARD_TYPE' AND code='NATIONAL_PENSION';

UPDATE system_codes
SET extra_data=JSON_SET(extra_data,'$.calculation_policy.fields',JSON_ARRAY(
      JSON_OBJECT('code','method','name','계산 처리방법','type','rounding','required',TRUE),
      JSON_OBJECT('code','discard_below_unit','name','버림 기준단위','type','number','required',TRUE,'min',1,'unit_label','원'),
      JSON_OBJECT('code','stage','name','적용단계','type','select','required',TRUE,'options',JSON_ARRAY(
        JSON_OBJECT('value','AFTER_RATE_APPLICATION','label','보수월액에 보험료율을 적용한 후'))),
      JSON_OBJECT('code','base_value_code','name','계산기초','type','select','required',TRUE,'options',JSON_ARRAY(
        JSON_OBJECT('value','MONTHLY_REMUNERATION','label','확정 보수월액'))),
      JSON_OBJECT('code','automatic_fallback_base_value_code','name','확정 기준이력 미등록 시 자동산출 기초','type','select','required',TRUE,'options',JSON_ARRAY(
        JSON_OBJECT('value','TAXABLE_PAY_ITEM_FINAL_AMOUNT','label','법정 비과세 근로소득을 제외한 지급항목 최종금액'))),
      JSON_OBJECT('code','pay_item_basis_rule_code','name','지급항목 포함·제외 정책','type','select','required',TRUE,'options',JSON_ARRAY(
        JSON_OBJECT('value','EXCLUDE_NON_TAXABLE_EMPLOYMENT_INCOME_EXCEPT_STATUTORY_INCLUDED','label','비과세 근로소득 제외, 법정 포함 예외는 확정 보수월액으로 보정'))),
      JSON_OBJECT('code','aggregation_unit','name','집계단위','type','select','required',TRUE,'options',JSON_ARRAY(
        JSON_OBJECT('value','INSURED_PERSON_MONTH','label','가입자별 월'))),
      JSON_OBJECT('code','application_order','name','적용순서','type','number','required',TRUE,'min',1)
    ),'$.preserve_schema_in_value',TRUE),
    updated_at=NOW(),updated_by='SYSTEM:REGULAR_INCOME_BASIS_CLOSURE'
WHERE code_group='STATUTORY_STANDARD_TYPE' AND code='HEALTH_INSURANCE';

UPDATE system_statutory_standards standard_row
INNER JOIN system_codes type_code ON type_code.code_group='STATUTORY_STANDARD_TYPE' AND type_code.code=standard_row.standard_type_code
SET standard_row.value_data=JSON_SET(
      standard_row.value_data,
      '$.calculation_policy.automatic_fallback_base_value_code','TAXABLE_PAY_ITEM_FINAL_AMOUNT',
      '$.calculation_policy.pay_item_basis_rule_code','EXCLUDE_NON_TAXABLE_EMPLOYMENT_INCOME',
      '$._schema.version',6,
      '$._schema.calculation_policy.fields',JSON_EXTRACT(type_code.extra_data,'$.calculation_policy.fields')
    ),standard_row.updated_at=NOW(),standard_row.updated_by='SYSTEM:REGULAR_INCOME_BASIS_CLOSURE'
WHERE standard_row.standard_type_code='NATIONAL_PENSION';

UPDATE system_statutory_standards standard_row
INNER JOIN system_codes type_code ON type_code.code_group='STATUTORY_STANDARD_TYPE' AND type_code.code=standard_row.standard_type_code
SET standard_row.value_data=JSON_SET(
      standard_row.value_data,
      '$.calculation_policy.stage','AFTER_RATE_APPLICATION',
      '$.calculation_policy.base_value_code','MONTHLY_REMUNERATION',
      '$.calculation_policy.automatic_fallback_base_value_code','TAXABLE_PAY_ITEM_FINAL_AMOUNT',
      '$.calculation_policy.pay_item_basis_rule_code','EXCLUDE_NON_TAXABLE_EMPLOYMENT_INCOME_EXCEPT_STATUTORY_INCLUDED',
      '$.calculation_policy.aggregation_unit','INSURED_PERSON_MONTH',
      '$.calculation_policy.application_order',1,
      '$._schema.version',6,
      '$._schema.calculation_policy.fields',JSON_EXTRACT(type_code.extra_data,'$.calculation_policy.fields')
    ),standard_row.updated_at=NOW(),standard_row.updated_by='SYSTEM:REGULAR_INCOME_BASIS_CLOSURE'
WHERE standard_row.standard_type_code='HEALTH_INSURANCE';

INSERT IGNORE INTO system_statutory_standard_sources(
 id,standard_id,sort_no,organization_name,source_name,law_name,notice_no,published_at,source_url,
 file_path,file_name,file_size,mime_type,note,created_at,created_by,updated_at,updated_by
)
SELECT CONCAT('a8240002-',LPAD(ROW_NUMBER() OVER (ORDER BY standard_row.effective_from),4,'0'),'-4000-8000-000000000001'),
 standard_row.id,COALESCE((SELECT MAX(source_row.sort_no)+1 FROM system_statutory_standard_sources source_row WHERE source_row.standard_id=standard_row.id),1),
 '대한민국 정부','국민연금 기준소득월액의 소득 범위와 결정','국민연금법 시행령 제3조·제5조',NULL,NULL,
 'https://law.go.kr/LSW/lsInfoP.do?lsiSeq=141596',NULL,NULL,NULL,NULL,
 '근로소득에서 비과세 근로소득을 제외하고 신고 소득월액의 1,000원 미만을 버려 기준소득월액을 결정하는 근거',
 NOW(),'SYSTEM:REGULAR_INCOME_BASIS_CLOSURE',NOW(),'SYSTEM:REGULAR_INCOME_BASIS_CLOSURE'
FROM system_statutory_standards standard_row WHERE standard_row.standard_type_code='NATIONAL_PENSION';

INSERT IGNORE INTO system_statutory_standard_sources(
 id,standard_id,sort_no,organization_name,source_name,law_name,notice_no,published_at,source_url,
 file_path,file_name,file_size,mime_type,note,created_at,created_by,updated_at,updated_by
)
SELECT CONCAT('a8240003-',LPAD(ROW_NUMBER() OVER (ORDER BY standard_row.effective_from),4,'0'),'-4000-8000-000000000001'),
 standard_row.id,COALESCE((SELECT MAX(source_row.sort_no)+1 FROM system_statutory_standard_sources source_row WHERE source_row.standard_id=standard_row.id),1),
 '대한민국 정부','직장가입자 보수에 포함되는 금품과 제외항목','국민건강보험법 시행령 제33조',NULL,NULL,
 'https://www.law.go.kr/LSW/lsLawLinkInfo.do?chrClsCd=010202&lsJoLnkSeq=1000915535',NULL,NULL,NULL,NULL,
 '비과세 근로소득을 보수에서 제외하되 법령상 포함 예외는 확정 보수월액으로 보정하는 근거',
 NOW(),'SYSTEM:REGULAR_INCOME_BASIS_CLOSURE',NOW(),'SYSTEM:REGULAR_INCOME_BASIS_CLOSURE'
FROM system_statutory_standards standard_row WHERE standard_row.standard_type_code='HEALTH_INSURANCE';
