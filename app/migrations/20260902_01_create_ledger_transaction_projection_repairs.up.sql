DELIMITER $$
CREATE PROCEDURE migrate_transaction_projection_repairs()
procedure_body: BEGIN
    DECLARE table_count INT DEFAULT 0;
    DECLARE column_count INT DEFAULT 0;
    DECLARE index_count INT DEFAULT 0;
    DECLARE check_count INT DEFAULT 0;

    SELECT COUNT(*) INTO table_count FROM information_schema.TABLES
     WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='ledger_transaction_projection_repairs';

    IF table_count = 1 THEN
        SELECT COUNT(*) INTO column_count FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='ledger_transaction_projection_repairs'
           AND COLUMN_NAME IN ('id','request_key','transaction_id','evidence_id','approval_request_id','source_revision_id','repair_type','reason_code','reason_text','source_hash','before_snapshot','after_snapshot','changed_fields_json','result_status','repaired_by','repaired_at','created_at');
        SELECT COUNT(DISTINCT INDEX_NAME) INTO index_count FROM information_schema.STATISTICS
         WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='ledger_transaction_projection_repairs'
           AND INDEX_NAME IN ('PRIMARY','uq_transaction_projection_repairs_request','idx_transaction_projection_repairs_transaction','idx_transaction_projection_repairs_evidence','idx_transaction_projection_repairs_approval','idx_transaction_projection_repairs_reason');
        SELECT COUNT(*) INTO check_count FROM information_schema.TABLE_CONSTRAINTS
         WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME='ledger_transaction_projection_repairs' AND CONSTRAINT_TYPE='CHECK';
        IF column_count=17 AND index_count=6 AND check_count=5 THEN LEAVE procedure_body; END IF;
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='ledger_transaction_projection_repairs 부분 또는 상이 구조가 존재합니다.';
    END IF;

    CREATE TABLE ledger_transaction_projection_repairs (
        id CHAR(36) NOT NULL,
        request_key VARCHAR(191) NOT NULL,
        transaction_id CHAR(36) NOT NULL,
        evidence_id CHAR(36) NULL,
        approval_request_id CHAR(36) NULL,
        source_revision_id CHAR(36) NULL,
        repair_type VARCHAR(50) NOT NULL,
        reason_code VARCHAR(100) NOT NULL,
        reason_text VARCHAR(500) NOT NULL,
        source_hash CHAR(64) NULL,
        before_snapshot LONGTEXT NOT NULL,
        after_snapshot LONGTEXT NOT NULL,
        changed_fields_json LONGTEXT NOT NULL,
        result_status VARCHAR(20) NOT NULL,
        repaired_by VARCHAR(100) NULL COMMENT 'ActorHelper Actor Token',
        repaired_at DATETIME(6) NOT NULL,
        created_at DATETIME(6) NOT NULL,
        PRIMARY KEY (id),
        UNIQUE KEY uq_transaction_projection_repairs_request (request_key),
        KEY idx_transaction_projection_repairs_transaction (transaction_id,repaired_at),
        KEY idx_transaction_projection_repairs_evidence (evidence_id),
        KEY idx_transaction_projection_repairs_approval (approval_request_id),
        KEY idx_transaction_projection_repairs_reason (reason_code,repaired_at),
        CONSTRAINT ck_transaction_projection_repairs_before CHECK (JSON_VALID(before_snapshot)),
        CONSTRAINT ck_transaction_projection_repairs_after CHECK (JSON_VALID(after_snapshot)),
        CONSTRAINT ck_transaction_projection_repairs_changed CHECK (JSON_VALID(changed_fields_json)),
        CONSTRAINT ck_transaction_projection_repairs_status CHECK (result_status='COMPLETED'),
        CONSTRAINT ck_transaction_projection_repairs_source_hash CHECK (source_hash IS NULL OR source_hash REGEXP '^[0-9a-fA-F]{64}$')
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='승인 파생 Transaction Projection 정정 불변 감사';
END$$
CALL migrate_transaction_projection_repairs()$$
DROP PROCEDURE migrate_transaction_projection_repairs$$
DELIMITER ;
