DELIMITER $$
CREATE PROCEDURE `sp_drop_evidence_external_key_unique`()
BEGIN
    DECLARE done INT DEFAULT 0;
    DECLARE table_name_value VARCHAR(128);
    DECLARE table_cursor CURSOR FOR
        SELECT DISTINCT TABLE_NAME
          FROM information_schema.STATISTICS
         WHERE TABLE_SCHEMA = DATABASE()
           AND INDEX_NAME = 'uq_evidence_external_key';
    DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = 1;

    OPEN table_cursor;
    drop_loop: LOOP
        FETCH table_cursor INTO table_name_value;
        IF done = 1 THEN LEAVE drop_loop; END IF;
        SET @sql = CONCAT('ALTER TABLE `', table_name_value, '` DROP INDEX `uq_evidence_external_key`');
        PREPARE statement FROM @sql;
        EXECUTE statement;
        DEALLOCATE PREPARE statement;
    END LOOP;
    CLOSE table_cursor;
END$$
DELIMITER ;
CALL `sp_drop_evidence_external_key_unique`();
DROP PROCEDURE `sp_drop_evidence_external_key_unique`;