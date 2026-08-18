DROP PROCEDURE IF EXISTS `migrate_20260815_05_add_journal_feedback_integrity`;
DELIMITER $$
CREATE PROCEDURE `migrate_20260815_05_add_journal_feedback_integrity`()
BEGIN
    IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ledger_voucher_lines' AND COLUMN_NAME = 'recommended_account_id') THEN
        ALTER TABLE `ledger_voucher_lines`
            ADD COLUMN `recommended_account_id` varchar(36) NULL COMMENT '최초 추천 계정과목' AFTER `is_user_modified`,
            ADD COLUMN `recommended_line_type` varchar(10) NULL COMMENT '최초 추천 차대구분' AFTER `recommended_account_id`,
            ADD COLUMN `recommended_amount` decimal(15,2) NULL COMMENT '최초 추천 금액' AFTER `recommended_line_type`,
            ADD KEY `idx_voucher_lines_recommended_account` (`recommended_account_id`);
    END IF;

    IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ledger_journal_learning_events' AND COLUMN_NAME = 'event_type') THEN
        ALTER TABLE `ledger_journal_learning_events`
            MODIFY COLUMN `voucher_line_id` varchar(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL COMMENT '전표 라인',
            ADD COLUMN `event_type` varchar(30) NULL COMMENT '학습 이벤트 유형' AFTER `id`,
            ADD UNIQUE KEY `uk_ljle_voucher_line_event` (`voucher_line_id`, `event_type`),
            ADD KEY `idx_ljle_feedback_context` (`event_type`, `transaction_direction`, `import_type`, `voucher_id`),
            ADD CONSTRAINT `fk_ljle_voucher_line` FOREIGN KEY (`voucher_line_id`) REFERENCES `ledger_voucher_lines` (`id`) ON DELETE RESTRICT;
    END IF;

    IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ledger_journal_recent_patterns' AND COLUMN_NAME = 'legacy_usage_count') THEN
        ALTER TABLE `ledger_journal_recent_patterns`
            ADD COLUMN `legacy_usage_count` int unsigned NOT NULL DEFAULT 0 COMMENT 'Feedback Writer 이전 보존 사용횟수' AFTER `usage_count`;
        UPDATE `ledger_journal_recent_patterns` SET `legacy_usage_count` = COALESCE(`usage_count`, 0);
    END IF;

    IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ledger_journal_client_account_patterns' AND COLUMN_NAME = 'legacy_usage_count') THEN
        ALTER TABLE `ledger_journal_client_account_patterns`
            ADD COLUMN `legacy_usage_count` int unsigned NOT NULL DEFAULT 0 COMMENT 'Feedback Writer 이전 보존 사용횟수' AFTER `usage_count`,
            ADD COLUMN `legacy_recent_score` decimal(14,4) NOT NULL DEFAULT 0 COMMENT 'Feedback Writer 이전 보존 최근점수' AFTER `recent_score`;
        UPDATE `ledger_journal_client_account_patterns`
        SET `legacy_usage_count` = COALESCE(`usage_count`, 0),
            `legacy_recent_score` = COALESCE(`recent_score`, 0);
    END IF;
END$$
DELIMITER ;
CALL `migrate_20260815_05_add_journal_feedback_integrity`();
DROP PROCEDURE `migrate_20260815_05_add_journal_feedback_integrity`;
