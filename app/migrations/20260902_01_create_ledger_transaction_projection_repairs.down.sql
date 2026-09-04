DELIMITER $$
CREATE PROCEDURE rollback_transaction_projection_repairs()
BEGIN
    IF EXISTS (SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='ledger_transaction_projection_repairs') THEN
        IF EXISTS (SELECT 1 FROM ledger_transaction_projection_repairs LIMIT 1) THEN
            SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='감사행이 존재하여 테이블 삭제를 차단합니다.';
        END IF;
        DROP TABLE ledger_transaction_projection_repairs;
    END IF;
END$$
CALL rollback_transaction_projection_repairs()$$
DROP PROCEDURE rollback_transaction_projection_repairs$$
DELIMITER ;
