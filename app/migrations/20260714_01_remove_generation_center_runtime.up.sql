START TRANSACTION;

-- 생성센터 상태값을 분할 계보 상태로 축소한다.
UPDATE `ledger_processing_items`
SET `item_status` = CASE
        WHEN `item_status` IN ('SPLIT', 'MERGED', 'INACTIVE', 'DELETED') THEN `item_status`
        ELSE 'ACTIVE'
    END,
    `is_current` = CASE
        WHEN `item_status` IN ('SPLIT', 'MERGED', 'INACTIVE', 'DELETED') THEN 0
        ELSE 1
    END;

-- 생성센터 폐기 후 증빙 분할 계보에 해당하지 않는 이력은 제거한다.
DELETE FROM `ledger_processing_item_actions`
WHERE `action_type` NOT IN (
    'CREATED',
    'SPLIT',
    'MERGE',
    'ADJUSTMENT',
    'UPDATED',
    'CANCELLED',
    'RESTORED',
    'PARENT_DEACTIVATED'
);

-- 거래·전표 생성 결과는 ledger_evidence_links가 SSOT이므로 action 이력에서도 제거한다.
ALTER TABLE `ledger_processing_item_actions`
    DROP INDEX `idx_processing_item_actions_transaction`,
    DROP INDEX `idx_processing_item_actions_voucher`,
    DROP COLUMN `related_transaction_id`,
    DROP COLUMN `related_voucher_id`;

-- processing item은 증빙 분할 계보와 자식행 구성만 담당한다.
ALTER TABLE `ledger_processing_items`
    DROP INDEX `idx_ledger_processing_items_transaction_status`,
    DROP INDEX `idx_ledger_processing_items_voucher_status`,
    DROP INDEX `idx_ledger_processing_items_readiness_status`,
    DROP COLUMN `transaction_status`,
    DROP COLUMN `voucher_status`,
    DROP COLUMN `readiness_status`,
    DROP COLUMN `correction_status`;

ALTER TABLE `ledger_processing_items`
    COMMENT = '증빙원본 부모·자식 분할 계보와 분할 자식행 값의 SSOT';

ALTER TABLE `ledger_processing_item_actions`
    COMMENT = '증빙원본 분할·병합·조정·취소·복구 감사 이력';

DELETE `rp`
FROM `auth_role_permissions` `rp`
JOIN `auth_permissions` `p` ON `p`.`id` = `rp`.`permission_id`
WHERE `p`.`page_key` = 'ledger.data.create_center'
   OR `p`.`permission_key` IN ('web.ledger.data.create', 'api.import.create_transactions');

DELETE FROM `auth_permissions`
WHERE `page_key` = 'ledger.data.create_center'
   OR `permission_key` IN ('web.ledger.data.create', 'api.import.create_transactions');

DELETE FROM `system_user_settings`
WHERE `page_key` IN ('ledger.data.create', 'ledger.data.create_center');

DELETE FROM `system_menu_registry`
WHERE `menu_key` = 'ledger.data.create_center'
   OR `page_key` = 'ledger.data.create_center'
   OR `default_entry` = '/ledger/data/create';

DELETE FROM `system_page_registry`
WHERE `page_key` = 'ledger.data.create_center'
   OR `default_route_key` = 'web.ledger.data.create'
   OR `default_route_url` = '/ledger/data/create';

COMMIT;
