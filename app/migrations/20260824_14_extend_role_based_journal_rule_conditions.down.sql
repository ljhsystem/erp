DROP PROCEDURE IF EXISTS `rollback_20260824_14_extend_role_based_journal_rule_conditions`;
DELIMITER $$
CREATE PROCEDURE `rollback_20260824_14_extend_role_based_journal_rule_conditions`()
BEGIN
    IF EXISTS (SELECT 1 FROM `ledger_journal_rules`)
       OR EXISTS (SELECT 1 FROM `ledger_journal_rule_revisions`) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='역할형 Rule 또는 Revision이 존재하여 파괴적인 Down Migration을 차단했습니다.';
    END IF;
    IF EXISTS (SELECT 1 FROM `ledger_journal_rules` WHERE `source_type` IS NOT NULL OR `source_line_type` IS NOT NULL OR `item_code` IS NOT NULL OR `credit_account_id` IS NULL) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='역할형 조건 또는 NULL 대변계정 데이터가 존재하여 Down Migration을 차단했습니다.';
    END IF;
    ALTER TABLE `ledger_journal_rules`
        DROP INDEX `idx_journal_rule_role_evaluation`,
        DROP INDEX `idx_journal_rule_condition_hash`,
        DROP INDEX `idx_journal_rule_source_condition`,
        DROP COLUMN `item_code`,
        DROP COLUMN `source_line_type`,
        DROP COLUMN `source_type`,
        MODIFY `credit_account_id` varchar(36) NOT NULL COMMENT '대변계정';
END$$
DELIMITER ;
CALL `rollback_20260824_14_extend_role_based_journal_rule_conditions`();
DROP PROCEDURE `rollback_20260824_14_extend_role_based_journal_rule_conditions`;
