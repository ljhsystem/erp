DROP PROCEDURE IF EXISTS `migrate_20260824_05_extend_journal_rule_learning_ssot`;
DELIMITER $$
CREATE PROCEDURE `migrate_20260824_05_extend_journal_rule_learning_ssot`()
BEGIN
    DECLARE existing_rule_count INT DEFAULT 0;
    DECLARE company_count INT DEFAULT 0;

    SELECT COUNT(*) INTO existing_rule_count FROM `ledger_journal_rules`;
    SELECT COUNT(*) INTO company_count FROM `system_company`;

    ALTER TABLE `ledger_journal_rules`
        ADD COLUMN IF NOT EXISTS `company_id` varchar(50) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NULL COMMENT '회사 식별자' AFTER `id`,
        ADD COLUMN IF NOT EXISTS `condition_hash` char(64) NULL COMMENT '정규화 조건 SHA-256' AFTER `import_type`,
        ADD COLUMN IF NOT EXISTS `origin_code` varchar(20) NOT NULL DEFAULT 'USER' COMMENT 'USER 또는 SYSTEM' AFTER `condition_hash`,
        ADD COLUMN IF NOT EXISTS `rule_status` varchar(20) NOT NULL DEFAULT 'INACTIVE' COMMENT 'ACTIVE/CANDIDATE/INACTIVE/REJECTED' AFTER `origin_code`,
        ADD COLUMN IF NOT EXISTS `accounting_role_code` varchar(50) NULL COMMENT '회계 역할 코드' AFTER `rule_status`,
        ADD COLUMN IF NOT EXISTS `debit_credit` varchar(6) NULL COMMENT 'DEBIT 또는 CREDIT' AFTER `accounting_role_code`,
        ADD COLUMN IF NOT EXISTS `account_id` varchar(36) NULL COMMENT '추천 계정과목' AFTER `debit_credit`,
        ADD COLUMN IF NOT EXISTS `amount_policy_code` varchar(30) NOT NULL DEFAULT 'SOURCE_ALLOCATED' COMMENT '금액정책' AFTER `account_id`,
        ADD COLUMN IF NOT EXISTS `is_locked` tinyint(1) NOT NULL DEFAULT 0 COMMENT '사용자 잠금 여부' AFTER `amount_policy_code`,
        ADD COLUMN IF NOT EXISTS `auto_apply_enabled` tinyint(1) NOT NULL DEFAULT 0 COMMENT '자동적용 허용 여부' AFTER `is_locked`,
        ADD COLUMN IF NOT EXISTS `effective_from` date NULL COMMENT '적용 시작일' AFTER `auto_apply_enabled`,
        ADD COLUMN IF NOT EXISTS `effective_to` date NULL COMMENT '적용 종료일' AFTER `effective_from`,
        ADD COLUMN IF NOT EXISTS `priority_no` int NOT NULL DEFAULT 100 COMMENT '추천 우선순위' AFTER `effective_to`,
        ADD COLUMN IF NOT EXISTS `revision_no` int unsigned NOT NULL DEFAULT 0 COMMENT '현재 Revision 번호' AFTER `priority_no`,
        ADD COLUMN IF NOT EXISTS `policy_revision` int unsigned NULL COMMENT '판단 정책 Revision' AFTER `revision_no`,
        ADD COLUMN IF NOT EXISTS `parent_rule_id` varchar(36) NULL COMMENT '파생 원본 규칙' AFTER `policy_revision`;

    IF existing_rule_count > 0 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = '기존 복합 분개규칙이 존재하여 자동 단일역할 Backfill을 수행할 수 없습니다.';
    END IF;
    IF company_count <> 1 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = '분개규칙 회사 범위를 확정하려면 system_company가 정확히 1건이어야 합니다.';
    END IF;

    ALTER TABLE `ledger_journal_rules`
        MODIFY `company_id` varchar(50) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL COMMENT '회사 식별자',
        MODIFY `condition_hash` char(64) NOT NULL COMMENT '정규화 조건 SHA-256',
        MODIFY `accounting_role_code` varchar(50) NOT NULL COMMENT '회계 역할 코드',
        MODIFY `debit_credit` varchar(6) NOT NULL COMMENT 'DEBIT 또는 CREDIT',
        MODIFY `account_id` varchar(36) NOT NULL COMMENT '추천 계정과목';

    IF EXISTS (SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='ledger_journal_rules' AND INDEX_NAME='uk_rule_code') THEN
        ALTER TABLE `ledger_journal_rules` DROP INDEX `uk_rule_code`;
    END IF;
    ALTER TABLE `ledger_journal_rules`
        ADD UNIQUE KEY `uk_journal_rule_company_code` (`company_id`,`rule_code`),
        ADD KEY `idx_journal_rule_evaluation` (`company_id`,`rule_status`,`origin_code`,`condition_hash`,`accounting_role_code`,`debit_credit`,`effective_from`,`effective_to`),
        ADD KEY `idx_journal_rule_account` (`account_id`),
        ADD KEY `idx_journal_rule_parent` (`parent_rule_id`),
        ADD CONSTRAINT `fk_journal_rule_company` FOREIGN KEY (`company_id`) REFERENCES `system_company` (`id`),
        ADD CONSTRAINT `fk_journal_rule_account` FOREIGN KEY (`account_id`) REFERENCES `ledger_accounts` (`id`),
        ADD CONSTRAINT `chk_journal_rule_origin` CHECK (`origin_code` IN ('USER','SYSTEM')),
        ADD CONSTRAINT `chk_journal_rule_status` CHECK (`rule_status` IN ('ACTIVE','CANDIDATE','INACTIVE','REJECTED')),
        ADD CONSTRAINT `chk_journal_rule_side` CHECK (`debit_credit` IN ('DEBIT','CREDIT')),
        ADD CONSTRAINT `chk_journal_rule_status_active` CHECK (`is_active` = IF(`deleted_at` IS NULL AND `rule_status`='ACTIVE',1,0)),
        ADD CONSTRAINT `chk_journal_rule_effective_period` CHECK (`effective_to` IS NULL OR `effective_from` IS NULL OR `effective_to` >= `effective_from`);

    ALTER TABLE `ledger_journal_learning_events`
        ADD COLUMN IF NOT EXISTS `company_id` varchar(50) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NULL COMMENT '회사 식별자' AFTER `id`,
        ADD COLUMN IF NOT EXISTS `voucher_line_source_ref_id` varchar(36) NULL COMMENT '학습 원천추적 행' AFTER `voucher_line_id`,
        ADD COLUMN IF NOT EXISTS `event_key` char(64) NULL COMMENT '학습 이벤트 멱등키' AFTER `event_type`,
        ADD COLUMN IF NOT EXISTS `learning_status` varchar(20) NOT NULL DEFAULT 'PENDING' COMMENT '학습 처리상태' AFTER `event_key`,
        ADD COLUMN IF NOT EXISTS `decision_code` varchar(50) NULL COMMENT '학습 결정코드' AFTER `learning_status`,
        ADD COLUMN IF NOT EXISTS `retry_count` int unsigned NOT NULL DEFAULT 0 COMMENT '재시도 횟수' AFTER `decision_code`,
        ADD COLUMN IF NOT EXISTS `last_error` varchar(1000) NULL COMMENT '최종 오류' AFTER `retry_count`,
        ADD COLUMN IF NOT EXISTS `policy_revision` int unsigned NULL COMMENT '학습정책 Revision' AFTER `last_error`,
        ADD COLUMN IF NOT EXISTS `policy_snapshot` longtext NULL COMMENT '학습정책 Snapshot JSON' AFTER `policy_revision`,
        ADD COLUMN IF NOT EXISTS `processed_at` datetime NULL COMMENT '처리일시' AFTER `policy_snapshot`,
        ADD COLUMN IF NOT EXISTS `processed_by` varchar(100) NULL COMMENT '처리 Actor' AFTER `processed_at`;

    IF EXISTS (SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='ledger_journal_learning_events' AND INDEX_NAME='uk_ljle_voucher_line_event') THEN
        IF NOT EXISTS (SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='ledger_journal_learning_events' AND INDEX_NAME='idx_ljle_voucher_line_fk') THEN
            ALTER TABLE `ledger_journal_learning_events` ADD KEY `idx_ljle_voucher_line_fk` (`voucher_line_id`);
        END IF;
        ALTER TABLE `ledger_journal_learning_events` DROP INDEX `uk_ljle_voucher_line_event`;
    END IF;
    ALTER TABLE `ledger_journal_learning_events`
        ADD UNIQUE KEY `uk_journal_learning_event_key` (`event_key`),
        ADD KEY `idx_journal_learning_scope` (`company_id`,`learning_status`,`event_type`,`created_at`),
        ADD CONSTRAINT `fk_journal_learning_company` FOREIGN KEY (`company_id`) REFERENCES `system_company` (`id`),
        ADD CONSTRAINT `chk_journal_learning_status` CHECK (`learning_status` IN ('PENDING','PROCESSED','IGNORED','CONFLICT','FAILED')),
        ADD CONSTRAINT `chk_journal_learning_trace` CHECK (`event_type` <> 'POSTED_CONFIRMATION' OR (`voucher_line_source_ref_id` IS NOT NULL AND `event_key` IS NOT NULL));
END$$
DELIMITER ;
CALL `migrate_20260824_05_extend_journal_rule_learning_ssot`();
DROP PROCEDURE `migrate_20260824_05_extend_journal_rule_learning_ssot`;
