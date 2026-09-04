UPDATE system_codes
SET extra_data = JSON_SET(
      extra_data,
      '$.calculation_policy.fields', JSON_ARRAY(
        JSON_OBJECT('code','method','name','끝수 처리방법','type','rounding','required',TRUE),
        JSON_OBJECT('code','discard_below_unit','name','버림 기준 단위','type','number','required',TRUE,'min',1,'unit_label','원'),
        JSON_OBJECT('code','stage','name','계산정책 적용단계','type','select','required',TRUE,'options',JSON_ARRAY(
          JSON_OBJECT('value','AFTER_RATE_APPLICATION','label','보수에 보험료율을 적용한 후')
        )),
        JSON_OBJECT('code','base_value_code','name','계산 기초값','type','select','required',TRUE,'options',JSON_ARRAY(
          JSON_OBJECT('value','INSURABLE_REMUNERATION','label','비과세 근로소득을 제외한 보수')
        )),
        JSON_OBJECT('code','aggregation_unit','name','계산 집계단위','type','select','required',TRUE,'options',JSON_ARRAY(
          JSON_OBJECT('value','INSURED_PERSON_PAYMENT','label','피보험자별 보수 지급 건')
        )),
        JSON_OBJECT('code','application_order','name','정책 적용순서','type','number','required',TRUE,'min',1),
        JSON_OBJECT('code','qualification_rule_code','name','피보험자격 적용규칙','type','select','required',TRUE,'options',JSON_ARRAY(
          JSON_OBJECT('value','CONFIRMED_COVERAGE_EXCLUSION_OVERRIDES','label','확정된 적용제외 정보가 있으면 제외')
        ))
      ),
      '$.preserve_schema_in_value', TRUE
    ),
    updated_at = NOW(),
    updated_by = 'SYSTEM:EMPLOYMENT_INSURANCE_POLICY_CLOSURE'
WHERE code_group = 'STATUTORY_STANDARD_TYPE'
  AND code = 'EMPLOYMENT_INSURANCE';

UPDATE system_statutory_standards standard_row
INNER JOIN system_codes type_code
  ON type_code.code_group = 'STATUTORY_STANDARD_TYPE'
 AND type_code.code = standard_row.standard_type_code
SET standard_row.value_data = JSON_SET(
      standard_row.value_data,
      '$.calculation_policy', JSON_OBJECT(
        'method','TRUNCATE',
        'discard_below_unit',10,
        'stage','AFTER_RATE_APPLICATION',
        'base_value_code','INSURABLE_REMUNERATION',
        'aggregation_unit','INSURED_PERSON_PAYMENT',
        'application_order',1,
        'qualification_rule_code','CONFIRMED_COVERAGE_EXCLUSION_OVERRIDES'
      ),
      '$._schema.version', 5,
      '$._schema.calculation_policy.fields', JSON_EXTRACT(type_code.extra_data, '$.calculation_policy.fields')
    ),
    standard_row.updated_at = NOW(),
    standard_row.updated_by = 'SYSTEM:EMPLOYMENT_INSURANCE_POLICY_CLOSURE'
WHERE standard_row.standard_type_code = 'EMPLOYMENT_INSURANCE';

INSERT IGNORE INTO system_statutory_standard_sources(
  id,standard_id,sort_no,organization_name,source_name,law_name,notice_no,published_at,
  source_url,file_path,file_name,file_size,mime_type,note,created_at,created_by,updated_at,updated_by
)
SELECT
  CONCAT('a8240001-', LPAD(ROW_NUMBER() OVER (ORDER BY standard_row.effective_from), 4, '0'), '-4000-8000-000000000001'),
  standard_row.id,
  COALESCE((SELECT MAX(source_row.sort_no) + 1
            FROM system_statutory_standard_sources source_row
            WHERE source_row.standard_id = standard_row.id), 1),
  '근로복지공단',
  '산재·고용보험 가입 및 부과업무 실무편람',
  '고용보험 및 산업재해보상보험의 보험료징수 등에 관한 법률 제16조 및 같은 법 시행령 제19조',
  NULL,NULL,NULL,NULL,NULL,NULL,NULL,
  '근로자별 월 보험료와 보수 지급 시 근로자 부담금은 10원 미만을 버리는 계산정책의 공식 실무 근거',
  NOW(),'SYSTEM:EMPLOYMENT_INSURANCE_POLICY_CLOSURE',NOW(),'SYSTEM:EMPLOYMENT_INSURANCE_POLICY_CLOSURE'
FROM system_statutory_standards standard_row
WHERE standard_row.standard_type_code = 'EMPLOYMENT_INSURANCE';
