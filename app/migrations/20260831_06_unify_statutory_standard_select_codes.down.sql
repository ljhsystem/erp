DELIMITER $$
CREATE PROCEDURE migrate_20260831_06_down()
BEGIN
    IF (SELECT COUNT(*) FROM system_codes WHERE id LIKE '20260831-06%') <> 34 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='법정기준 선택코드 Down 기준이 일치하지 않습니다.';
    END IF;

    UPDATE system_codes SET extra_data=JSON_REMOVE(JSON_SET(extra_data,
      '$.field_sets.eligibility[1].options',JSON_ARRAY(JSON_OBJECT('value','RULE_EVALUATION','label','조건 평가'),JSON_OBJECT('value','DEPENDENT_RESULT','label','다른 보험 결과 종속')),
      '$.field_sets.eligibility[2].options',JSON_ARRAY(JSON_OBJECT('value','ALL','label','모두 충족'),JSON_OBJECT('value','ANY','label','하나 이상 충족')),
      '$.field_sets.eligibility[5].options',JSON_ARRAY(JSON_OBJECT('value','ATTRIBUTION_DATE','label','귀속일')),
      '$.field_sets.eligibility[6].options',JSON_ARRAY(JSON_OBJECT('value','EXCLUDED','label','가입 제외'),JSON_OBJECT('value','OPTIONAL_EXCLUSION_BY_WORKER','label','본인 신청 제외')),
      '$.field_sets.eligibility[8].options',JSON_ARRAY(JSON_OBJECT('value','EMPLOYMENT_START_ANNIVERSARY','label','고용시작일 응당일'),JSON_OBJECT('value','START_MONTH_TO_MONTH_END','label','시작월 말일')),
      '$.field_sets.eligibility[9].options',JSON_ARRAY(JSON_OBJECT('value','ALL','label','모두 충족'),JSON_OBJECT('value','ANY','label','하나 이상 충족'),JSON_OBJECT('value','NONE','label','조건 없음')),
      '$.field_sets.eligibility[13].options',JSON_ARRAY(JSON_OBJECT('value','STATUTORY_INSURANCE_INCOME','label','법정 사회보험 소득')),
      '$.field_sets.eligibility[15].options',JSON_ARRAY(JSON_OBJECT('value','EMPLOYER','label','사업주'),JSON_OBJECT('value','CONSTRUCTION_SITE','label','건설현장'),JSON_OBJECT('value','EMPLOYER_CALENDAR_MONTH','label','사업주·달력월'),JSON_OBJECT('value','CONSTRUCTION_WORKPLACE_GROUP','label','동일 건설사업장 합산'),JSON_OBJECT('value','DEPENDENT','label','종속 보험')),
      '$.field_sets.eligibility[16].options',JSON_ARRAY(JSON_OBJECT('value','CALENDAR_MONTH','label','달력월')),
      '$.field_sets.eligibility[18].type','text',
      '$.field_sets.eligibility[19].options',JSON_ARRAY(JSON_OBJECT('value','TRANSITION_APPLICABLE','label','경과조치 적용'),JSON_OBJECT('value','TRANSITION_NOT_APPLICABLE','label','경과조치 미적용')),
      '$.field_sets.eligibility[20].options',JSON_ARRAY(JSON_OBJECT('value','HEALTH_INSURANCE','label','건강보험')),
      '$.field_sets.eligibility[21].options',JSON_ARRAY(JSON_OBJECT('value','NATIONAL_PENSION','label','국민연금'),JSON_OBJECT('value','HEALTH_INSURANCE','label','건강보험'),JSON_OBJECT('value','LONG_TERM_CARE','label','장기요양보험'),JSON_OBJECT('value','EMPLOYMENT_INSURANCE','label','고용보험'),JSON_OBJECT('value','INDUSTRIAL_ACCIDENT','label','산재보험')),
      '$.field_sets.eligibility[22].options',JSON_ARRAY(JSON_OBJECT('value','ELIGIBLE','label','가입 대상')),
      '$.field_sets.eligibility[23].options',JSON_ARRAY(JSON_OBJECT('value','NOT_ELIGIBLE','label','가입 제외')),
      '$.field_sets.eligibility[24].options',JSON_ARRAY(JSON_OBJECT('value','CONFIRMATION_REQUIRED','label','확인 필요')),
      '$.field_sets.eligibility[25].options',JSON_ARRAY(JSON_OBJECT('value','CONFIRMED','label','확정'))),
      '$.field_sets.eligibility[1].option_source','$.field_sets.eligibility[1].option_code_group','$.field_sets.eligibility[1].allowed_codes','$.field_sets.eligibility[1].allow_inactive_for_existing_value','$.field_sets.eligibility[1].nullable',
      '$.field_sets.eligibility[2].option_source','$.field_sets.eligibility[2].option_code_group','$.field_sets.eligibility[2].allowed_codes','$.field_sets.eligibility[2].allow_inactive_for_existing_value','$.field_sets.eligibility[2].nullable',
      '$.field_sets.eligibility[5].option_source','$.field_sets.eligibility[5].option_code_group','$.field_sets.eligibility[5].allowed_codes','$.field_sets.eligibility[5].allow_inactive_for_existing_value','$.field_sets.eligibility[5].nullable',
      '$.field_sets.eligibility[6].option_source','$.field_sets.eligibility[6].option_code_group','$.field_sets.eligibility[6].allowed_codes','$.field_sets.eligibility[6].allow_inactive_for_existing_value',
      '$.field_sets.eligibility[8].option_source','$.field_sets.eligibility[8].option_code_group','$.field_sets.eligibility[8].allowed_codes','$.field_sets.eligibility[8].allow_inactive_for_existing_value','$.field_sets.eligibility[8].nullable',
      '$.field_sets.eligibility[9].option_source','$.field_sets.eligibility[9].option_code_group','$.field_sets.eligibility[9].allowed_codes','$.field_sets.eligibility[9].allow_inactive_for_existing_value','$.field_sets.eligibility[9].nullable',
      '$.field_sets.eligibility[13].option_source','$.field_sets.eligibility[13].option_code_group','$.field_sets.eligibility[13].allowed_codes','$.field_sets.eligibility[13].allow_inactive_for_existing_value','$.field_sets.eligibility[13].nullable',
      '$.field_sets.eligibility[15].option_source','$.field_sets.eligibility[15].option_code_group','$.field_sets.eligibility[15].allowed_codes','$.field_sets.eligibility[15].allow_inactive_for_existing_value','$.field_sets.eligibility[15].nullable',
      '$.field_sets.eligibility[16].option_source','$.field_sets.eligibility[16].option_code_group','$.field_sets.eligibility[16].allowed_codes','$.field_sets.eligibility[16].allow_inactive_for_existing_value','$.field_sets.eligibility[16].nullable',
      '$.field_sets.eligibility[18].option_source','$.field_sets.eligibility[18].option_code_group','$.field_sets.eligibility[18].allowed_codes','$.field_sets.eligibility[18].allow_inactive_for_existing_value',
      '$.field_sets.eligibility[19].option_source','$.field_sets.eligibility[19].option_code_group','$.field_sets.eligibility[19].allowed_codes','$.field_sets.eligibility[19].allow_inactive_for_existing_value',
      '$.field_sets.eligibility[20].option_source','$.field_sets.eligibility[20].option_code_group','$.field_sets.eligibility[20].allowed_codes','$.field_sets.eligibility[20].allow_inactive_for_existing_value',
      '$.field_sets.eligibility[21].option_source','$.field_sets.eligibility[21].option_code_group','$.field_sets.eligibility[21].allowed_codes','$.field_sets.eligibility[21].allow_inactive_for_existing_value','$.field_sets.eligibility[21].nullable',
      '$.field_sets.eligibility[22].option_source','$.field_sets.eligibility[22].option_code_group','$.field_sets.eligibility[22].allowed_codes','$.field_sets.eligibility[22].allow_inactive_for_existing_value','$.field_sets.eligibility[22].nullable',
      '$.field_sets.eligibility[23].option_source','$.field_sets.eligibility[23].option_code_group','$.field_sets.eligibility[23].allowed_codes','$.field_sets.eligibility[23].allow_inactive_for_existing_value','$.field_sets.eligibility[23].nullable',
      '$.field_sets.eligibility[24].option_source','$.field_sets.eligibility[24].option_code_group','$.field_sets.eligibility[24].allowed_codes','$.field_sets.eligibility[24].allow_inactive_for_existing_value','$.field_sets.eligibility[24].nullable',
      '$.field_sets.eligibility[25].option_source','$.field_sets.eligibility[25].option_code_group','$.field_sets.eligibility[25].allowed_codes','$.field_sets.eligibility[25].allow_inactive_for_existing_value','$.field_sets.eligibility[25].nullable')
    WHERE code_group='STATUTORY_STANDARD_TYPE' AND code IN('NATIONAL_PENSION','HEALTH_INSURANCE','LONG_TERM_CARE','EMPLOYMENT_INSURANCE','INDUSTRIAL_ACCIDENT');

    IF ROW_COUNT() <> 5 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='보험 Template Down 결과가 올바르지 않습니다.'; END IF;
    DELETE FROM system_codes WHERE id LIKE '20260831-06%';
    IF ROW_COUNT() <> 34 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='법정기준 선택코드 Down 삭제 건수가 올바르지 않습니다.'; END IF;
END$$
CALL migrate_20260831_06_down()$$
DROP PROCEDURE migrate_20260831_06_down$$
DELIMITER ;
