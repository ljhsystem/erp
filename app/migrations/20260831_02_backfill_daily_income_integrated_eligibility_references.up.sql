DELIMITER $$
CREATE PROCEDURE migrate_20260831_02_up()
BEGIN
    IF (SELECT COUNT(*) FROM institution_daily_employment_income_calculation_results
        WHERE calculation_revision_id='4e24672c-3ac3-4c63-b537-63838715d998'
          AND id IN('d4b2ce27-73c0-4d93-9286-523bc286560a','4992fec8-d2a5-4618-ac31-604eab26dde8','cd5820dc-849c-45fd-a7c2-7e7e9157212f')
          AND status_code='EXCLUDED' AND eligibility_status_code='NOT_ELIGIBLE') <> 3 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='승인된 계산결과 3건의 기준선이 다릅니다.';
    END IF;
    IF EXISTS (
        SELECT 1 FROM institution_daily_employment_income_calculation_results
         WHERE id='d4b2ce27-73c0-4d93-9286-523bc286560a' AND eligibility_revision_id<>'20260829-0314-4000-8000-000000000014'
    ) OR EXISTS (
        SELECT 1 FROM institution_daily_employment_income_calculation_results
         WHERE id='4992fec8-d2a5-4618-ac31-604eab26dde8' AND eligibility_revision_id<>'20260829-0321-4000-8000-000000000021'
    ) OR EXISTS (
        SELECT 1 FROM institution_daily_employment_income_calculation_results
         WHERE id='cd5820dc-849c-45fd-a7c2-7e7e9157212f' AND eligibility_revision_id<>'20260829-0303-4000-8000-000000000003'
    ) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='승인된 계산결과의 기존 가입자격 Revision 참조가 다릅니다.';
    END IF;

    UPDATE institution_daily_employment_income_calculation_results
       SET updated_at=updated_at,
           eligibility_revision_id=CASE id
             WHEN 'd4b2ce27-73c0-4d93-9286-523bc286560a' THEN '20260831-1014-4000-8000-000000000014'
             WHEN '4992fec8-d2a5-4618-ac31-604eab26dde8' THEN '20260831-1021-4000-8000-000000000021'
             WHEN 'cd5820dc-849c-45fd-a7c2-7e7e9157212f' THEN '20260831-1003-4000-8000-000000000003'
           END,
           eligibility_snapshot=REPLACE(eligibility_snapshot,
             CASE id WHEN 'd4b2ce27-73c0-4d93-9286-523bc286560a' THEN '20260829-0314-4000-8000-000000000014' WHEN '4992fec8-d2a5-4618-ac31-604eab26dde8' THEN '20260829-0321-4000-8000-000000000021' WHEN 'cd5820dc-849c-45fd-a7c2-7e7e9157212f' THEN '20260829-0303-4000-8000-000000000003' END,
             CASE id WHEN 'd4b2ce27-73c0-4d93-9286-523bc286560a' THEN '20260831-1014-4000-8000-000000000014' WHEN '4992fec8-d2a5-4618-ac31-604eab26dde8' THEN '20260831-1021-4000-8000-000000000021' WHEN 'cd5820dc-849c-45fd-a7c2-7e7e9157212f' THEN '20260831-1003-4000-8000-000000000003' END),
           calculation_basis_snapshot=REPLACE(calculation_basis_snapshot,
             CASE id WHEN 'd4b2ce27-73c0-4d93-9286-523bc286560a' THEN '20260829-0314-4000-8000-000000000014' WHEN '4992fec8-d2a5-4618-ac31-604eab26dde8' THEN '20260829-0321-4000-8000-000000000021' WHEN 'cd5820dc-849c-45fd-a7c2-7e7e9157212f' THEN '20260829-0303-4000-8000-000000000003' END,
             CASE id WHEN 'd4b2ce27-73c0-4d93-9286-523bc286560a' THEN '20260831-1014-4000-8000-000000000014' WHEN '4992fec8-d2a5-4618-ac31-604eab26dde8' THEN '20260831-1021-4000-8000-000000000021' WHEN 'cd5820dc-849c-45fd-a7c2-7e7e9157212f' THEN '20260831-1003-4000-8000-000000000003' END)
     WHERE id IN('d4b2ce27-73c0-4d93-9286-523bc286560a','4992fec8-d2a5-4618-ac31-604eab26dde8','cd5820dc-849c-45fd-a7c2-7e7e9157212f');

    IF ROW_COUNT() <> 3 OR (SELECT COUNT(*) FROM institution_daily_employment_income_calculation_results result_row JOIN system_statutory_standards standard_row ON standard_row.id=result_row.eligibility_revision_id WHERE standard_row.policy_component_code='ELIGIBILITY' AND result_row.id IN('d4b2ce27-73c0-4d93-9286-523bc286560a','4992fec8-d2a5-4618-ac31-604eab26dde8','cd5820dc-849c-45fd-a7c2-7e7e9157212f')) <> 3 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='계산결과 가입자격 참조 3건 이관에 실패했습니다.';
    END IF;
END$$
CALL migrate_20260831_02_up()$$
DROP PROCEDURE migrate_20260831_02_up$$
DELIMITER ;
