DELIMITER $$
CREATE PROCEDURE migrate_20260831_06_up()
BEGIN
    IF EXISTS (SELECT 1 FROM system_codes WHERE code_group IN(
        'STATUTORY_POLICY_COMPONENT','STATUTORY_EMPLOYMENT_TYPE','STATUTORY_WORK_SCOPE',
        'STATUTORY_CONDITION_COMBINATION','INSURANCE_ELIGIBILITY_DECISION','INSURANCE_ELIGIBILITY_RESULT',
        'INSURANCE_ELIGIBILITY_AGE_REFERENCE_DATE','INSURANCE_ELIGIBILITY_UNDER_AGE_POLICY',
        'INSURANCE_ELIGIBILITY_MONTH_JUDGMENT','INSURANCE_ELIGIBILITY_INCOME_BASIS',
        'INSURANCE_ELIGIBILITY_AGGREGATION_SCOPE','INSURANCE_ELIGIBILITY_AGGREGATION_PERIOD',
        'INSURANCE_ELIGIBILITY_TRANSITION_POLICY','INSURANCE_ELIGIBILITY_TRANSITION_STATUS',
        'STATUTORY_STANDARD_PERIOD_STATUS')) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='법정기준 선택코드 그룹이 이미 존재합니다.';
    END IF;
    IF (SELECT COUNT(*) FROM system_codes WHERE code_group='STATUTORY_STANDARD_TYPE'
        AND code IN('NATIONAL_PENSION','HEALTH_INSURANCE','LONG_TERM_CARE','EMPLOYMENT_INSURANCE','INDUSTRIAL_ACCIDENT')) <> 5 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='보험 법정기준 Type 5건이 필요합니다.';
    END IF;

    INSERT INTO system_codes(id,sort_no,code_group,group_name,code,code_name,extra_data,note,is_active,created_by,updated_by) VALUES
    ('20260831-0601-4000-8000-000000000001',96001,'STATUTORY_POLICY_COMPONENT','법정기준 정책 구성요소','PREMIUM','보험료',NULL,'법정기준 Header 정책 구성요소',1,'SYSTEM:MIGRATION','SYSTEM:MIGRATION'),
    ('20260831-0602-4000-8000-000000000002',96002,'STATUTORY_POLICY_COMPONENT','법정기준 정책 구성요소','ELIGIBILITY','가입자격',NULL,'법정기준 Header 정책 구성요소',1,'SYSTEM:MIGRATION','SYSTEM:MIGRATION'),
    ('20260831-0603-4000-8000-000000000003',96003,'STATUTORY_EMPLOYMENT_TYPE','법정기준 고용형태','ALL','전체',NULL,'법정기준 정책 고용형태',1,'SYSTEM:MIGRATION','SYSTEM:MIGRATION'),
    ('20260831-0604-4000-8000-000000000004',96004,'STATUTORY_EMPLOYMENT_TYPE','법정기준 고용형태','REGULAR','상용',NULL,'법정기준 정책 고용형태',1,'SYSTEM:MIGRATION','SYSTEM:MIGRATION'),
    ('20260831-0605-4000-8000-000000000005',96005,'STATUTORY_EMPLOYMENT_TYPE','법정기준 고용형태','DAILY','일용',NULL,'법정기준 정책 고용형태',1,'SYSTEM:MIGRATION','SYSTEM:MIGRATION'),
    ('20260831-0606-4000-8000-000000000006',96006,'STATUTORY_WORK_SCOPE','법정기준 업무 Scope','ALL','전체',NULL,'법정기준 정책 업무 Scope',1,'SYSTEM:MIGRATION','SYSTEM:MIGRATION'),
    ('20260831-0607-4000-8000-000000000007',96007,'STATUTORY_WORK_SCOPE','법정기준 업무 Scope','HEAD_OFFICE','본사',NULL,'HQ와 ECOMMERCE가 사용하는 법정 Scope',1,'SYSTEM:MIGRATION','SYSTEM:MIGRATION'),
    ('20260831-0608-4000-8000-000000000008',96008,'STATUTORY_WORK_SCOPE','법정기준 업무 Scope','CONSTRUCTION_SITE','건설현장',NULL,'건설 프로젝트 현장 법정 Scope',1,'SYSTEM:MIGRATION','SYSTEM:MIGRATION'),
    ('20260831-0609-4000-8000-000000000009',96009,'STATUTORY_CONDITION_COMBINATION','법정기준 조건 결합','ALL','모두 충족',NULL,'조건 결합',1,'SYSTEM:MIGRATION','SYSTEM:MIGRATION'),
    ('20260831-0610-4000-8000-000000000010',96010,'STATUTORY_CONDITION_COMBINATION','법정기준 조건 결합','ANY','하나 이상 충족',NULL,'조건 결합',1,'SYSTEM:MIGRATION','SYSTEM:MIGRATION'),
    ('20260831-0611-4000-8000-000000000011',96011,'STATUTORY_CONDITION_COMBINATION','법정기준 조건 결합','NONE','어느 것도 해당하지 않음',NULL,'조건 결합',1,'SYSTEM:MIGRATION','SYSTEM:MIGRATION'),
    ('20260831-0612-4000-8000-000000000012',96012,'INSURANCE_ELIGIBILITY_DECISION','가입자격 판정 방식','RULE_EVALUATION','조건 평가',NULL,'가입자격 판정 방식',1,'SYSTEM:MIGRATION','SYSTEM:MIGRATION'),
    ('20260831-0613-4000-8000-000000000013',96013,'INSURANCE_ELIGIBILITY_DECISION','가입자격 판정 방식','DEPENDENT_RESULT','다른 보험 결과 종속',NULL,'가입자격 판정 방식',1,'SYSTEM:MIGRATION','SYSTEM:MIGRATION'),
    ('20260831-0614-4000-8000-000000000014',96014,'INSURANCE_ELIGIBILITY_RESULT','가입자격 판정결과','ELIGIBLE','가입대상',NULL,'가입자격 결과',1,'SYSTEM:MIGRATION','SYSTEM:MIGRATION'),
    ('20260831-0615-4000-8000-000000000015',96015,'INSURANCE_ELIGIBILITY_RESULT','가입자격 판정결과','NOT_ELIGIBLE','가입제외',NULL,'가입자격 결과',1,'SYSTEM:MIGRATION','SYSTEM:MIGRATION'),
    ('20260831-0616-4000-8000-000000000016',96016,'INSURANCE_ELIGIBILITY_RESULT','가입자격 판정결과','CONFIRMATION_REQUIRED','확인 필요',NULL,'가입자격 결과',1,'SYSTEM:MIGRATION','SYSTEM:MIGRATION'),
    ('20260831-0617-4000-8000-000000000017',96017,'INSURANCE_ELIGIBILITY_AGE_REFERENCE_DATE','가입자격 연령 기준일','ATTRIBUTION_DATE','귀속일',NULL,'연령 판단 기준일',1,'SYSTEM:MIGRATION','SYSTEM:MIGRATION'),
    ('20260831-0618-4000-8000-000000000018',96018,'INSURANCE_ELIGIBILITY_UNDER_AGE_POLICY','가입자격 최소연령 미만 정책','EXCLUDED','가입제외',NULL,'최소연령 미만 처리',1,'SYSTEM:MIGRATION','SYSTEM:MIGRATION'),
    ('20260831-0619-4000-8000-000000000019',96019,'INSURANCE_ELIGIBILITY_UNDER_AGE_POLICY','가입자격 최소연령 미만 정책','OPTIONAL_EXCLUSION_BY_WORKER','본인 신청 제외',NULL,'최소연령 미만 처리',1,'SYSTEM:MIGRATION','SYSTEM:MIGRATION'),
    ('20260831-0620-4000-8000-000000000020',96020,'INSURANCE_ELIGIBILITY_MONTH_JUDGMENT','가입자격 월 판단 방식','EMPLOYMENT_START_ANNIVERSARY','고용시작일 응당일',NULL,'고용기간 월 판단',1,'SYSTEM:MIGRATION','SYSTEM:MIGRATION'),
    ('20260831-0621-4000-8000-000000000021',96021,'INSURANCE_ELIGIBILITY_MONTH_JUDGMENT','가입자격 월 판단 방식','START_MONTH_TO_MONTH_END','시작월 말일',NULL,'고용기간 월 판단',1,'SYSTEM:MIGRATION','SYSTEM:MIGRATION'),
    ('20260831-0622-4000-8000-000000000022',96022,'INSURANCE_ELIGIBILITY_INCOME_BASIS','가입자격 소득 기준','STATUTORY_INSURANCE_INCOME','법정 사회보험 소득',NULL,'월 소득 판단 기준',1,'SYSTEM:MIGRATION','SYSTEM:MIGRATION'),
    ('20260831-0623-4000-8000-000000000023',96023,'INSURANCE_ELIGIBILITY_AGGREGATION_SCOPE','가입자격 합산 범위','EMPLOYER','사업주',NULL,'가입자격 합산 범위',1,'SYSTEM:MIGRATION','SYSTEM:MIGRATION'),
    ('20260831-0624-4000-8000-000000000024',96024,'INSURANCE_ELIGIBILITY_AGGREGATION_SCOPE','가입자격 합산 범위','CONSTRUCTION_SITE','건설현장',NULL,'가입자격 합산 범위',1,'SYSTEM:MIGRATION','SYSTEM:MIGRATION'),
    ('20260831-0625-4000-8000-000000000025',96025,'INSURANCE_ELIGIBILITY_AGGREGATION_SCOPE','가입자격 합산 범위','EMPLOYER_CALENDAR_MONTH','사업주·달력월',NULL,'가입자격 합산 범위',1,'SYSTEM:MIGRATION','SYSTEM:MIGRATION'),
    ('20260831-0626-4000-8000-000000000026',96026,'INSURANCE_ELIGIBILITY_AGGREGATION_SCOPE','가입자격 합산 범위','CONSTRUCTION_WORKPLACE_GROUP','동일 건설사업장 합산',NULL,'가입자격 합산 범위',1,'SYSTEM:MIGRATION','SYSTEM:MIGRATION'),
    ('20260831-0627-4000-8000-000000000027',96027,'INSURANCE_ELIGIBILITY_AGGREGATION_SCOPE','가입자격 합산 범위','DEPENDENT','종속 보험',NULL,'가입자격 합산 범위',1,'SYSTEM:MIGRATION','SYSTEM:MIGRATION'),
    ('20260831-0628-4000-8000-000000000028',96028,'INSURANCE_ELIGIBILITY_AGGREGATION_PERIOD','가입자격 합산 기간','CALENDAR_MONTH','달력월',NULL,'가입자격 합산 기간',1,'SYSTEM:MIGRATION','SYSTEM:MIGRATION'),
    ('20260831-0629-4000-8000-000000000029',96029,'INSURANCE_ELIGIBILITY_TRANSITION_POLICY','가입자격 경과정책','CONSTRUCTION_2018_20_TO_8','2018년 건설일용 20일→8일 경과정책',NULL,'건설일용 경과정책',1,'SYSTEM:MIGRATION','SYSTEM:MIGRATION'),
    ('20260831-0630-4000-8000-000000000030',96030,'INSURANCE_ELIGIBILITY_TRANSITION_STATUS','가입자격 경과상태','TRANSITION_APPLICABLE','경과조치 적용',NULL,'가입자격 경과상태',1,'SYSTEM:MIGRATION','SYSTEM:MIGRATION'),
    ('20260831-0631-4000-8000-000000000031',96031,'INSURANCE_ELIGIBILITY_TRANSITION_STATUS','가입자격 경과상태','TRANSITION_NOT_APPLICABLE','경과조치 미적용',NULL,'가입자격 경과상태',1,'SYSTEM:MIGRATION','SYSTEM:MIGRATION'),
    ('20260831-0632-4000-8000-000000000032',96032,'STATUTORY_STANDARD_PERIOD_STATUS','법정기준 적용상태','SCHEDULED','적용 예정',NULL,'기간 기반 가상 적용상태 표시',1,'SYSTEM:MIGRATION','SYSTEM:MIGRATION'),
    ('20260831-0633-4000-8000-000000000033',96033,'STATUTORY_STANDARD_PERIOD_STATUS','법정기준 적용상태','CURRENT','현재 적용',NULL,'기간 기반 가상 적용상태 표시',1,'SYSTEM:MIGRATION','SYSTEM:MIGRATION'),
    ('20260831-0634-4000-8000-000000000034',96034,'STATUTORY_STANDARD_PERIOD_STATUS','법정기준 적용상태','ENDED','적용 종료',NULL,'기간 기반 가상 적용상태 표시',1,'SYSTEM:MIGRATION','SYSTEM:MIGRATION');

    UPDATE system_codes SET extra_data=JSON_REMOVE(JSON_SET(extra_data,
      '$.field_sets.eligibility[1].option_source','SYSTEM_CODES','$.field_sets.eligibility[1].option_code_group','INSURANCE_ELIGIBILITY_DECISION','$.field_sets.eligibility[1].allowed_codes',JSON_ARRAY('RULE_EVALUATION','DEPENDENT_RESULT'),'$.field_sets.eligibility[1].allow_inactive_for_existing_value',TRUE,'$.field_sets.eligibility[1].nullable',FALSE,
      '$.field_sets.eligibility[2].option_source','SYSTEM_CODES','$.field_sets.eligibility[2].option_code_group','STATUTORY_CONDITION_COMBINATION','$.field_sets.eligibility[2].allowed_codes',JSON_ARRAY('ALL','ANY'),'$.field_sets.eligibility[2].allow_inactive_for_existing_value',TRUE,'$.field_sets.eligibility[2].nullable',FALSE,
      '$.field_sets.eligibility[5].option_source','SYSTEM_CODES','$.field_sets.eligibility[5].option_code_group','INSURANCE_ELIGIBILITY_AGE_REFERENCE_DATE','$.field_sets.eligibility[5].allowed_codes',JSON_ARRAY('ATTRIBUTION_DATE'),'$.field_sets.eligibility[5].allow_inactive_for_existing_value',TRUE,'$.field_sets.eligibility[5].nullable',FALSE,
      '$.field_sets.eligibility[6].option_source','SYSTEM_CODES','$.field_sets.eligibility[6].option_code_group','INSURANCE_ELIGIBILITY_UNDER_AGE_POLICY','$.field_sets.eligibility[6].allowed_codes',JSON_ARRAY('EXCLUDED','OPTIONAL_EXCLUSION_BY_WORKER'),'$.field_sets.eligibility[6].allow_inactive_for_existing_value',TRUE,'$.field_sets.eligibility[6].nullable',TRUE,
      '$.field_sets.eligibility[8].option_source','SYSTEM_CODES','$.field_sets.eligibility[8].option_code_group','INSURANCE_ELIGIBILITY_MONTH_JUDGMENT','$.field_sets.eligibility[8].allowed_codes',JSON_ARRAY('EMPLOYMENT_START_ANNIVERSARY','START_MONTH_TO_MONTH_END'),'$.field_sets.eligibility[8].allow_inactive_for_existing_value',TRUE,'$.field_sets.eligibility[8].nullable',FALSE,
      '$.field_sets.eligibility[9].option_source','SYSTEM_CODES','$.field_sets.eligibility[9].option_code_group','STATUTORY_CONDITION_COMBINATION','$.field_sets.eligibility[9].allowed_codes',JSON_ARRAY('ALL','ANY','NONE'),'$.field_sets.eligibility[9].allow_inactive_for_existing_value',TRUE,'$.field_sets.eligibility[9].nullable',FALSE,
      '$.field_sets.eligibility[13].option_source','SYSTEM_CODES','$.field_sets.eligibility[13].option_code_group','INSURANCE_ELIGIBILITY_INCOME_BASIS','$.field_sets.eligibility[13].allowed_codes',JSON_ARRAY('STATUTORY_INSURANCE_INCOME'),'$.field_sets.eligibility[13].allow_inactive_for_existing_value',TRUE,'$.field_sets.eligibility[13].nullable',FALSE,
      '$.field_sets.eligibility[15].option_source','SYSTEM_CODES','$.field_sets.eligibility[15].option_code_group','INSURANCE_ELIGIBILITY_AGGREGATION_SCOPE','$.field_sets.eligibility[15].allowed_codes',JSON_ARRAY('EMPLOYER','CONSTRUCTION_SITE','EMPLOYER_CALENDAR_MONTH','CONSTRUCTION_WORKPLACE_GROUP','DEPENDENT'),'$.field_sets.eligibility[15].allow_inactive_for_existing_value',TRUE,'$.field_sets.eligibility[15].nullable',FALSE,
      '$.field_sets.eligibility[16].option_source','SYSTEM_CODES','$.field_sets.eligibility[16].option_code_group','INSURANCE_ELIGIBILITY_AGGREGATION_PERIOD','$.field_sets.eligibility[16].allowed_codes',JSON_ARRAY('CALENDAR_MONTH'),'$.field_sets.eligibility[16].allow_inactive_for_existing_value',TRUE,'$.field_sets.eligibility[16].nullable',FALSE,
      '$.field_sets.eligibility[18].type','select','$.field_sets.eligibility[18].option_source','SYSTEM_CODES','$.field_sets.eligibility[18].option_code_group','INSURANCE_ELIGIBILITY_TRANSITION_POLICY','$.field_sets.eligibility[18].allowed_codes',JSON_ARRAY('CONSTRUCTION_2018_20_TO_8'),'$.field_sets.eligibility[18].allow_inactive_for_existing_value',TRUE,'$.field_sets.eligibility[18].nullable',TRUE,
      '$.field_sets.eligibility[19].option_source','SYSTEM_CODES','$.field_sets.eligibility[19].option_code_group','INSURANCE_ELIGIBILITY_TRANSITION_STATUS','$.field_sets.eligibility[19].allowed_codes',JSON_ARRAY('TRANSITION_APPLICABLE','TRANSITION_NOT_APPLICABLE'),'$.field_sets.eligibility[19].allow_inactive_for_existing_value',TRUE,'$.field_sets.eligibility[19].nullable',TRUE,
      '$.field_sets.eligibility[20].option_source','SYSTEM_CODES','$.field_sets.eligibility[20].option_code_group','STATUTORY_STANDARD_TYPE','$.field_sets.eligibility[20].allowed_codes',JSON_ARRAY('HEALTH_INSURANCE'),'$.field_sets.eligibility[20].allow_inactive_for_existing_value',TRUE,'$.field_sets.eligibility[20].nullable',TRUE,
      '$.field_sets.eligibility[21].option_source','SYSTEM_CODES','$.field_sets.eligibility[21].option_code_group','STATUTORY_STANDARD_TYPE','$.field_sets.eligibility[21].allowed_codes',JSON_ARRAY('NATIONAL_PENSION','HEALTH_INSURANCE','LONG_TERM_CARE'),'$.field_sets.eligibility[21].allow_inactive_for_existing_value',TRUE,'$.field_sets.eligibility[21].nullable',FALSE,
      '$.field_sets.eligibility[22].option_source','SYSTEM_CODES','$.field_sets.eligibility[22].option_code_group','INSURANCE_ELIGIBILITY_RESULT','$.field_sets.eligibility[22].allowed_codes',JSON_ARRAY('ELIGIBLE'),'$.field_sets.eligibility[22].allow_inactive_for_existing_value',TRUE,'$.field_sets.eligibility[22].nullable',FALSE,
      '$.field_sets.eligibility[23].option_source','SYSTEM_CODES','$.field_sets.eligibility[23].option_code_group','INSURANCE_ELIGIBILITY_RESULT','$.field_sets.eligibility[23].allowed_codes',JSON_ARRAY('NOT_ELIGIBLE'),'$.field_sets.eligibility[23].allow_inactive_for_existing_value',TRUE,'$.field_sets.eligibility[23].nullable',FALSE,
      '$.field_sets.eligibility[24].option_source','SYSTEM_CODES','$.field_sets.eligibility[24].option_code_group','INSURANCE_ELIGIBILITY_RESULT','$.field_sets.eligibility[24].allowed_codes',JSON_ARRAY('CONFIRMATION_REQUIRED'),'$.field_sets.eligibility[24].allow_inactive_for_existing_value',TRUE,'$.field_sets.eligibility[24].nullable',FALSE,
      '$.field_sets.eligibility[25].option_source','SYSTEM_CODES','$.field_sets.eligibility[25].option_code_group','SOCIAL_INSURANCE_CONFIRMATION_STATUS','$.field_sets.eligibility[25].allowed_codes',JSON_ARRAY('CONFIRMED'),'$.field_sets.eligibility[25].allow_inactive_for_existing_value',TRUE,'$.field_sets.eligibility[25].nullable',FALSE),
      '$.field_sets.eligibility[1].options','$.field_sets.eligibility[2].options','$.field_sets.eligibility[5].options','$.field_sets.eligibility[6].options','$.field_sets.eligibility[8].options','$.field_sets.eligibility[9].options','$.field_sets.eligibility[13].options','$.field_sets.eligibility[15].options','$.field_sets.eligibility[16].options','$.field_sets.eligibility[19].options','$.field_sets.eligibility[20].options','$.field_sets.eligibility[21].options','$.field_sets.eligibility[22].options','$.field_sets.eligibility[23].options','$.field_sets.eligibility[24].options','$.field_sets.eligibility[25].options')
    WHERE code_group='STATUTORY_STANDARD_TYPE' AND code IN('NATIONAL_PENSION','HEALTH_INSURANCE','LONG_TERM_CARE','EMPLOYMENT_INSURANCE','INDUSTRIAL_ACCIDENT');

    IF ROW_COUNT() <> 5 OR (SELECT COUNT(*) FROM system_codes WHERE id LIKE '20260831-06%') <> 34 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='법정기준 선택코드 SSOT 적용 결과가 올바르지 않습니다.';
    END IF;
END$$
CALL migrate_20260831_06_up()$$
DROP PROCEDURE migrate_20260831_06_up$$
DELIMITER ;
