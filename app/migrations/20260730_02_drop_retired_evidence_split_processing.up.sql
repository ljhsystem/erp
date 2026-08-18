-- Evidence Split 및 생성센터 processing 구조 최종 제거.
-- 운영 적용 전 별도 DB 변경 승인이 필요하다.

ALTER TABLE `ledger_voucher_lines`
    DROP COLUMN IF EXISTS `processing_item_id`;

DROP TABLE IF EXISTS `ledger_processing_item_actions`;
DROP TABLE IF EXISTS `ledger_processing_items`;

DELETE `rp`
FROM `auth_role_permissions` `rp`
JOIN `auth_permissions` `p` ON `p`.`id` = `rp`.`permission_id`
WHERE `p`.`permission_key` IN (
    'api.import.evidence.split_child',
    'api.import.evidence.processing_child.update',
    'api.import.evidence.processing_child.delete',
    'api.ledger.evidence_split.create',
    'api.ledger.evidence_split.update',
    'api.ledger.evidence_split.delete'
);

DELETE FROM `auth_permissions`
WHERE `permission_key` IN (
    'api.import.evidence.split_child',
    'api.import.evidence.processing_child.update',
    'api.import.evidence.processing_child.delete',
    'api.ledger.evidence_split.create',
    'api.ledger.evidence_split.update',
    'api.ledger.evidence_split.delete'
);
