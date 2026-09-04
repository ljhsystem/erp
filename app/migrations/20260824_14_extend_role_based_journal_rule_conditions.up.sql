DROP PROCEDURE IF EXISTS `migrate_20260824_14_extend_role_based_journal_rule_conditions`;
DELIMITER $$
CREATE PROCEDURE `migrate_20260824_14_extend_role_based_journal_rule_conditions`()
BEGIN
    IF (SELECT COUNT(*) FROM `ledger_journal_rules`) <> 0 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='기존 분개규칙이 존재하여 역할형 규칙으로 자동 변환할 수 없습니다.';
    END IF;
    IF (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='ledger_journal_rules') = 0
       OR (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='ledger_journal_rules' AND COLUMN_NAME='credit_account_id' AND DATA_TYPE='varchar' AND CHARACTER_MAXIMUM_LENGTH=36 AND IS_NULLABLE='NO') <> 1
       OR (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='ledger_journal_rules' AND COLUMN_NAME IN ('company_id','condition_hash','rule_status','accounting_role_code','debit_credit','account_id','operation_type','import_type','effective_from','effective_to','deleted_at')) <> 11 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='ledger_journal_rules 운영 DDL이 승인된 Preflight와 다릅니다.';
    END IF;
    IF (SELECT COUNT(*) FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME='ledger_journal_rules' AND CONSTRAINT_NAME='fk_journal_rules_credit_account') <> 1 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='credit_account_id 기존 FK를 확인할 수 없습니다.';
    END IF;

    ALTER TABLE `ledger_journal_rules`
        MODIFY `credit_account_id` varchar(36) NULL COMMENT '대변계정',
        ADD COLUMN `source_type` varchar(50) NULL COMMENT '실제 원천 저장소·도메인 유형' AFTER `import_type`,
        ADD COLUMN `source_line_type` varchar(50) NULL COMMENT '원천 내부 Line 역할' AFTER `source_type`,
        ADD COLUMN `item_code` varchar(50) NULL COMMENT '원천 도메인 공식 업무분류 코드' AFTER `source_line_type`,
        ADD KEY `idx_journal_rule_role_evaluation` (`company_id`,`rule_status`,`operation_type`,`import_type`,`accounting_role_code`,`deleted_at`,`priority_no`),
        ADD KEY `idx_journal_rule_condition_hash` (`company_id`,`condition_hash`,`rule_status`,`deleted_at`),
        ADD KEY `idx_journal_rule_source_condition` (`company_id`,`source_type`,`source_line_type`,`item_code`,`rule_status`,`deleted_at`);

    IF (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='ledger_journal_rules' AND COLUMN_NAME='credit_account_id' AND IS_NULLABLE='YES') <> 1
       OR (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='ledger_journal_rules' AND COLUMN_NAME IN ('source_type','source_line_type','item_code') AND DATA_TYPE='varchar' AND CHARACTER_MAXIMUM_LENGTH=50 AND IS_NULLABLE='YES') <> 3 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='역할형 분개규칙 컬럼 확장 검증에 실패했습니다.';
    END IF;
END$$
DELIMITER ;
CALL `migrate_20260824_14_extend_role_based_journal_rule_conditions`();
DROP PROCEDURE `migrate_20260824_14_extend_role_based_journal_rule_conditions`;
