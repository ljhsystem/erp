UPDATE system_codes
SET extra_data=JSON_SET(
      extra_data,
      '$.calculation_policy',COALESCE(JSON_EXTRACT(extra_data,'$.calculation_policy'),JSON_OBJECT('fields',JSON_ARRAY())),
      '$.calculation_policy.fields',COALESCE(JSON_EXTRACT(extra_data,'$.calculation_policy.fields'),JSON_ARRAY()),
      '$.preserve_schema_in_value',TRUE
    ),updated_at=NOW(),updated_by='SYSTEM:MIGRATION'
WHERE code_group='STATUTORY_STANDARD_TYPE' AND code IN('HEALTH_INSURANCE','LONG_TERM_CARE');

UPDATE system_codes
SET extra_data=JSON_ARRAY_APPEND(extra_data,'$.calculation_policy.fields',
      JSON_OBJECT('code','method','name','계산 처리방법','type','rounding','required',TRUE)),
    updated_at=NOW(),updated_by='SYSTEM:MIGRATION'
WHERE code_group='STATUTORY_STANDARD_TYPE' AND code IN('HEALTH_INSURANCE','LONG_TERM_CARE')
  AND JSON_SEARCH(extra_data,'one','method',NULL,'$.calculation_policy.fields[*].code') IS NULL;

UPDATE system_codes
SET extra_data=JSON_ARRAY_APPEND(extra_data,'$.calculation_policy.fields',
      JSON_OBJECT('code','discard_below_unit','name','버림 기준단위','type','number','required',TRUE,'min',0,'unit_label','원')),
    updated_at=NOW(),updated_by='SYSTEM:MIGRATION'
WHERE code_group='STATUTORY_STANDARD_TYPE' AND code IN('HEALTH_INSURANCE','LONG_TERM_CARE')
  AND JSON_SEARCH(extra_data,'one','discard_below_unit',NULL,'$.calculation_policy.fields[*].code') IS NULL;

UPDATE system_statutory_standards standard_row
INNER JOIN system_codes type_code
  ON type_code.code_group='STATUTORY_STANDARD_TYPE' AND type_code.code=standard_row.standard_type_code
SET standard_row.value_data=JSON_SET(
      standard_row.value_data,
      '$.calculation_policy.method','TRUNCATE',
      '$.calculation_policy.discard_below_unit',10,
      '$._schema',JSON_OBJECT(
        'version',4,
        'fields',JSON_EXTRACT(type_code.extra_data,'$.fields'),
        'calculation_policy',JSON_OBJECT('fields',JSON_EXTRACT(type_code.extra_data,'$.calculation_policy.fields'))
      )
    ),standard_row.updated_at=NOW(),standard_row.updated_by='SYSTEM:MIGRATION'
WHERE standard_row.standard_type_code IN('HEALTH_INSURANCE','LONG_TERM_CARE')
  AND standard_row.effective_from='2026-01-01' AND standard_row.effective_to='2026-12-31';

INSERT IGNORE INTO system_statutory_standard_sources(
 id,standard_id,sort_no,organization_name,source_name,law_name,notice_no,published_at,source_url,
 file_path,file_name,file_size,mime_type,note,created_at,created_by,updated_at,updated_by
)
SELECT 'a8202206-0001-4000-8000-000000000001',id,
 COALESCE((SELECT MAX(source_row.sort_no)+1 FROM system_statutory_standard_sources source_row WHERE source_row.standard_id=standard_row.id),1),
 '대한민국 정부','국민건강보험법 제107조 끝수 처리','국민건강보험법 제107조',NULL,NULL,
 'https://www.law.go.kr/LSW/lsLawLinkInfo.do?chrClsCd=010202&lsJoLnkSeq=1013122437',
 NULL,NULL,NULL,NULL,'보험료 계산 시 국고금 관리법 제47조에 따른 끝수를 계산하지 않는 근거',
 NOW(),'SYSTEM:MIGRATION',NOW(),'SYSTEM:MIGRATION'
FROM system_statutory_standards standard_row
WHERE standard_row.standard_type_code='HEALTH_INSURANCE' AND standard_row.effective_from='2026-01-01';

