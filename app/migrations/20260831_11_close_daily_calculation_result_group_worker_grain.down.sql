DELIMITER $$
CREATE PROCEDURE migrate_20260831_11_down()
BEGIN
    IF EXISTS (
        SELECT 1
        FROM institution_daily_employment_income_calculation_results
        GROUP BY calculation_revision_id,result_type_code,worker_client_id,
            workplace_scope_key,workday_scope_key,application_from,application_to,
            payment_date,payment_sequence
        HAVING COUNT(*) > 1
        LIMIT 1
    ) THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT='동일 근로자의 복수 Group 계산결과가 있어 기존 Grain으로 복원할 수 없습니다.';
    END IF;

    ALTER TABLE institution_daily_employment_income_calculation_results
        DROP INDEX uq_daily_calc_result_grain,
        ADD UNIQUE KEY uq_daily_calc_result_grain (
            calculation_revision_id,result_type_code,worker_client_id,
            workplace_scope_key,workday_scope_key,application_from,application_to,
            payment_date,payment_sequence
        );
END$$
CALL migrate_20260831_11_down()$$
DROP PROCEDURE migrate_20260831_11_down$$
DELIMITER ;
