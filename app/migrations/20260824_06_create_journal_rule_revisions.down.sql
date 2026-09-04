DROP PROCEDURE IF EXISTS `rollback_20260824_06_create_journal_rule_revisions`;
DELIMITER $$
CREATE PROCEDURE `rollback_20260824_06_create_journal_rule_revisions`()
BEGIN
    IF EXISTS (SELECT 1 FROM `ledger_journal_rule_revisions`) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = '분개규칙 Revision이 존재하여 Down Migration을 차단했습니다.';
    END IF;
    DROP TABLE `ledger_journal_rule_revisions`;
END$$
DELIMITER ;
CALL `rollback_20260824_06_create_journal_rule_revisions`();
DROP PROCEDURE `rollback_20260824_06_create_journal_rule_revisions`;
