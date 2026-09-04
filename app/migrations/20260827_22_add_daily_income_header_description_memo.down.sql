DELIMITER $$
CREATE PROCEDURE migrate_20260827_22_down()
BEGIN
    IF EXISTS (
        SELECT 1 FROM institution_daily_employment_incomes
        WHERE description IS NOT NULL OR memo IS NOT NULL LIMIT 1
    ) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='비고 또는 메모 자료가 있어 Down할 수 없습니다.';
    END IF;
    ALTER TABLE institution_daily_employment_incomes
        DROP COLUMN memo,
        DROP COLUMN description;
END$$
CALL migrate_20260827_22_down()$$
DROP PROCEDURE migrate_20260827_22_down$$
DELIMITER ;
