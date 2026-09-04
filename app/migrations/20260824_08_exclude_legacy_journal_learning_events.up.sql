DROP PROCEDURE IF EXISTS `migrate_20260824_08_exclude_legacy_journal_learning_events`;
DELIMITER $$
CREATE PROCEDURE `migrate_20260824_08_exclude_legacy_journal_learning_events`()
BEGIN
    DECLARE legacy_count INT DEFAULT 0;
    SELECT COUNT(*) INTO legacy_count
    FROM `ledger_journal_learning_events`
    WHERE `voucher_line_source_ref_id` IS NULL AND `event_key` IS NULL;
    IF legacy_count <> 5 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = '승인된 Legacy Learning Event 5건과 실제 대상 건수가 다릅니다.';
    END IF;
    UPDATE `ledger_journal_learning_events`
    SET `learning_status`='IGNORED', `decision_code`='LEGACY_EVENT_EXCLUDED',
        `failure_type`=COALESCE(`failure_type`,'LEGACY_EVENT_EXCLUDED')
    WHERE `voucher_line_source_ref_id` IS NULL AND `event_key` IS NULL;
END$$
DELIMITER ;
CALL `migrate_20260824_08_exclude_legacy_journal_learning_events`();
DROP PROCEDURE `migrate_20260824_08_exclude_legacy_journal_learning_events`;