INSERT IGNORE INTO system_statutory_standard_sources(
 id,standard_id,sort_no,organization_name,source_name,law_name,notice_no,published_at,source_url,
 file_path,file_name,file_size,mime_type,note,created_at,created_by,updated_at,updated_by
)
SELECT 'a8202206-0002-4000-8000-000000000002',id,
 COALESCE((SELECT MAX(source_row.sort_no)+1 FROM system_statutory_standard_sources source_row WHERE source_row.standard_id=standard_row.id),1),
 '대한민국 정부','국고금 관리법 제47조 국고금의 끝수 계산','국고금 관리법 제47조',NULL,NULL,
 'https://www.law.go.kr/LSW/lsLinkCommonInfo.do?lsJoLnkSeq=1031453581',
 NULL,NULL,NULL,NULL,'10원 미만의 끝수를 계산하지 않는 기준',
 NOW(),'SYSTEM:MIGRATION',NOW(),'SYSTEM:MIGRATION'
FROM system_statutory_standards standard_row
WHERE standard_row.standard_type_code='HEALTH_INSURANCE' AND standard_row.effective_from='2026-01-01';

INSERT IGNORE INTO system_statutory_standard_sources(
 id,standard_id,sort_no,organization_name,source_name,law_name,notice_no,published_at,source_url,
 file_path,file_name,file_size,mime_type,note,created_at,created_by,updated_at,updated_by
)
SELECT 'a8202206-0003-4000-8000-000000000003',id,
 COALESCE((SELECT MAX(source_row.sort_no)+1 FROM system_statutory_standard_sources source_row WHERE source_row.standard_id=standard_row.id),1),
 '대한민국 정부','노인장기요양보험법 제64조 준용규정','노인장기요양보험법 제64조',NULL,NULL,
 'https://www.law.go.kr/LSW/lsInfoP.do?lsiSeq=286217',
 NULL,NULL,NULL,NULL,'국민건강보험법 제107조를 장기요양보험료 단수처리에 준용하는 근거',
 NOW(),'SYSTEM:MIGRATION',NOW(),'SYSTEM:MIGRATION'
FROM system_statutory_standards standard_row
WHERE standard_row.standard_type_code='LONG_TERM_CARE' AND standard_row.effective_from='2026-01-01';

INSERT IGNORE INTO system_statutory_standard_sources(
 id,standard_id,sort_no,organization_name,source_name,law_name,notice_no,published_at,source_url,
 file_path,file_name,file_size,mime_type,note,created_at,created_by,updated_at,updated_by
)
SELECT 'a8202206-0004-4000-8000-000000000004',id,
 COALESCE((SELECT MAX(source_row.sort_no)+1 FROM system_statutory_standard_sources source_row WHERE source_row.standard_id=standard_row.id),1),
 '대한민국 정부','국민건강보험법 제107조 끝수 처리','국민건강보험법 제107조',NULL,NULL,
 'https://www.law.go.kr/LSW/lsLawLinkInfo.do?chrClsCd=010202&lsJoLnkSeq=1013122437',
 NULL,NULL,NULL,NULL,'장기요양보험법 제64조가 준용하는 보험료 끝수 처리 조문',
 NOW(),'SYSTEM:MIGRATION',NOW(),'SYSTEM:MIGRATION'
FROM system_statutory_standards standard_row
WHERE standard_row.standard_type_code='LONG_TERM_CARE' AND standard_row.effective_from='2026-01-01';

INSERT IGNORE INTO system_statutory_standard_sources(
 id,standard_id,sort_no,organization_name,source_name,law_name,notice_no,published_at,source_url,
 file_path,file_name,file_size,mime_type,note,created_at,created_by,updated_at,updated_by
)
SELECT 'a8202206-0005-4000-8000-000000000005',id,
 COALESCE((SELECT MAX(source_row.sort_no)+1 FROM system_statutory_standard_sources source_row WHERE source_row.standard_id=standard_row.id),1),
 '대한민국 정부','국고금 관리법 제47조 국고금의 끝수 계산','국고금 관리법 제47조',NULL,NULL,
 'https://www.law.go.kr/LSW/lsLinkCommonInfo.do?lsJoLnkSeq=1031453581',
 NULL,NULL,NULL,NULL,'준용되는 10원 미만 끝수 미계산 기준',
 NOW(),'SYSTEM:MIGRATION',NOW(),'SYSTEM:MIGRATION'
FROM system_statutory_standards standard_row
WHERE standard_row.standard_type_code='LONG_TERM_CARE' AND standard_row.effective_from='2026-01-01';
