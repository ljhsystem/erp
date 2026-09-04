UPDATE system_statutory_standards
SET value_data=JSON_SET(
      value_data,
      '$.calculation_policy',JSON_OBJECT('method','TRUNCATE','discard_below_unit',10)
    ),updated_at=NOW(),updated_by='SYSTEM:MIGRATION'
WHERE standard_type_code='HEALTH_INSURANCE'
  AND effective_from='2013-01-01' AND effective_to='2013-12-31'
  AND JSON_EXTRACT(value_data,'$.calculation_policy.method') IS NULL;

UPDATE system_statutory_standards
SET value_data=JSON_SET(
      value_data,
      '$.calculation_policy.method','TRUNCATE',
      '$.calculation_policy.discard_below_unit',10
    ),updated_at=NOW(),updated_by='SYSTEM:MIGRATION'
WHERE standard_type_code='LONG_TERM_CARE'
  AND effective_from='2010-01-01' AND effective_to='2017-12-31'
  AND JSON_EXTRACT(value_data,'$.calculation_policy.method') IS NULL;

INSERT IGNORE INTO system_statutory_standard_sources(
 id,standard_id,sort_no,organization_name,source_name,law_name,notice_no,published_at,source_url,note,created_at,created_by,updated_at,updated_by
)
SELECT 'a8202211-0001-4000-8000-000000000001',id,
 COALESCE((SELECT MAX(source_row.sort_no)+1 FROM system_statutory_standard_sources source_row WHERE source_row.standard_id=standard_row.id),1),
 '대한민국 정부','국민건강보험법 제107조 끝수 처리','국민건강보험법 제107조',NULL,NULL,
 'https://www.law.go.kr/LSW/lsLawLinkInfo.do?chrClsCd=010202&lsJoLnkSeq=1013122437',
 '국고금 관리법 제47조에 따라 보험료의 10원 미만 끝수를 계산하지 않는 근거',NOW(),'SYSTEM:MIGRATION',NOW(),'SYSTEM:MIGRATION'
FROM system_statutory_standards standard_row
WHERE standard_row.standard_type_code='HEALTH_INSURANCE' AND standard_row.effective_from='2013-01-01';

INSERT IGNORE INTO system_statutory_standard_sources(
 id,standard_id,sort_no,organization_name,source_name,law_name,notice_no,published_at,source_url,note,created_at,created_by,updated_at,updated_by
)
SELECT 'a8202211-0002-4000-8000-000000000002',id,
 COALESCE((SELECT MAX(source_row.sort_no)+1 FROM system_statutory_standard_sources source_row WHERE source_row.standard_id=standard_row.id),1),
 '대한민국 정부','노인장기요양보험법 제64조 준용규정','노인장기요양보험법 제64조',NULL,NULL,
 'https://www.law.go.kr/LSW/lsInfoP.do?lsiSeq=286217',
 '국민건강보험법 제107조와 국고금 관리법 제47조의 10원 미만 끝수 처리를 준용하는 근거',NOW(),'SYSTEM:MIGRATION',NOW(),'SYSTEM:MIGRATION'
FROM system_statutory_standards standard_row
WHERE standard_row.standard_type_code='LONG_TERM_CARE' AND standard_row.effective_from='2010-01-01';
