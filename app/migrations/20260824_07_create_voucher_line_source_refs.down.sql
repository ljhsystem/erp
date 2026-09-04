DROP PROCEDURE IF EXISTS `rollback_20260824_07_create_voucher_line_source_refs`;
DELIMITER $$
CREATE PROCEDURE `rollback_20260824_07_create_voucher_line_source_refs`()
BEGIN
    IF EXISTS (SELECT 1 FROM `ledger_voucher_line_source_refs`) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = '원천 Line 추적 데이터가 존재하여 Down Migration을 차단했습니다.';
    END IF;
    ALTER TABLE `ledger_journal_learning_events` DROP FOREIGN KEY `fk_journal_learning_source_ref`;
    DROP TABLE `ledger_voucher_line_source_refs`;
END$$
DELIMITER ;
CALL `rollback_20260824_07_create_voucher_line_source_refs`();
DROP PROCEDURE `rollback_20260824_07_create_voucher_line_source_refs`;
