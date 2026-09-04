DELIMITER $$
CREATE PROCEDURE migrate_20260827_15_down()
BEGIN
    IF EXISTS (SELECT 1 FROM institution_daily_employment_income_allocations LIMIT 1)
       OR EXISTS (SELECT 1 FROM institution_daily_employment_income_calculation_results LIMIT 1)
       OR EXISTS (SELECT 1 FROM institution_daily_employment_income_calculation_revisions LIMIT 1) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='기관계산 Revision·Result·Allocation 자료가 있어 Down할 수 없습니다.';
    END IF;
    DROP TABLE institution_daily_employment_income_allocations;
    DROP TABLE institution_daily_employment_income_calculation_results;
    DROP TABLE institution_daily_employment_income_calculation_revisions;
END$$
CALL migrate_20260827_15_down()$$
DROP PROCEDURE migrate_20260827_15_down$$
DELIMITER ;
