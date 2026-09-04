DROP PROCEDURE IF EXISTS `rollback_20260824_05_extend_journal_rule_learning_ssot`;
DELIMITER $$
CREATE PROCEDURE `rollback_20260824_05_extend_journal_rule_learning_ssot`()
BEGIN
    IF EXISTS (SELECT 1 FROM `ledger_journal_rules`) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = '신규 분개규칙이 존재하여 파괴적인 Down Migration을 차단했습니다.';
    END IF;
    IF EXISTS (SELECT 1 FROM `ledger_journal_learning_events` WHERE `event_key` IS NOT NULL OR `voucher_line_source_ref_id` IS NOT NULL) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = '신규 학습 이벤트가 존재하여 파괴적인 Down Migration을 차단했습니다.';
    END IF;
    ALTER TABLE `ledger_journal_learning_events`
        DROP FOREIGN KEY `fk_journal_learning_company`,
        DROP CONSTRAINT `chk_journal_learning_status`,
        DROP CONSTRAINT `chk_journal_learning_trace`,
        DROP INDEX `uk_journal_learning_event_key`,
        DROP INDEX `idx_journal_learning_scope`,
        DROP COLUMN `company_id`, DROP COLUMN `voucher_line_source_ref_id`, DROP COLUMN `event_key`,
        DROP COLUMN `learning_status`, DROP COLUMN `decision_code`, DROP COLUMN `retry_count`, DROP COLUMN `last_error`,
        DROP COLUMN `policy_revision`, DROP COLUMN `policy_snapshot`, DROP COLUMN `processed_at`, DROP COLUMN `processed_by`;
    ALTER TABLE `ledger_journal_rules`
        DROP FOREIGN KEY `fk_journal_rule_company`, DROP FOREIGN KEY `fk_journal_rule_account`,
        DROP CONSTRAINT `chk_journal_rule_origin`, DROP CONSTRAINT `chk_journal_rule_status`,
        DROP CONSTRAINT `chk_journal_rule_side`, DROP CONSTRAINT `chk_journal_rule_status_active`,
        DROP CONSTRAINT `chk_journal_rule_effective_period`, DROP INDEX `uk_journal_rule_company_code`,
        DROP INDEX `idx_journal_rule_evaluation`, DROP INDEX `idx_journal_rule_account`, DROP INDEX `idx_journal_rule_parent`,
        DROP COLUMN `company_id`, DROP COLUMN `condition_hash`, DROP COLUMN `origin_code`, DROP COLUMN `rule_status`,
        DROP COLUMN `accounting_role_code`, DROP COLUMN `debit_credit`, DROP COLUMN `account_id`, DROP COLUMN `amount_policy_code`,
        DROP COLUMN `is_locked`, DROP COLUMN `auto_apply_enabled`, DROP COLUMN `effective_from`, DROP COLUMN `effective_to`,
        DROP COLUMN `priority_no`, DROP COLUMN `revision_no`, DROP COLUMN `policy_revision`, DROP COLUMN `parent_rule_id`,
        ADD UNIQUE KEY `uk_rule_code` (`rule_code`);
END$$
DELIMITER ;
CALL `rollback_20260824_05_extend_journal_rule_learning_ssot`();
DROP PROCEDURE `rollback_20260824_05_extend_journal_rule_learning_ssot`;
