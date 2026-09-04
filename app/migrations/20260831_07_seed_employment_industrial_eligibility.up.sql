DELIMITER $$
CREATE PROCEDURE migrate_20260831_07_up()
BEGIN
    IF (SELECT COUNT(*) FROM system_statutory_standards WHERE policy_component_code='ELIGIBILITY'
        AND standard_type_code IN('NATIONAL_PENSION','HEALTH_INSURANCE','LONG_TERM_CARE')) <> 22 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='기존 보험 가입자격 Revision 기준선이 다릅니다.';
    END IF;
    IF EXISTS(SELECT 1 FROM system_statutory_standards WHERE policy_component_code='ELIGIBILITY'
        AND standard_type_code IN('EMPLOYMENT_INSURANCE','INDUSTRIAL_ACCIDENT')) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='고용보험 또는 산재보험 가입자격 Revision이 이미 존재합니다.';
    END IF;

    CREATE TEMPORARY TABLE eligibility_seed(
        seq INT PRIMARY KEY,
        insurance_type_code VARCHAR(40) NOT NULL,
        employment_type_code VARCHAR(20) NOT NULL,
        work_scope_code VARCHAR(30) NOT NULL,
        effective_from DATE NOT NULL,
        effective_to DATE NULL,
        policy_json LONGTEXT NOT NULL,
        source_name VARCHAR(300) NOT NULL,
        law_name VARCHAR(300) NOT NULL,
        notice_no VARCHAR(100) NOT NULL,
        source_url VARCHAR(1000) NOT NULL,
        published_at DATE NOT NULL,
        source_note VARCHAR(500) NOT NULL
    );

    INSERT INTO eligibility_seed
    SELECT period_row.seq * 10 + scope_row.seq,
           'EMPLOYMENT_INSURANCE',scope_row.employment_type_code,scope_row.work_scope_code,
           period_row.effective_from,period_row.effective_to,
           JSON_OBJECT(
             'policy_version',2,'decision_model_code','COMPONENT_ELIGIBILITY',
             'insurance_type_code','EMPLOYMENT_INSURANCE','employment_type_code',scope_row.employment_type_code,
             'work_scope_code',scope_row.work_scope_code,
             'required_facts',JSON_ARRAY(
               JSON_OBJECT('fact_code','employment_relationship_confirmed','fact_name','근로관계 확인'),
               JSON_OBJECT('fact_code','employment_type_code','fact_name','법정기준 고용형태'),
               JSON_OBJECT('fact_code','employment_insurance_person_excluded','fact_name','고용보험 적용제외 해당 여부'),
               JSON_OBJECT('fact_code','foreign_worker_optional_unemployment','fact_name','외국인 실업급여 임의가입 대상 여부'),
               JSON_OBJECT('fact_code','scheduled_monthly_work_minutes','fact_name','월 소정근로시간'),
               JSON_OBJECT('fact_code','scheduled_weekly_work_minutes','fact_name','주 소정근로시간'),
               JSON_OBJECT('fact_code','continuous_employment_months','fact_name','계속고용 개월 수')
             ),
             'overall_aggregation_code','COMPONENT_STATUS_AGGREGATION',
             'components',JSON_ARRAY(
               JSON_OBJECT(
                 'component_code','UNEMPLOYMENT_BENEFIT','component_name','실업급여',
                 'combination_code','ALL','required_application_code','NOT_REQUIRED',
                 'employee_contribution_applicable',TRUE,'employer_contribution_applicable',TRUE,
                 'condition',JSON_OBJECT('combination_code','ALL','conditions',JSON_ARRAY(
                   JSON_OBJECT('fact_code','employment_relationship_confirmed','operator','TRUE','expected_value',TRUE),
                   JSON_OBJECT('fact_code','employment_insurance_person_excluded','operator','FALSE','expected_value',FALSE),
                   IF(scope_row.employment_type_code='DAILY',
                      JSON_OBJECT('fact_code','employment_type_code','operator','EQ','expected_value','DAILY'),
                      JSON_OBJECT('combination_code','ANY','conditions',JSON_ARRAY(
                        JSON_OBJECT('fact_code','scheduled_monthly_work_minutes','operator','GTE','expected_value',3600),
                        JSON_OBJECT('fact_code','scheduled_weekly_work_minutes','operator','GTE','expected_value',900),
                        IF(period_row.effective_from='2023-07-01',
                           JSON_OBJECT('fact_code','continuous_employment_months','operator','GTE','expected_value',3),
                           JSON_OBJECT('fact_code','employment_type_code','operator','EQ','expected_value','REGULAR'))
                      )))
                 )),
                 'optional_application_condition',JSON_OBJECT('fact_code','foreign_worker_optional_unemployment','operator','TRUE','expected_value',TRUE),
                 'applicable_reason',JSON_OBJECT('code','EMPLOYMENT_UNEMPLOYMENT_APPLICABLE','name','실업급여 적용요건 충족'),
                 'excluded_reason',JSON_OBJECT('code','EMPLOYMENT_UNEMPLOYMENT_EXCLUDED','name','실업급여 적용요건 미충족'),
                 'confirmation_reason',JSON_OBJECT('code','EMPLOYMENT_UNEMPLOYMENT_FACT_REQUIRED','name','실업급여 가입자격 확인 필요')
               ),
               JSON_OBJECT(
                 'component_code','EMPLOYMENT_STABILITY_VOCATIONAL','component_name','고용안정·직업능력개발',
                 'combination_code','ALL','required_application_code','NOT_REQUIRED',
                 'employee_contribution_applicable',FALSE,'employer_contribution_applicable',TRUE,
                 'condition',JSON_OBJECT('combination_code','ALL','conditions',JSON_ARRAY(
                   JSON_OBJECT('fact_code','employment_relationship_confirmed','operator','TRUE','expected_value',TRUE),
                   JSON_OBJECT('fact_code','employment_insurance_person_excluded','operator','FALSE','expected_value',FALSE)
                 )),
                 'applicable_reason',JSON_OBJECT('code','EMPLOYMENT_STABILITY_APPLICABLE','name','고용안정·직업능력개발 적용요건 충족'),
                 'excluded_reason',JSON_OBJECT('code','EMPLOYMENT_STABILITY_EXCLUDED','name','고용안정·직업능력개발 적용요건 미충족'),
                 'confirmation_reason',JSON_OBJECT('code','EMPLOYMENT_STABILITY_FACT_REQUIRED','name','고용안정·직업능력개발 가입자격 확인 필요')
               )
             ),
             'reason_codes',JSON_ARRAY(
               JSON_OBJECT('code','EMPLOYMENT_UNEMPLOYMENT_APPLICABLE','name','실업급여 적용요건 충족'),
               JSON_OBJECT('code','EMPLOYMENT_UNEMPLOYMENT_EXCLUDED','name','실업급여 적용요건 미충족'),
               JSON_OBJECT('code','EMPLOYMENT_UNEMPLOYMENT_FACT_REQUIRED','name','실업급여 가입자격 확인 필요'),
               JSON_OBJECT('code','EMPLOYMENT_STABILITY_APPLICABLE','name','고용안정·직업능력개발 적용요건 충족'),
               JSON_OBJECT('code','EMPLOYMENT_STABILITY_EXCLUDED','name','고용안정·직업능력개발 적용요건 미충족'),
               JSON_OBJECT('code','EMPLOYMENT_STABILITY_FACT_REQUIRED','name','고용안정·직업능력개발 가입자격 확인 필요')
             ),
             '_schema',JSON_OBJECT('version',2,'condition_language','STRUCTURED_NO_EXPRESSION','source_metadata_sha256',SHA2(CONCAT(period_row.notice_no,'|',period_row.source_url),256))
           ),
           period_row.source_name,period_row.law_name,period_row.notice_no,period_row.source_url,period_row.published_at,period_row.source_note
      FROM (
        SELECT 1 seq,'REGULAR' employment_type_code,'HEAD_OFFICE' work_scope_code UNION ALL
        SELECT 2,'DAILY','HEAD_OFFICE' UNION ALL SELECT 3,'DAILY','CONSTRUCTION_SITE'
      ) scope_row
      CROSS JOIN (
        SELECT 1 seq,DATE('2013-07-19') effective_from,DATE('2019-01-14') effective_to,
               '고용보험법 적용제외 기준' source_name,'고용보험법 및 고용보험법 시행령' law_name,'법률 제11864호' notice_no,
               'https://www.law.go.kr/법령/고용보험법' source_url,DATE('2013-06-04') published_at,'2013년 적용제외 기준' source_note UNION ALL
        SELECT 2,DATE('2019-01-15'),DATE('2023-06-30'),'고용보험법 제10조·제10조의2 개정','고용보험법','법률 제16269호',
               'https://www.law.go.kr/lsInfoP.do?lsiSeq=206705',DATE('2019-01-15'),'65세 이후 고용 및 외국인 적용 구성요소 경계' UNION ALL
        SELECT 3,DATE('2023-07-01'),NULL,'고용보험법 시행령 제3조 개정','고용보험법 시행령','대통령령 제33595호',
               'https://www.law.go.kr/법령/고용보험법시행령',DATE('2023-06-27'),'단시간근로자 3개월 계속근로 예외 경계'
      ) period_row;

    INSERT INTO eligibility_seed
    SELECT 100 + period_row.seq * 10 + scope_row.seq,
           'INDUSTRIAL_ACCIDENT',scope_row.employment_type_code,scope_row.work_scope_code,
           period_row.effective_from,period_row.effective_to,
           JSON_OBJECT(
             'policy_version',2,'decision_model_code','BUSINESS_AND_WORKER_ELIGIBILITY',
             'insurance_type_code','INDUSTRIAL_ACCIDENT','employment_type_code',scope_row.employment_type_code,
             'work_scope_code',scope_row.work_scope_code,
             'required_facts',JSON_ARRAY(
               JSON_OBJECT('fact_code','workplace_industrial_insurance_applicable','fact_name','사업장 산재보험 적용 여부'),
               JSON_OBJECT('fact_code','employee_status_confirmed','fact_name','근로자성 확인'),
               JSON_OBJECT('fact_code','actual_work_engagement_confirmed','fact_name','실제 근로 종사 확인')
             ),
             'overall_aggregation_code','ALL_STAGES_REQUIRED',
             'stages',JSON_ARRAY(
               JSON_OBJECT('stage_code','BUSINESS_APPLICABILITY','stage_name','사업장 적용성','combination_code','ALL',
                 'condition',JSON_OBJECT('fact_code','workplace_industrial_insurance_applicable','operator','TRUE','expected_value',TRUE),
                 'applicable_reason',JSON_OBJECT('code','INDUSTRIAL_BUSINESS_APPLICABLE','name','산재보험 적용 사업장'),
                 'excluded_reason',JSON_OBJECT('code','INDUSTRIAL_BUSINESS_EXCLUDED','name','산재보험 적용 제외 사업장'),
                 'confirmation_reason',JSON_OBJECT('code','INDUSTRIAL_BUSINESS_FACT_REQUIRED','name','사업장 산재보험 관계 확인 필요')),
               JSON_OBJECT('stage_code','WORKER_STATUS','stage_name','근로자성','combination_code','ALL',
                 'condition',JSON_OBJECT('fact_code','employee_status_confirmed','operator','TRUE','expected_value',TRUE),
                 'applicable_reason',JSON_OBJECT('code','INDUSTRIAL_WORKER_APPLICABLE','name','산재보험법상 근로자'),
                 'excluded_reason',JSON_OBJECT('code','INDUSTRIAL_WORKER_EXCLUDED','name','산재보험법상 근로자 아님'),
                 'confirmation_reason',JSON_OBJECT('code','INDUSTRIAL_WORKER_FACT_REQUIRED','name','근로자성 확인 필요')),
               JSON_OBJECT('stage_code','ACTUAL_WORK_ENGAGEMENT','stage_name','실제 근로 종사','combination_code','ALL',
                 'condition',JSON_OBJECT('fact_code','actual_work_engagement_confirmed','operator','TRUE','expected_value',TRUE),
                 'applicable_reason',JSON_OBJECT('code','INDUSTRIAL_ENGAGEMENT_APPLICABLE','name','실제 근로 종사 확인'),
                 'excluded_reason',JSON_OBJECT('code','INDUSTRIAL_ENGAGEMENT_EXCLUDED','name','실제 근로 종사 없음'),
                 'confirmation_reason',JSON_OBJECT('code','INDUSTRIAL_ENGAGEMENT_FACT_REQUIRED','name','실제 근로 종사 확인 필요'))
             ),
             'reason_codes',JSON_ARRAY(
               JSON_OBJECT('code','INDUSTRIAL_BUSINESS_APPLICABLE','name','산재보험 적용 사업장'),
               JSON_OBJECT('code','INDUSTRIAL_BUSINESS_EXCLUDED','name','산재보험 적용 제외 사업장'),
               JSON_OBJECT('code','INDUSTRIAL_BUSINESS_FACT_REQUIRED','name','사업장 산재보험 관계 확인 필요'),
               JSON_OBJECT('code','INDUSTRIAL_WORKER_APPLICABLE','name','산재보험법상 근로자'),
               JSON_OBJECT('code','INDUSTRIAL_WORKER_EXCLUDED','name','산재보험법상 근로자 아님'),
               JSON_OBJECT('code','INDUSTRIAL_WORKER_FACT_REQUIRED','name','근로자성 확인 필요'),
               JSON_OBJECT('code','INDUSTRIAL_ENGAGEMENT_APPLICABLE','name','실제 근로 종사 확인'),
               JSON_OBJECT('code','INDUSTRIAL_ENGAGEMENT_EXCLUDED','name','실제 근로 종사 없음'),
               JSON_OBJECT('code','INDUSTRIAL_ENGAGEMENT_FACT_REQUIRED','name','실제 근로 종사 확인 필요')
             ),
             '_schema',JSON_OBJECT('version',2,'condition_language','STRUCTURED_NO_EXPRESSION','source_metadata_sha256',SHA2(CONCAT(period_row.notice_no,'|',period_row.source_url),256))
           ),
           period_row.source_name,period_row.law_name,period_row.notice_no,period_row.source_url,period_row.published_at,period_row.source_note
      FROM (
        SELECT 1 seq,'REGULAR' employment_type_code,'HEAD_OFFICE' work_scope_code UNION ALL
        SELECT 2,'DAILY','HEAD_OFFICE' UNION ALL SELECT 3,'DAILY','CONSTRUCTION_SITE'
      ) scope_row
      CROSS JOIN (
        SELECT 1 seq,DATE('2013-07-19') effective_from,DATE('2017-12-31') effective_to,
               '산업재해보상보험법 적용범위' source_name,'산업재해보상보험법 및 시행령' law_name,'법률 제11882호' notice_no,
               'https://www.law.go.kr/법령/산업재해보상보험법' source_url,DATE('2013-06-12') published_at,'2013년 사업장 적용범위' source_note UNION ALL
        SELECT 2,DATE('2018-01-01'),NULL,'산업재해보상보험법 시행령 제2조 개정','산업재해보상보험법 시행령','대통령령 제28506호',
               'https://www.law.go.kr/lsInfoP.do?lsiSeq=200262',DATE('2017-12-26'),'건설공사 적용제외 삭제 및 시행 후 착공 경과계약'
      ) period_row;

    INSERT INTO system_statutory_standards(
        id,sort_no,standard_type_code,policy_component_code,employment_type_code,work_scope_code,
        additional_dimension_data,additional_dimension_key,effective_from,effective_to,value_data,note,
        created_at,created_by,updated_at,updated_by
    )
    SELECT CONCAT('68310700-0000-4000-8000-',LPAD(seq,12,'0')),
           9000+seq,insurance_type_code,'ELIGIBILITY',employment_type_code,work_scope_code,
           JSON_OBJECT(),SHA2('{}',256),effective_from,effective_to,policy_json,
           CONCAT('[공식 가입자격 Seed] ',source_note),NOW(),'SYSTEM:MIGRATION',NOW(),'SYSTEM:MIGRATION'
      FROM eligibility_seed ORDER BY seq;

    INSERT INTO system_statutory_standard_sources(
        id,standard_id,organization_name,law_name,notice_no,source_name,source_url,published_at,
        file_path,file_name,file_size,mime_type,note,sort_no,created_at,created_by,updated_at,updated_by
    )
    SELECT CONCAT('68311700-0000-4000-8000-',LPAD(seq,12,'0')),
           CONCAT('68310700-0000-4000-8000-',LPAD(seq,12,'0')),
           '국가법령정보센터',law_name,notice_no,source_name,source_url,published_at,
           NULL,NULL,NULL,NULL,CONCAT(source_note,' · Source metadata SHA-256: ',SHA2(CONCAT(notice_no,'|',source_url),256)),
           1,NOW(),'SYSTEM:MIGRATION',NOW(),'SYSTEM:MIGRATION'
      FROM eligibility_seed ORDER BY seq;

    IF (SELECT COUNT(*) FROM system_statutory_standards WHERE policy_component_code='ELIGIBILITY'
        AND standard_type_code='EMPLOYMENT_INSURANCE') <> 9
       OR (SELECT COUNT(*) FROM system_statutory_standards WHERE policy_component_code='ELIGIBILITY'
        AND standard_type_code='INDUSTRIAL_ACCIDENT') <> 6 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='고용보험·산재보험 가입자격 Seed 건수가 다릅니다.';
    END IF;
    DROP TEMPORARY TABLE eligibility_seed;
END$$
CALL migrate_20260831_07_up()$$
DROP PROCEDURE migrate_20260831_07_up$$
DELIMITER ;
