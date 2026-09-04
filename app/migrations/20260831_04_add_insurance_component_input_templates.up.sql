DELIMITER $$
CREATE PROCEDURE migrate_20260831_04_up()
BEGIN
    IF (SELECT COUNT(*) FROM system_codes WHERE code_group='STATUTORY_STANDARD_TYPE' AND code IN('NATIONAL_PENSION','HEALTH_INSURANCE','LONG_TERM_CARE','EMPLOYMENT_INSURANCE','INDUSTRIAL_ACCIDENT')) <> 5 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='보험별 법정기준 Type 5건이 없습니다.';
    END IF;
    IF EXISTS (
        SELECT 1 FROM system_codes
         WHERE code_group='STATUTORY_STANDARD_TYPE'
           AND code IN('NATIONAL_PENSION','HEALTH_INSURANCE','LONG_TERM_CARE','EMPLOYMENT_INSURANCE','INDUSTRIAL_ACCIDENT')
           AND (JSON_CONTAINS_PATH(extra_data,'one','$.field_sets.eligibility')=1 OR JSON_CONTAINS_PATH(extra_data,'one','$.component_templates')=1)
    ) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='보험별 가입자격 입력 템플릿이 이미 존재합니다.';
    END IF;

    SET @eligibility_fields=JSON_ARRAY(
      JSON_OBJECT('code','policy_version','name','정책 버전','type','number','required',TRUE,'value_path','policy_version','group_code','policy','group_name','정책 기본정보','nullable',FALSE,'display_order',10),
      JSON_OBJECT('code','decision_code','name','판정 방식','type','select','required',TRUE,'value_path','decision_code','group_code','policy','group_name','정책 기본정보','options',JSON_ARRAY(JSON_OBJECT('value','RULE_EVALUATION','label','조건 평가'),JSON_OBJECT('value','DEPENDENT_RESULT','label','다른 보험 결과 종속')),'display_order',20),
      JSON_OBJECT('code','overall_combination_code','name','전체 조건 결합','type','select','required',TRUE,'value_path','overall_combination_code','group_code','policy','group_name','정책 기본정보','options',JSON_ARRAY(JSON_OBJECT('value','ALL','label','모두 충족'),JSON_OBJECT('value','ANY','label','하나 이상 충족')),'display_order',30),
      JSON_OBJECT('code','minimum_age','name','최소 연령','type','number','required',FALSE,'nullable',TRUE,'value_path','age.minimum_age','group_code','age','group_name','연령 조건','display_order',100),
      JSON_OBJECT('code','maximum_age_exclusive','name','미만 연령 상한','type','number','required',FALSE,'nullable',TRUE,'value_path','age.maximum_age_exclusive','group_code','age','group_name','연령 조건','display_order',110),
      JSON_OBJECT('code','reference_date_code','name','연령 기준일','type','select','required',TRUE,'value_path','age.reference_date_code','group_code','age','group_name','연령 조건','options',JSON_ARRAY(JSON_OBJECT('value','ATTRIBUTION_DATE','label','귀속일')),'display_order',120),
      JSON_OBJECT('code','under_minimum_age_policy_code','name','최소연령 미만 처리','type','select','required',FALSE,'nullable',TRUE,'value_path','age.under_minimum_age_policy_code','group_code','age','group_name','연령 조건','options',JSON_ARRAY(JSON_OBJECT('value','EXCLUDED','label','가입 제외'),JSON_OBJECT('value','OPTIONAL_EXCLUSION_BY_WORKER','label','본인 신청 제외')),'display_order',130),
      JSON_OBJECT('code','minimum_continuous_months','name','최소 계속고용 개월','type','number','required',FALSE,'nullable',TRUE,'value_path','employment_period.minimum_continuous_months','group_code','employment','group_name','고용기간 조건','display_order',200),
      JSON_OBJECT('code','month_judgment_code','name','월 판단 방식','type','select','required',TRUE,'value_path','employment_period.month_judgment_code','group_code','employment','group_name','고용기간 조건','options',JSON_ARRAY(JSON_OBJECT('value','EMPLOYMENT_START_ANNIVERSARY','label','고용시작일 응당일'),JSON_OBJECT('value','START_MONTH_TO_MONTH_END','label','시작월 말일')),'display_order',210),
      JSON_OBJECT('code','monthly_combination_code','name','월 조건 결합','type','select','required',TRUE,'value_path','monthly_conditions.combination_code','group_code','monthly','group_name','월 근무·소득 조건','options',JSON_ARRAY(JSON_OBJECT('value','ALL','label','모두 충족'),JSON_OBJECT('value','ANY','label','하나 이상 충족'),JSON_OBJECT('value','NONE','label','조건 없음')),'display_order',300),
      JSON_OBJECT('code','minimum_work_days','name','최소 월 근무일수','type','number','required',FALSE,'nullable',TRUE,'value_path','monthly_conditions.minimum_work_days','group_code','monthly','group_name','월 근무·소득 조건','display_order',310),
      JSON_OBJECT('code','minimum_work_minutes','name','최소 월 근로시간(분)','type','number','required',FALSE,'nullable',TRUE,'value_path','monthly_conditions.minimum_work_minutes','group_code','monthly','group_name','월 근무·소득 조건','display_order',320),
      JSON_OBJECT('code','minimum_income_amount','name','최소 월 소득금액','type','amount','required',FALSE,'nullable',TRUE,'value_path','monthly_conditions.minimum_income_amount','group_code','monthly','group_name','월 근무·소득 조건','display_order',330),
      JSON_OBJECT('code','income_code','name','소득 기준코드','type','select','required',TRUE,'value_path','monthly_conditions.income_code','group_code','monthly','group_name','월 근무·소득 조건','options',JSON_ARRAY(JSON_OBJECT('value','STATUTORY_INSURANCE_INCOME','label','법정 사회보험 소득')),'display_order',340),
      JSON_OBJECT('code','null_means_no_condition','name','NULL 조건 없음 처리','type','boolean','required',TRUE,'value_path','monthly_conditions.null_means_no_condition','group_code','monthly','group_name','월 근무·소득 조건','display_order',350),
      JSON_OBJECT('code','aggregation_scope_code','name','합산 범위','type','select','required',TRUE,'value_path','aggregation.scope_code','group_code','aggregation','group_name','합산·건설 경과정책','options',JSON_ARRAY(JSON_OBJECT('value','EMPLOYER','label','사업주'),JSON_OBJECT('value','CONSTRUCTION_SITE','label','건설현장'),JSON_OBJECT('value','EMPLOYER_CALENDAR_MONTH','label','사업주·달력월'),JSON_OBJECT('value','CONSTRUCTION_WORKPLACE_GROUP','label','동일 건설사업장 합산'),JSON_OBJECT('value','DEPENDENT','label','종속 보험')),'display_order',400),
      JSON_OBJECT('code','aggregation_period_code','name','합산 기간','type','select','required',TRUE,'value_path','aggregation.period_code','group_code','aggregation','group_name','합산·건설 경과정책','options',JSON_ARRAY(JSON_OBJECT('value','CALENDAR_MONTH','label','달력월')),'display_order',410),
      JSON_OBJECT('code','site_first','name','현장 우선 판정','type','boolean','required',TRUE,'value_path','aggregation.site_first','group_code','aggregation','group_name','합산·건설 경과정책','display_order',420),
      JSON_OBJECT('code','transition_policy_code','name','경과조치 정책코드','type','text','required',FALSE,'nullable',TRUE,'value_path','transition.policy_code','group_code','aggregation','group_name','합산·건설 경과정책','display_order',430),
      JSON_OBJECT('code','transition_required_status_code','name','필요 경과상태','type','select','required',FALSE,'nullable',TRUE,'value_path','transition.required_status_code','group_code','aggregation','group_name','합산·건설 경과정책','options',JSON_ARRAY(JSON_OBJECT('value','TRANSITION_APPLICABLE','label','경과조치 적용'),JSON_OBJECT('value','TRANSITION_NOT_APPLICABLE','label','경과조치 미적용')),'display_order',440),
      JSON_OBJECT('code','dependent_insurance_type_code','name','종속 보험종류','type','select','required',FALSE,'nullable',TRUE,'value_path','dependent_insurance_type_code','group_code','result','group_name','결과·요구입력','options',JSON_ARRAY(JSON_OBJECT('value','HEALTH_INSURANCE','label','건강보험')),'display_order',500),
      JSON_OBJECT('code','premium_revision_type_code','name','보험료 기준종류','type','select','required',TRUE,'value_path','premium_revision_type_code','group_code','result','group_name','결과·요구입력','options',JSON_ARRAY(JSON_OBJECT('value','NATIONAL_PENSION','label','국민연금'),JSON_OBJECT('value','HEALTH_INSURANCE','label','건강보험'),JSON_OBJECT('value','LONG_TERM_CARE','label','장기요양보험'),JSON_OBJECT('value','EMPLOYMENT_INSURANCE','label','고용보험'),JSON_OBJECT('value','INDUSTRIAL_ACCIDENT','label','산재보험')),'display_order',510),
      JSON_OBJECT('code','eligible_result_code','name','가입 결과코드','type','select','required',TRUE,'value_path','eligible_result_code','group_code','result','group_name','결과·요구입력','options',JSON_ARRAY(JSON_OBJECT('value','ELIGIBLE','label','가입 대상')),'display_order',520),
      JSON_OBJECT('code','not_eligible_result_code','name','제외 결과코드','type','select','required',TRUE,'value_path','not_eligible_result_code','group_code','result','group_name','결과·요구입력','options',JSON_ARRAY(JSON_OBJECT('value','NOT_ELIGIBLE','label','가입 제외')),'display_order',530),
      JSON_OBJECT('code','missing_input_result_code','name','누락입력 결과코드','type','select','required',TRUE,'value_path','missing_input_result_code','group_code','result','group_name','결과·요구입력','options',JSON_ARRAY(JSON_OBJECT('value','CONFIRMATION_REQUIRED','label','확인 필요')),'display_order',540),
      JSON_OBJECT('code','official_evidence_status','name','공식근거 상태','type','select','required',TRUE,'value_path','official_evidence_status','group_code','result','group_name','결과·요구입력','options',JSON_ARRAY(JSON_OBJECT('value','CONFIRMED','label','확정')),'display_order',550)
    );
    SET @component_templates=JSON_ARRAY(
      JSON_OBJECT('policy_component_code','PREMIUM','employment_type_code','ALL','work_scope_code','ALL','card_name','공통 보험료','field_set_code','premium'),
      JSON_OBJECT('policy_component_code','ELIGIBILITY','employment_type_code','REGULAR','work_scope_code','HEAD_OFFICE','card_name','상용 가입자격','field_set_code','eligibility'),
      JSON_OBJECT('policy_component_code','ELIGIBILITY','employment_type_code','DAILY','work_scope_code','HEAD_OFFICE','card_name','일반 일용 가입자격','field_set_code','eligibility'),
      JSON_OBJECT('policy_component_code','ELIGIBILITY','employment_type_code','DAILY','work_scope_code','CONSTRUCTION_SITE','card_name','건설 일용 가입자격','field_set_code','eligibility')
    );

    UPDATE system_codes
       SET extra_data=JSON_SET(extra_data,
           '$.field_sets',JSON_OBJECT(
               'premium',JSON_EXTRACT(extra_data,'$.fields'),
               'eligibility',JSON_EXTRACT(@eligibility_fields,'$')),
           '$.component_templates',JSON_EXTRACT(@component_templates,'$'))
     WHERE code_group='STATUTORY_STANDARD_TYPE'
       AND code IN('NATIONAL_PENSION','HEALTH_INSURANCE','LONG_TERM_CARE','EMPLOYMENT_INSURANCE','INDUSTRIAL_ACCIDENT');
    IF ROW_COUNT() <> 5 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='보험별 입력 템플릿 5건 등록에 실패했습니다.';
    END IF;
END$$
CALL migrate_20260831_04_up()$$
DROP PROCEDURE migrate_20260831_04_up$$
DELIMITER ;
