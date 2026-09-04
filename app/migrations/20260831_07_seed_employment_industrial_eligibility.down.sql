DELIMITER $$
CREATE PROCEDURE migrate_20260831_07_down()
BEGIN
    IF EXISTS(
        SELECT 1 FROM institution_daily_employment_income_calculation_results
        WHERE eligibility_revision_id LIKE '68310700-%'
    ) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='계산결과가 신규 가입자격 Revision을 참조하여 Down할 수 없습니다.';
    END IF;
    DELETE FROM system_statutory_standard_sources WHERE id LIKE '68311700-%';
    DELETE FROM system_statutory_standards WHERE id LIKE '68310700-%';
END$$
CALL migrate_20260831_07_down()$$
DROP PROCEDURE migrate_20260831_07_down$$
DELIMITER ;
