-- 지급예정 운영 데이터가 있으면 손실 방지를 위해 롤백을 중단한다.
DELIMITER $$
CREATE PROCEDURE `sp_rollback_payment_schedule_ssot`()
BEGIN
    DECLARE schedule_count BIGINT DEFAULT 0;

    SELECT COUNT(*) INTO schedule_count FROM `ledger_payment_schedules`;
    IF schedule_count > 0 THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'payment schedule data exists; down migration is unsafe';
    END IF;

    ALTER TABLE `ledger_evidence_links`
        DROP CONSTRAINT IF EXISTS `chk_evl_payment_schedule_allocation`,
        DROP INDEX IF EXISTS `idx_evl_payment_schedule_target`,
        DROP INDEX IF EXISTS `idx_evl_payment_schedule_evidence`;

    DROP TABLE IF EXISTS `ledger_payment_schedule_histories`;
    DROP TABLE IF EXISTS `ledger_payment_schedules`;
END$$
DELIMITER ;

CALL `sp_rollback_payment_schedule_ssot`();
DROP PROCEDURE `sp_rollback_payment_schedule_ssot`;

INSERT INTO `system_page_registry`
(`page_key`,`module_key`,`module_label`,`menu_key`,`menu_label`,`page_label`,`page_description`,`breadcrumb`,`default_route_key`,`default_route_url`,`source_description`,`is_active`,`created_at`,`updated_at`)
VALUES
('ledger.funds.unlinked_transactions','ledger','회계관리','ledger.funds','자금관리','미연결 거래','회계관리 > 자금관리 > 미연결 거래','회계관리 > 자금관리 > 미연결 거래','web.ledger.funds.unlinked_transactions',NULL,'회계관리 > 자금관리 > 미연결 거래',1,'2026-06-08 20:07:19','2026-06-08 20:07:19')
ON DUPLICATE KEY UPDATE `page_key` = VALUES(`page_key`);

INSERT INTO `system_menu_registry`
(`menu_key`,`page_key`,`module_key`,`menu_label`,`module_order`,`menu_order`,`page_order`,`menu_icon`,`default_entry`,`is_menu`,`visible_in_sidebar`,`visible_in_settings`,`visible_in_sitemap`,`visible_in_navbar`,`is_active`,`created_at`,`updated_at`)
VALUES
('ledger.funds.unlinked_transactions','ledger.funds.unlinked_transactions','ledger','미연결 거래',30,80,70,'bi-link-45deg','/ledger/funds/unlinked-transactions',1,1,0,1,0,1,'2026-06-08 21:12:54','2026-06-08 21:12:54')
ON DUPLICATE KEY UPDATE `menu_key` = VALUES(`menu_key`);

INSERT INTO `auth_permissions`
(`id`,`sort_no`,`page`,`permission_source`,`category`,`permission_key`,`permission_name`,`description`,`page_key`,`is_active`,`created_at`,`created_by`,`updated_at`,`updated_by`)
VALUES
('24530ccd-0695-4c5d-993b-e8cd4b5bed1f',410,'미연결 거래','web','회계관리 > 자금관리','web.ledger.funds.unlinked_transactions','화면조회','미연결입출금 화면 조회','ledger.funds.unlinked_transactions',1,'2026-05-22 11:58:57','SYSTEM:자동','2026-07-27 15:25:18','SYSTEM:자동')
ON DUPLICATE KEY UPDATE `id` = VALUES(`id`);

INSERT INTO `auth_role_permissions`
(`id`,`role_id`,`permission_id`,`created_at`,`created_by`)
VALUES
('6f37b483-c672-4cb7-9f5c-12e162398ea7','08361618-c06d-4fd5-b18d-be61e1b1058e','24530ccd-0695-4c5d-993b-e8cd4b5bed1f','2026-07-25 15:44:04','USER:f113b666-ff40-4f93-a7e7-8cea4cdc9c28'),
('951c8260-b1b8-4329-887b-d269c1a1e79a','c1c90ecf-1a44-470c-8d9c-4d6e671cdcfa','24530ccd-0695-4c5d-993b-e8cd4b5bed1f','2026-06-08 16:41:48','USER:f113b666-ff40-4f93-a7e7-8cea4cdc9c28')
ON DUPLICATE KEY UPDATE `id` = VALUES(`id`);

UPDATE `system_page_registry`
SET `page_label` = '지급스케줄',
    `page_description` = '회계관리 > 자금관리 > 지급스케줄',
    `breadcrumb` = '회계관리 > 자금관리 > 지급스케줄',
    `source_description` = '회계관리 > 자금관리 > 지급스케줄',
    `updated_at` = CURRENT_TIMESTAMP
WHERE `page_key` = 'ledger.funds.payment_schedule';

UPDATE `system_menu_registry`
SET `menu_label` = '지급스케줄',
    `updated_at` = CURRENT_TIMESTAMP
WHERE `menu_key` = 'ledger.funds.payment_schedule';

UPDATE `auth_permissions`
SET `page` = '지급스케줄',
    `updated_at` = CURRENT_TIMESTAMP,
    `updated_by` = 'SYSTEM:MIGRATION'
WHERE `permission_key` = 'web.ledger.funds.payment_schedule';
