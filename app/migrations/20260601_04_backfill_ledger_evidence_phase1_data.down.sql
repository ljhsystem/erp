-- 실행 금지(검토 리허설용): 1차 증빙 데이터 롤백 SQL(배치 식별자 기준)
START TRANSACTION;

-- 반드시 실제 롤백 대상 배치 식별자를 지정한다.
-- 예시: SET @rollback_batch_id := 'PHASE1_20260601_101500';
SET @rollback_batch_id := '';

-- 배치 대상 tax_invoice_id 목록(품목 선삭제용)
DROP TEMPORARY TABLE IF EXISTS `tmp_phase1_tax_invoice_ids`;
CREATE TEMPORARY TABLE `tmp_phase1_tax_invoice_ids` (
    `id` VARCHAR(36) NOT NULL PRIMARY KEY
) ENGINE=InnoDB;

INSERT INTO `tmp_phase1_tax_invoice_ids` (`id`)
SELECT h.`issued_row_id`
FROM `ledger_evidence_number_histories` h
WHERE h.`created_by` = 'SYSTEM:MIGRATION'
  AND h.`note` = CONCAT('phase1 backfill:', @rollback_batch_id)
  AND h.`issued_table` = 'ledger_evidence_tax_invoice';

DELETE FROM `ledger_evidence_tax_invoice_items`
WHERE `tax_invoice_id` IN (SELECT `id` FROM `tmp_phase1_tax_invoice_ids`);

DELETE p
FROM `ledger_evidence_card_purchase` p
INNER JOIN `ledger_evidence_number_histories` h
    ON h.`issued_table` = 'ledger_evidence_card_purchase'
   AND h.`issued_row_id` = p.`id`
WHERE h.`created_by` = 'SYSTEM:MIGRATION'
  AND h.`note` = CONCAT('phase1 backfill:', @rollback_batch_id);

DELETE c
FROM `ledger_evidence_cash_receipt` c
INNER JOIN `ledger_evidence_number_histories` h
    ON h.`issued_table` = 'ledger_evidence_cash_receipt'
   AND h.`issued_row_id` = c.`id`
WHERE h.`created_by` = 'SYSTEM:MIGRATION'
  AND h.`note` = CONCAT('phase1 backfill:', @rollback_batch_id);

DELETE t
FROM `ledger_evidence_tax_invoice` t
INNER JOIN `ledger_evidence_number_histories` h
    ON h.`issued_table` = 'ledger_evidence_tax_invoice'
   AND h.`issued_row_id` = t.`id`
WHERE h.`created_by` = 'SYSTEM:MIGRATION'
  AND h.`note` = CONCAT('phase1 backfill:', @rollback_batch_id);

DELETE b
FROM `ledger_evidence_bank` b
INNER JOIN `ledger_evidence_number_histories` h
    ON h.`issued_table` = 'ledger_evidence_bank'
   AND h.`issued_row_id` = b.`id`
WHERE h.`created_by` = 'SYSTEM:MIGRATION'
  AND h.`note` = CONCAT('phase1 backfill:', @rollback_batch_id);

DELETE FROM `ledger_evidence_number_histories`
WHERE `created_by` = 'SYSTEM:MIGRATION'
  AND `note` = CONCAT('phase1 backfill:', @rollback_batch_id);

-- 시퀀스 값은 전체 재계산으로 안전 복원
UPDATE `ledger_evidence_number_sequences`
SET `last_evidence_sort_no` = COALESCE((
        SELECT MAX(x.`evidence_sort_no`)
        FROM (
            SELECT `evidence_sort_no` FROM `ledger_evidence_bank`
            UNION ALL
            SELECT `evidence_sort_no` FROM `ledger_evidence_tax_invoice`
            UNION ALL
            SELECT `evidence_sort_no` FROM `ledger_evidence_cash_receipt`
            UNION ALL
            SELECT `evidence_sort_no` FROM `ledger_evidence_card_purchase`
        ) x
    ), 0),
    `updated_at` = NOW(),
    `updated_by` = 'SYSTEM:ROLLBACK'
WHERE `scope_code` = 'EVIDENCE_GLOBAL';

DROP TEMPORARY TABLE IF EXISTS `tmp_phase1_tax_invoice_ids`;

COMMIT;
