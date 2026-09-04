UPDATE system_codes
SET extra_data = JSON_ARRAY_APPEND(extra_data, '$.fields',
        JSON_OBJECT('code','minimum_result_amount','name','계산 결과금액 하한','type','amount','required',FALSE)),
    updated_at = NOW(), updated_by = 'SYSTEM:MIGRATION'
WHERE code_group = 'STATUTORY_STANDARD_TYPE'
  AND code = 'HEALTH_INSURANCE'
  AND JSON_SEARCH(extra_data, 'one', 'minimum_result_amount', NULL, '$.fields[*].code') IS NULL;

UPDATE system_codes
SET extra_data = JSON_ARRAY_APPEND(extra_data, '$.fields',
        JSON_OBJECT('code','maximum_result_amount','name','계산 결과금액 상한','type','amount','required',FALSE)),
    updated_at = NOW(), updated_by = 'SYSTEM:MIGRATION'
WHERE code_group = 'STATUTORY_STANDARD_TYPE'
  AND code = 'HEALTH_INSURANCE'
  AND JSON_SEARCH(extra_data, 'one', 'maximum_result_amount', NULL, '$.fields[*].code') IS NULL;

UPDATE system_codes
SET extra_data = JSON_ARRAY_APPEND(extra_data, '$.fields',
        JSON_OBJECT(
          'code','result_limit_application_stage','name','계산 결과한도 적용단계','type','select','required',FALSE,
          'options',JSON_ARRAY(
            JSON_OBJECT('value','AFTER_PREMIUM_CALCULATION','label','보험료 계산 후'),
            JSON_OBJECT('value','AFTER_ROUNDING','label','끝수처리 후')
          )
        )),
    updated_at = NOW(), updated_by = 'SYSTEM:MIGRATION'
WHERE code_group = 'STATUTORY_STANDARD_TYPE'
  AND code = 'HEALTH_INSURANCE'
  AND JSON_SEARCH(extra_data, 'one', 'result_limit_application_stage', NULL, '$.fields[*].code') IS NULL;

UPDATE system_codes
SET extra_data = JSON_ARRAY_APPEND(extra_data, '$.fields',
        JSON_OBJECT(
          'code','qualification_month_rule_code','name','월중 자격변동 적용정책','type','select','required',FALSE,
          'options',JSON_ARRAY(
            JSON_OBJECT(
              'value','FIRST_DAY_CHANGE_USES_NEW_STATUS_OTHERWISE_PREVIOUS_STATUS',
              'label','1일 변동은 변경 후 자격, 그 외 월중 변동은 변경 전 자격'
            )
          )
        )),
    updated_at = NOW(), updated_by = 'SYSTEM:MIGRATION'
WHERE code_group = 'STATUTORY_STANDARD_TYPE'
  AND code = 'HEALTH_INSURANCE'
  AND JSON_SEARCH(extra_data, 'one', 'qualification_month_rule_code', NULL, '$.fields[*].code') IS NULL;

UPDATE system_codes
SET extra_data = JSON_SET(extra_data, '$.preserve_schema_in_value', TRUE),
    updated_at = NOW(), updated_by = 'SYSTEM:MIGRATION'
WHERE code_group = 'STATUTORY_STANDARD_TYPE' AND code = 'HEALTH_INSURANCE';

UPDATE system_statutory_standards standard_row
INNER JOIN system_codes type_code
  ON type_code.code_group = 'STATUTORY_STANDARD_TYPE'
 AND type_code.code = standard_row.standard_type_code
SET standard_row.value_data = JSON_SET(
      standard_row.value_data,
      '$.employee_rate', 0.03595,
      '$.employer_rate', 0.03595,
      '$.minimum_result_amount', 20160,
      '$.maximum_result_amount', 9183480,
      '$.qualification_month_rule_code', 'FIRST_DAY_CHANGE_USES_NEW_STATUS_OTHERWISE_PREVIOUS_STATUS',
      '$._schema', JSON_OBJECT(
        'version', 3,
        'fields', JSON_EXTRACT(type_code.extra_data, '$.fields'),
        'calculation_policy', JSON_OBJECT(
          'fields', COALESCE(JSON_EXTRACT(type_code.extra_data, '$.calculation_policy.fields'), JSON_ARRAY())
        )
      )
    ),
    standard_row.note = '2026년 직장가입자 건강보험료율과 월 보험료 결과 상·하한 및 월중 자격변동 정책',
    standard_row.updated_at = NOW(), standard_row.updated_by = 'SYSTEM:MIGRATION'
