DELIMITER $$
CREATE PROCEDURE migrate_20260827_24_down()
BEGIN
    IF EXISTS (SELECT 1 FROM system_migration_history LIMIT 1) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Migration 이력이 있어 이력 원장을 제거할 수 없습니다.';
    END IF;
    DROP TABLE system_migration_history;
END$$
CALL migrate_20260827_24_down()$$
DROP PROCEDURE migrate_20260827_24_down$$
DELIMITER ;
