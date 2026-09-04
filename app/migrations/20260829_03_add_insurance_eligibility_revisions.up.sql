DELIMITER $$
CREATE PROCEDURE migrate_20260829_03_up()
BEGIN
    IF EXISTS(SELECT 1 FROM system_codes WHERE code_group='STATUTORY_STANDARD_TYPE' AND code='INSURANCE_ELIGIBILITY')
       OR EXISTS(SELECT 1 FROM system_statutory_standards WHERE standard_type_code='INSURANCE_ELIGIBILITY') THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='보험 가입자격 Type 또는 Revision이 이미 존재합니다.';
    END IF;
    INSERT INTO system_codes(id,sort_no,code_group,group_name,code,code_name,note,is_active,extra_data,created_at,created_by,updated_at,updated_by)
    SELECT '20260829-0300-4000-8000-000000000001',COALESCE(MAX(sort_no),0)+1,'STATUTORY_STANDARD_TYPE','법정기준 종류','INSURANCE_ELIGIBILITY','사회보험 가입자격','보험료율 계산 전 근로형태·근무범위별 가입자격 판정 SSOT',1,
      JSON_OBJECT('fields',JSON_ARRAY(),'preserve_schema_in_value',TRUE,'allow_dimension_overlap',TRUE),NOW(),'SYSTEM:MIGRATION',NOW(),'SYSTEM:MIGRATION'
    FROM system_codes;

    CREATE TEMPORARY TABLE eligibility_seed(
      seq INT PRIMARY KEY, insurance VARCHAR(40), employment VARCHAR(20), scope VARCHAR(30), from_date DATE, to_date DATE NULL,
      min_age INT NULL, max_age INT NULL, under18_policy VARCHAR(40) NULL, continuous_months INT NULL,
      days_count INT NULL, minutes_count INT NULL, income_amount DECIMAL(18,2) NULL, combine_code VARCHAR(10), aggregate_scope VARCHAR(40),
      transition_code VARCHAR(80) NULL, transition_status VARCHAR(40) NULL, dependent_insurance VARCHAR(40) NULL,
      premium_type VARCHAR(40), evidence_status VARCHAR(40), note_text VARCHAR(500)
    );
    INSERT INTO eligibility_seed VALUES
    (1,'NATIONAL_PENSION','REGULAR','HEAD_OFFICE','2013-07-19','2015-07-28',18,60,'EXCLUDED',NULL,NULL,NULL,NULL,'NONE','EMPLOYER',NULL,NULL,NULL,'NATIONAL_PENSION','CONFIRMED','본사 상용 연령정책'),
    (2,'NATIONAL_PENSION','REGULAR','HEAD_OFFICE','2015-07-29',NULL,NULL,60,'OPTIONAL_EXCLUSION_BY_WORKER',NULL,NULL,NULL,NULL,'NONE','EMPLOYER',NULL,NULL,NULL,'NATIONAL_PENSION','CONFIRMED','18세 미만 당연가입 및 본인 제외신청'),
    (3,'NATIONAL_PENSION','DAILY','HEAD_OFFICE','2013-07-19','2018-07-31',18,60,NULL,1,8,3600,NULL,'ANY','EMPLOYER',NULL,NULL,NULL,'NATIONAL_PENSION','CONFIRMED','일반 일용 8일 또는 60시간'),
    (4,'NATIONAL_PENSION','DAILY','HEAD_OFFICE','2018-08-01','2021-12-31',18,60,NULL,1,8,3600,NULL,'ANY','EMPLOYER',NULL,NULL,NULL,'NATIONAL_PENSION','CONFIRMED','일반 일용 8일 또는 60시간'),
    (5,'NATIONAL_PENSION','DAILY','HEAD_OFFICE','2022-01-01','2025-06-30',18,60,NULL,1,8,3600,2200000,'ANY','EMPLOYER',NULL,NULL,NULL,'NATIONAL_PENSION','CONFIRMED','일반 일용 소득기준 추가'),
    (6,'NATIONAL_PENSION','DAILY','HEAD_OFFICE','2025-07-01',NULL,18,60,NULL,1,8,3600,2200000,'ANY','EMPLOYER_CALENDAR_MONTH',NULL,NULL,NULL,'NATIONAL_PENSION','CONFIRMED','시작월 달력월 판단'),
    (7,'NATIONAL_PENSION','DAILY','CONSTRUCTION_SITE','2013-07-19','2018-07-31',18,60,NULL,1,20,NULL,NULL,'ANY','CONSTRUCTION_SITE',NULL,NULL,NULL,'NATIONAL_PENSION','CONFIRMED','건설일용 현장별 20일'),
    (8,'NATIONAL_PENSION','DAILY','CONSTRUCTION_SITE','2018-08-01','2020-07-31',18,60,NULL,1,20,NULL,NULL,'ANY','CONSTRUCTION_SITE','CONSTRUCTION_2018_20_TO_8','TRANSITION_APPLICABLE',NULL,'NATIONAL_PENSION','CONFIRMED','경과현장 20일 유지'),
    (9,'NATIONAL_PENSION','DAILY','CONSTRUCTION_SITE','2018-08-01','2020-07-31',18,60,NULL,1,8,NULL,NULL,'ANY','CONSTRUCTION_SITE','CONSTRUCTION_2018_20_TO_8','TRANSITION_NOT_APPLICABLE',NULL,'NATIONAL_PENSION','CONFIRMED','신규현장 8일'),
    (10,'NATIONAL_PENSION','DAILY','CONSTRUCTION_SITE','2020-08-01','2021-12-31',18,60,NULL,1,8,NULL,NULL,'ANY','CONSTRUCTION_SITE',NULL,NULL,NULL,'NATIONAL_PENSION','CONFIRMED','모든 건설현장 8일'),
    (11,'NATIONAL_PENSION','DAILY','CONSTRUCTION_SITE','2022-01-01','2025-06-30',18,60,NULL,1,8,NULL,2200000,'ANY','CONSTRUCTION_SITE',NULL,NULL,NULL,'NATIONAL_PENSION','CONFIRMED','건설일용 현장별 8일 또는 소득'),
    (12,'NATIONAL_PENSION','DAILY','CONSTRUCTION_SITE','2025-07-01',NULL,18,60,NULL,1,8,NULL,2200000,'ANY','CONSTRUCTION_WORKPLACE_GROUP',NULL,NULL,NULL,'NATIONAL_PENSION','CONFIRMED','현장 우선 후 동일 건설사업장 합산'),
    (13,'HEALTH_INSURANCE','REGULAR','HEAD_OFFICE','2013-07-19',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'NONE','EMPLOYER',NULL,NULL,NULL,'HEALTH_INSURANCE','CONFIRMED','본사 상용 직장가입 기본정책'),
    (14,'HEALTH_INSURANCE','DAILY','HEAD_OFFICE','2013-07-19','2019-12-31',NULL,NULL,NULL,1,NULL,NULL,NULL,'NONE','EMPLOYER',NULL,NULL,NULL,'HEALTH_INSURANCE','CONFIRMED','일반 일용 고용기간 1개월 기준'),
    (15,'HEALTH_INSURANCE','DAILY','HEAD_OFFICE','2020-01-01',NULL,NULL,NULL,NULL,1,8,NULL,NULL,'ALL','EMPLOYER',NULL,NULL,NULL,'HEALTH_INSURANCE','CONFIRMED','일반 일용 1개월 및 8일'),
    (16,'HEALTH_INSURANCE','DAILY','CONSTRUCTION_SITE','2013-07-19','2018-07-31',NULL,NULL,NULL,1,20,NULL,NULL,'ALL','CONSTRUCTION_SITE',NULL,NULL,NULL,'HEALTH_INSURANCE','CONFIRMED','건설일용 현장별 20일'),
    (17,'HEALTH_INSURANCE','DAILY','CONSTRUCTION_SITE','2018-08-01','2020-07-31',NULL,NULL,NULL,1,20,NULL,NULL,'ALL','CONSTRUCTION_SITE','CONSTRUCTION_2018_20_TO_8','TRANSITION_APPLICABLE',NULL,'HEALTH_INSURANCE','CONFIRMED','경과현장 20일 유지'),
    (18,'HEALTH_INSURANCE','DAILY','CONSTRUCTION_SITE','2018-08-01','2020-07-31',NULL,NULL,NULL,1,8,NULL,NULL,'ALL','CONSTRUCTION_SITE','CONSTRUCTION_2018_20_TO_8','TRANSITION_NOT_APPLICABLE',NULL,'HEALTH_INSURANCE','CONFIRMED','신규현장 8일'),
    (19,'HEALTH_INSURANCE','DAILY','CONSTRUCTION_SITE','2020-08-01',NULL,NULL,NULL,NULL,1,8,NULL,NULL,'ALL','CONSTRUCTION_SITE',NULL,NULL,NULL,'HEALTH_INSURANCE','CONFIRMED','모든 건설현장 현장별 8일'),
    (20,'LONG_TERM_CARE','REGULAR','HEAD_OFFICE','2013-07-19',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'NONE','DEPENDENT',NULL,NULL,'HEALTH_INSURANCE','LONG_TERM_CARE','CONFIRMED','건강보험 상용 자격결과 종속'),
    (21,'LONG_TERM_CARE','DAILY','HEAD_OFFICE','2013-07-19',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'NONE','DEPENDENT',NULL,NULL,'HEALTH_INSURANCE','LONG_TERM_CARE','CONFIRMED','건강보험 일반일용 자격결과 종속'),
    (22,'LONG_TERM_CARE','DAILY','CONSTRUCTION_SITE','2013-07-19',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'NONE','DEPENDENT',NULL,NULL,'HEALTH_INSURANCE','LONG_TERM_CARE','CONFIRMED','건강보험 건설일용 자격결과 종속');

    INSERT INTO system_statutory_standards(id,sort_no,standard_type_code,effective_from,effective_to,value_data,note,created_at,created_by,updated_at,updated_by)
    SELECT CONCAT('20260829-03',LPAD(seq,2,'0'),'-4000-8000-',LPAD(seq,12,'0')),seq,'INSURANCE_ELIGIBILITY',from_date,to_date,
      JSON_OBJECT(
        'policy_version',1,'insurance_type_code',insurance,'employment_type_code',employment,'work_scope_code',scope,'decision_code',IF(dependent_insurance IS NULL,'RULE_EVALUATION','DEPENDENT_RESULT'),
        'age',JSON_OBJECT('minimum_age',min_age,'maximum_age_exclusive',max_age,'reference_date_code','ATTRIBUTION_DATE','under_minimum_age_policy_code',under18_policy),
        'employment_period',JSON_OBJECT('minimum_continuous_months',continuous_months,'month_judgment_code',IF(from_date>='2025-07-01','START_MONTH_TO_MONTH_END','EMPLOYMENT_START_ANNIVERSARY')),
        'monthly_conditions',JSON_OBJECT('combination_code',combine_code,'minimum_work_days',days_count,'minimum_work_minutes',minutes_count,'minimum_income_amount',income_amount,'income_code','STATUTORY_INSURANCE_INCOME','null_means_no_condition',TRUE),
        'overall_combination_code','ALL','aggregation',JSON_OBJECT('scope_code',aggregate_scope,'period_code','CALENDAR_MONTH','site_first',aggregate_scope='CONSTRUCTION_WORKPLACE_GROUP'),
        'transition',JSON_OBJECT('policy_code',transition_code,'required_status_code',transition_status),
        'requirements',JSON_OBJECT('employment_start_date',continuous_months IS NOT NULL,'employment_end_date_or_open_status',continuous_months IS NOT NULL,'continuous_employment_confirmed',continuous_months IS NOT NULL,'birth_date',min_age IS NOT NULL OR max_age IS NOT NULL,'monthly_work_days',days_count IS NOT NULL,'monthly_work_minutes',minutes_count IS NOT NULL,'monthly_income_amount',income_amount IS NOT NULL),
        'exclusions',JSON_ARRAY(),'dependent_insurance_type_code',dependent_insurance,'premium_revision_type_code',premium_type,
        'eligible_result_code','ELIGIBLE','not_eligible_result_code','NOT_ELIGIBLE','missing_input_result_code','CONFIRMATION_REQUIRED','official_evidence_status',evidence_status,
        '_schema',JSON_OBJECT('version',1,'condition_language','STRUCTURED_NO_EXPRESSION','null_is_distinct_from_zero',TRUE)
      ),note_text,NOW(),'SYSTEM:MIGRATION',NOW(),'SYSTEM:MIGRATION'
    FROM eligibility_seed;

    INSERT INTO system_statutory_standard_sources(id,standard_id,organization_name,law_name,notice_no,source_name,source_url,published_at,file_path,file_name,file_size,mime_type,note,sort_no,created_at,created_by,updated_at,updated_by)
    SELECT CONCAT('20260829-13',LPAD(seq,2,'0'),'-4000-8000-',LPAD(seq,12,'0')),
      CONCAT('20260829-03',LPAD(seq,2,'0'),'-4000-8000-',LPAD(seq,12,'0')),
      CASE WHEN insurance='NATIONAL_PENSION' THEN '국민연금공단' WHEN insurance='HEALTH_INSURANCE' THEN '국민건강보험공단' ELSE '국가법령정보센터' END,
      CASE WHEN insurance='NATIONAL_PENSION' THEN '국민연금법 및 국민연금법 시행령' WHEN insurance='HEALTH_INSURANCE' THEN '국민건강보험법' ELSE '노인장기요양보험법' END,
      NULL,CONCAT(note_text,' 공식 근거'),
      CASE WHEN insurance='NATIONAL_PENSION' THEN 'https://www.nps.or.kr/pnsinfo/ntpsklg/getOHAF0016M0.do?menuId=MN24001108' WHEN insurance='HEALTH_INSURANCE' THEN 'https://si4n.nhis.or.kr/popup/2019/20191227_pop01longdesc.html' ELSE 'https://www.law.go.kr/법령/노인장기요양보험법' END,
      NULL,NULL,NULL,NULL,NULL,'Migration 작성일 기준 공식자료 확인',1,NOW(),'SYSTEM:MIGRATION',NOW(),'SYSTEM:MIGRATION'
    FROM eligibility_seed;
    DROP TEMPORARY TABLE eligibility_seed;
END$$
CALL migrate_20260829_03_up()$$
DROP PROCEDURE migrate_20260829_03_up$$
DELIMITER ;