WHERE standard_row.standard_type_code = 'HEALTH_INSURANCE'
  AND standard_row.effective_from = '2026-01-01'
  AND standard_row.effective_to = '2026-12-31';

INSERT IGNORE INTO system_statutory_standard_sources(
  id,standard_id,sort_no,organization_name,source_name,law_name,notice_no,published_at,
  source_url,file_path,file_name,file_size,mime_type,note,created_at,created_by,updated_at,updated_by
)
SELECT 'a8202205-0001-4000-8000-000000000001',id,
  COALESCE((SELECT MAX(source_row.sort_no) + 1 FROM system_statutory_standard_sources source_row WHERE source_row.standard_id=standard_row.id),1),
  '국민건강보험공단','2026년 건강보험료율 안내','국민건강보험법 시행령',NULL,NULL,
  'https://edi.nhis.or.kr/portal/images/popup/20251204_pop01longdesc.html',NULL,NULL,NULL,NULL,
  '2026년 직장가입자 건강보험료율 7.19%, 근로자·사용자 각각 3.595%',NOW(),'SYSTEM:MIGRATION',NOW(),'SYSTEM:MIGRATION'
FROM system_statutory_standards standard_row
WHERE standard_row.standard_type_code='HEALTH_INSURANCE' AND standard_row.effective_from='2026-01-01';

INSERT IGNORE INTO system_statutory_standard_sources(
  id,standard_id,sort_no,organization_name,source_name,law_name,notice_no,published_at,
  source_url,file_path,file_name,file_size,mime_type,note,created_at,created_by,updated_at,updated_by
)
SELECT 'a8202205-0002-4000-8000-000000000002',id,
  COALESCE((SELECT MAX(source_row.sort_no) + 1 FROM system_statutory_standard_sources source_row WHERE source_row.standard_id=standard_row.id),1),
  '국민건강보험공단','국민건강보험 관련 법령','국민건강보험법',NULL,NULL,
  'https://www.nhis.or.kr/lm/lmxsrv/law/lawFullContent.do?MODE=twoView&SEQ=27&SEQ_HISTORY=595288',NULL,NULL,NULL,NULL,
  '직장가입자 월별 보험료 산정과 월중 자격변동 적용근거',NOW(),'SYSTEM:MIGRATION',NOW(),'SYSTEM:MIGRATION'
FROM system_statutory_standards standard_row
WHERE standard_row.standard_type_code='HEALTH_INSURANCE' AND standard_row.effective_from='2026-01-01';

INSERT IGNORE INTO system_statutory_standard_sources(
  id,standard_id,sort_no,organization_name,source_name,law_name,notice_no,published_at,
  source_url,file_path,file_name,file_size,mime_type,note,created_at,created_by,updated_at,updated_by
)
SELECT 'a8202205-0003-4000-8000-000000000003',id,
  COALESCE((SELECT MAX(source_row.sort_no) + 1 FROM system_statutory_standard_sources source_row WHERE source_row.standard_id=standard_row.id),1),
  '보건복지부','월별 보험료액의 상한과 하한에 관한 고시','국민건강보험법',
  '보건복지부고시 제2025-222호','2025-12-24',
  'https://www.law.go.kr/LSW/conAdmrulByLsPop.do?admRulPttninfSeq=17390&datClsCd=010102&dguBun=DEG&joBrNo=00&joNo=0032&lsiSeq=244519',
  NULL,NULL,NULL,NULL,'2026년 직장가입자 월 보험료 결과 상한 9,183,480원, 하한 20,160원',
  NOW(),'SYSTEM:MIGRATION',NOW(),'SYSTEM:MIGRATION'
FROM system_statutory_standards standard_row
WHERE standard_row.standard_type_code='HEALTH_INSURANCE' AND standard_row.effective_from='2026-01-01';
