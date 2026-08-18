START TRANSACTION;

-- 주의: 이 down Migration은 삭제된 스키마만 복구한다.
-- up Migration에서 삭제된 action, 권한, 사용자 설정 및 메뉴 운영 데이터는 복구하지 않는다.

ALTER TABLE `ledger_processing_items`
    ADD COLUMN `transaction_status` VARCHAR(30) NOT NULL DEFAULT 'NONE' COMMENT '이 processing item 기준 거래 생성 상태(NONE, PARTIAL, PROCESSING, CREATED, ERROR 등)' AFTER `item_status`,
    ADD COLUMN `voucher_status` VARCHAR(30) NOT NULL DEFAULT 'NONE' COMMENT '이 processing item 기준 전표 생성/전기 상태(NONE, PARTIAL, PROCESSING, CREATED, POSTED, ERROR 등)' AFTER `transaction_status`,
    ADD COLUMN `readiness_status` VARCHAR(30) NOT NULL DEFAULT 'UNKNOWN' COMMENT '이 processing item의 생성 준비 상태(UNKNOWN, READY, NOT_READY, PARTIAL, BLOCKED 등)' AFTER `voucher_status`,
    ADD COLUMN `correction_status` VARCHAR(30) NOT NULL DEFAULT 'NONE' COMMENT '이 processing item의 보정 상태(NONE, NEEDS_CORRECTION, CORRECTED, REJECTED 등)' AFTER `readiness_status`;

CREATE INDEX `idx_ledger_processing_items_transaction_status`
    ON `ledger_processing_items` (`transaction_status`);
CREATE INDEX `idx_ledger_processing_items_voucher_status`
    ON `ledger_processing_items` (`voucher_status`);
CREATE INDEX `idx_ledger_processing_items_readiness_status`
    ON `ledger_processing_items` (`readiness_status`);

ALTER TABLE `ledger_processing_item_actions`
    ADD COLUMN `related_transaction_id` VARCHAR(36) COLLATE utf8mb4_general_ci NULL DEFAULT NULL COMMENT '관련 거래 ID(거래 생성/연결/재생성 시)' AFTER `related_processing_item_id`,
    ADD COLUMN `related_voucher_id` VARCHAR(36) COLLATE utf8mb4_general_ci NULL DEFAULT NULL COMMENT '관련 전표 ID(전표 생성/연결/재생성 시)' AFTER `related_transaction_id`;

CREATE INDEX `idx_processing_item_actions_transaction`
    ON `ledger_processing_item_actions` (`related_transaction_id`);
CREATE INDEX `idx_processing_item_actions_voucher`
    ON `ledger_processing_item_actions` (`related_voucher_id`);

ALTER TABLE `ledger_processing_items`
    COMMENT = '';

ALTER TABLE `ledger_processing_item_actions`
    COMMENT = '생성센터 처리 Item 작업 이력';

COMMIT;
