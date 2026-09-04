DELIMITER $$
CREATE PROCEDURE migrate_20260831_08_up()
BEGIN
    IF EXISTS (SELECT 1 FROM system_codes WHERE code_group='INSURANCE_ELIGIBILITY_REASON') THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='보험 가입자격 판정사유 코드그룹이 이미 존재합니다.';
    END IF;

    INSERT INTO system_codes
        (id,sort_no,code_group,group_name,code,code_name,extra_data,note,is_active,created_by,updated_by)
    VALUES
        ('20260831-0801-4000-8000-000000000001',98001,'INSURANCE_ELIGIBILITY_REASON','보험 가입자격 판정사유','POLICY_CONDITIONS_MET','법정 가입요건 충족',JSON_OBJECT('reason_detail','법정 가입요건을 충족하여 보험 적용대상으로 판정했습니다.'),'보험 가입자격 판정사유',1,'SYSTEM:MIGRATION','SYSTEM:MIGRATION'),
        ('20260831-0802-4000-8000-000000000002',98002,'INSURANCE_ELIGIBILITY_REASON','보험 가입자격 판정사유','CONTINUOUS_EMPLOYMENT_PERIOD_NOT_MET','계속고용기간 요건 미충족',JSON_OBJECT('reason_detail','1개월 이상 계속근로 요건을 충족하지 않아 보험 적용대상에서 제외했습니다.'),'보험 가입자격 판정사유',1,'SYSTEM:MIGRATION','SYSTEM:MIGRATION'),
        ('20260831-0803-4000-8000-000000000003',98003,'INSURANCE_ELIGIBILITY_REASON','보험 가입자격 판정사유','MONTHLY_CONDITIONS_NOT_MET','월 근무기준 미충족',JSON_OBJECT('reason_detail','월 근무일수·근로시간·소득 조건의 법정 가입기준을 충족하지 않았습니다.'),'보험 가입자격 판정사유',1,'SYSTEM:MIGRATION','SYSTEM:MIGRATION'),
        ('20260831-0804-4000-8000-000000000004',98004,'INSURANCE_ELIGIBILITY_REASON','보험 가입자격 판정사유','MINIMUM_AGE_NOT_MET','최소 가입연령 요건 미충족',JSON_OBJECT('reason_detail','법정 최소 가입연령 요건을 충족하지 않았습니다.'),'보험 가입자격 판정사유',1,'SYSTEM:MIGRATION','SYSTEM:MIGRATION'),
        ('20260831-0805-4000-8000-000000000005',98005,'INSURANCE_ELIGIBILITY_REASON','보험 가입자격 판정사유','MAXIMUM_AGE_EXCLUDED','가입연령 상한 제외',JSON_OBJECT('reason_detail','법정 가입연령 상한에 따라 보험 적용대상에서 제외했습니다.'),'보험 가입자격 판정사유',1,'SYSTEM:MIGRATION','SYSTEM:MIGRATION'),
        ('20260831-0806-4000-8000-000000000006',98006,'INSURANCE_ELIGIBILITY_REASON','보험 가입자격 판정사유','MISSING_ELIGIBILITY_INPUT','가입자격 판정정보 확인 필요',JSON_OBJECT('reason_detail','가입자격 판정에 필요한 실제 근로자료 또는 근로자 정보가 누락되었습니다.'),'보험 가입자격 판정사유',1,'SYSTEM:MIGRATION','SYSTEM:MIGRATION'),
        ('20260831-0807-4000-8000-000000000007',98007,'INSURANCE_ELIGIBILITY_REASON','보험 가입자격 판정사유','DEPENDENT_INSURANCE_RESULT','건강보험 판정 결과에 따른 제외',JSON_OBJECT('reason_detail','건강보험 가입대상에서 제외되어 장기요양보험도 적용하지 않습니다.'),'보험 가입자격 판정사유',1,'SYSTEM:MIGRATION','SYSTEM:MIGRATION'),
        ('20260831-0808-4000-8000-000000000008',98008,'INSURANCE_ELIGIBILITY_REASON','보험 가입자격 판정사유','POLICY_CONDITIONS_NOT_MET','법정 가입요건 미충족',JSON_OBJECT('reason_detail','법정 가입요건 중 최종 판정에 필요한 조건을 충족하지 않았습니다.'),'보험 가입자격 판정사유',1,'SYSTEM:MIGRATION','SYSTEM:MIGRATION'),
        ('20260831-0809-4000-8000-000000000009',98009,'INSURANCE_ELIGIBILITY_REASON','보험 가입자격 판정사유','PARTIAL_COMPONENT_APPLICABILITY','보험 구성요소 일부 적용',JSON_OBJECT('reason_detail','보험 구성요소별 가입요건 판정 결과가 서로 달라 일부 항목만 적용합니다.'),'보험 가입자격 판정사유',1,'SYSTEM:MIGRATION','SYSTEM:MIGRATION'),
        ('20260831-0810-4000-8000-000000000010',98010,'INSURANCE_ELIGIBILITY_REASON','보험 가입자격 판정사유','REQUIRED_FACT_MISSING','가입자격 판정사실 확인 필요',JSON_OBJECT('reason_detail','가입자격 판정에 필요한 사실이 확인되지 않았습니다.'),'보험 가입자격 판정사유',1,'SYSTEM:MIGRATION','SYSTEM:MIGRATION');

    IF (SELECT COUNT(*) FROM system_codes WHERE code_group='INSURANCE_ELIGIBILITY_REASON') <> 10 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='보험 가입자격 판정사유 코드 등록 결과가 올바르지 않습니다.';
    END IF;
END$$
CALL migrate_20260831_08_up()$$
DROP PROCEDURE migrate_20260831_08_up$$
DELIMITER ;
