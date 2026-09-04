DELIMITER $$
CREATE PROCEDURE migrate_20260831_11_up()
BEGIN
    IF EXISTS (
        SELECT 1
        FROM institution_daily_employment_income_calculation_results
        WHERE daily_employment_income_item_id IS NULL
        LIMIT 1
    ) THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT='Item ID가 없는 일용 계산결과가 있어 Group×근로자 Grain 전환을 중단합니다.';
    END IF;

    ALTER TABLE institution_daily_employment_income_calculation_results
        DROP INDEX uq_daily_calc_result_grain,
        ADD UNIQUE KEY uq_daily_calc_result_grain (
            calculation_revision_id,result_type_code,worker_client_id,
            daily_employment_income_item_id,workplace_scope_key,workday_scope_key,
            application_from,application_to,payment_date,payment_sequence
        );
END$$
CALL migrate_20260831_11_up()$$
DROP PROCEDURE migrate_20260831_11_up$$
DELIMITER ;
