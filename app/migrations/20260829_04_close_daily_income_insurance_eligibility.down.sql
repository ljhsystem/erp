DELIMITER $$
CREATE PROCEDURE migrate_20260829_04_down()
BEGIN
    IF EXISTS(SELECT 1 FROM institution_daily_employment_income_calculation_results WHERE eligibility_revision_id IS NOT NULL OR eligibility_snapshot IS NOT NULL LIMIT 1) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='가입자격 계산 Snapshot이 있어 Down을 중단합니다.';
    END IF;
    ALTER TABLE institution_daily_employment_income_calculation_results
      DROP FOREIGN KEY fk_daily_calc_result_eligibility,
      DROP CONSTRAINT ck_daily_calc_result_eligibility_snapshot,
      DROP INDEX idx_daily_calc_result_eligibility,
      DROP COLUMN eligibility_snapshot,DROP COLUMN eligibility_revision_id;
END$$
CALL migrate_20260829_04_down()$$
DROP PROCEDURE migrate_20260829_04_down$$
DELIMITER ;
