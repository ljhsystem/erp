DELIMITER $$
DROP PROCEDURE IF EXISTS enable_evidence_transaction_many_to_many$$
CREATE PROCEDURE enable_evidence_transaction_many_to_many()
BEGIN
  DECLARE v_duplicate_pairs INT DEFAULT 0;
  SELECT COUNT(*) INTO v_duplicate_pairs FROM (
    SELECT evidence_type,evidence_id,target_type,target_id,COUNT(*) c
    FROM ledger_evidence_links WHERE deleted_at IS NULL
    GROUP BY evidence_type,evidence_id,target_type,target_id HAVING c>1
  ) duplicate_pairs;
  IF v_duplicate_pairs<>0 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='활성 Evidence/Target 중복 Pair가 존재합니다.'; END IF;

  ALTER TABLE ledger_evidence_links
    DROP INDEX uk_evl_active_transaction_evidence,
    DROP INDEX uk_evl_unique_link,
    DROP COLUMN active_transaction_evidence_type,
    DROP COLUMN active_transaction_evidence_id,
    ADD COLUMN active_pair_evidence_type varchar(40) GENERATED ALWAYS AS (CASE WHEN deleted_at IS NULL THEN evidence_type ELSE NULL END) STORED COMMENT '활성 Pair 증빙유형',
    ADD COLUMN active_pair_evidence_id varchar(36) GENERATED ALWAYS AS (CASE WHEN deleted_at IS NULL THEN evidence_id ELSE NULL END) STORED COMMENT '활성 Pair 증빙ID',
    ADD COLUMN active_pair_target_type varchar(20) GENERATED ALWAYS AS (CASE WHEN deleted_at IS NULL THEN target_type ELSE NULL END) STORED COMMENT '활성 Pair 대상유형',
    ADD COLUMN active_pair_target_id varchar(36) GENERATED ALWAYS AS (CASE WHEN deleted_at IS NULL THEN target_id ELSE NULL END) STORED COMMENT '활성 Pair 대상ID',
    ADD UNIQUE KEY uk_evl_active_evidence_target_pair(active_pair_evidence_type,active_pair_evidence_id,active_pair_target_type,active_pair_target_id),
    ADD KEY idx_evl_active_target_evidence(active_pair_target_type,active_pair_target_id,active_pair_evidence_type,active_pair_evidence_id);

  ALTER TABLE ledger_evidence_metadata
    ADD COLUMN transaction_cardinality varchar(30) NOT NULL DEFAULT 'SINGLE_TRANSACTION' COMMENT 'Evidence의 활성 거래 연결 업무정책' AFTER process_role,
    ADD CONSTRAINT chk_lem_transaction_cardinality CHECK(transaction_cardinality IN('SINGLE_TRANSACTION','MULTI_TRANSACTION'));
  UPDATE ledger_evidence_metadata SET transaction_cardinality='MULTI_TRANSACTION',updated_at=NOW(),updated_by='SYSTEM:EVIDENCE_LINK_CARDINALITY'
   WHERE import_type='PAYROLL_REPORT' AND deleted_at IS NULL;
END$$
CALL enable_evidence_transaction_many_to_many()$$
DROP PROCEDURE enable_evidence_transaction_many_to_many$$
DELIMITER ;
