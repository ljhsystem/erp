SET NAMES utf8mb4;

DELIMITER $$
CREATE PROCEDURE migrate_20260827_07_daily_income_closure_registry()
BEGIN
    IF NOT EXISTS (SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='institution_daily_employment_income_commands') THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='20260827_04 Command Migration을 먼저 적용해야 합니다.';
    END IF;
    IF EXISTS (SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN ('institution_daily_employment_income_closures','institution_daily_employment_income_accounting_links')) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='일용근로소득 Closure Registry 테이블이 이미 존재합니다.';
    END IF;
    IF NOT EXISTS (SELECT 1 FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME='institution_daily_employment_incomes' AND CONSTRAINT_NAME='ck_daily_income_status' AND CONSTRAINT_TYPE='CHECK') THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='일용근로소득 문서상태 CHECK를 찾을 수 없습니다.';
    END IF;

    CREATE TABLE institution_daily_employment_income_closures (
        id varchar(36) NOT NULL,
        daily_employment_income_id varchar(36) NOT NULL,
        approval_request_id varchar(36) NOT NULL,
        status varchar(20) NOT NULL DEFAULT 'PENDING',
        attempt_count int unsigned NOT NULL DEFAULT 0,
        processing_token varchar(64) NULL,
        started_at datetime NULL,
        completed_at datetime NULL,
        failed_at datetime NULL,
        last_error_code varchar(100) NULL,
        last_error_message varchar(500) NULL,
        processed_by varchar(100) NOT NULL COMMENT 'ActorHelper Actor Token',
        created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY uk_daily_income_closure_approval (daily_employment_income_id,approval_request_id),
        KEY idx_daily_income_closure_status (status,updated_at),
        CONSTRAINT fk_daily_income_closure_document FOREIGN KEY (daily_employment_income_id) REFERENCES institution_daily_employment_incomes(id) ON DELETE RESTRICT ON UPDATE CASCADE,
        CONSTRAINT fk_daily_income_closure_approval FOREIGN KEY (approval_request_id) REFERENCES user_approval_requests(id) ON DELETE RESTRICT ON UPDATE CASCADE,
        CONSTRAINT chk_daily_income_closure_status CHECK (status IN ('PENDING','PROCESSING','COMPLETED','FAILED')),
        CONSTRAINT chk_daily_income_closure_attempt CHECK (attempt_count >= 0),
        CONSTRAINT chk_daily_income_closure_processing CHECK ((status='PROCESSING' AND processing_token IS NOT NULL AND started_at IS NOT NULL) OR status<>'PROCESSING')
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='일용근로소득 최종승인 후속처리 실행 원장';

    CREATE TABLE institution_daily_employment_income_accounting_links (
        id varchar(36) NOT NULL,
        closure_id varchar(36) NOT NULL,
        daily_employment_income_id varchar(36) NOT NULL,
        daily_employment_income_item_id varchar(36) NOT NULL,
        generation_role varchar(40) NOT NULL,
        generation_status varchar(20) NOT NULL DEFAULT 'PENDING',
        evidence_id varchar(36) NULL,
        transaction_id varchar(36) NULL,
        result_hash char(64) NULL,
        error_code varchar(100) NULL,
        processed_by varchar(100) NOT NULL COMMENT 'ActorHelper Actor Token',
        started_at datetime NULL,
        completed_at datetime NULL,
        created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY uk_daily_income_accounting_role (daily_employment_income_id,daily_employment_income_item_id,generation_role),
        KEY idx_daily_income_accounting_closure (closure_id,generation_status),
        KEY idx_daily_income_accounting_evidence (evidence_id),
        KEY idx_daily_income_accounting_transaction (transaction_id),
        CONSTRAINT fk_daily_income_accounting_closure FOREIGN KEY (closure_id) REFERENCES institution_daily_employment_income_closures(id) ON DELETE RESTRICT ON UPDATE CASCADE,
        CONSTRAINT fk_daily_income_accounting_document FOREIGN KEY (daily_employment_income_id) REFERENCES institution_daily_employment_incomes(id) ON DELETE RESTRICT ON UPDATE CASCADE,
        CONSTRAINT fk_daily_income_accounting_item FOREIGN KEY (daily_employment_income_item_id) REFERENCES institution_daily_employment_income_items(id) ON DELETE RESTRICT ON UPDATE CASCADE,
        CONSTRAINT fk_daily_income_accounting_evidence FOREIGN KEY (evidence_id) REFERENCES ledger_evidence_daily_employment_income(id) ON DELETE RESTRICT ON UPDATE CASCADE,
        CONSTRAINT fk_daily_income_accounting_transaction FOREIGN KEY (transaction_id) REFERENCES ledger_transactions(id) ON DELETE RESTRICT ON UPDATE CASCADE,
        CONSTRAINT chk_daily_income_accounting_role CHECK (generation_role IN ('DAILY_INCOME_EVIDENCE','WORKER_PAYMENT')),
        CONSTRAINT chk_daily_income_accounting_status CHECK (generation_status IN ('PENDING','PROCESSING','COMPLETED','FAILED')),
        CONSTRAINT chk_daily_income_accounting_hash CHECK (result_hash IS NULL OR result_hash REGEXP '^[0-9a-f]{64}$'),
        CONSTRAINT chk_daily_income_accounting_role_fields CHECK ((generation_role='DAILY_INCOME_EVIDENCE' AND transaction_id IS NULL) OR generation_role='WORKER_PAYMENT')
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='일용근로소득 현장 Item과 작업자별 Evidence·지급거래 생성 Registry';

    ALTER TABLE institution_daily_employment_incomes DROP CONSTRAINT ck_daily_income_status;
    ALTER TABLE institution_daily_employment_incomes ADD CONSTRAINT ck_daily_income_status CHECK (status_code IN ('DRAFT','PENDING','APPROVED','REJECTED','WITHDRAWN'));
END$$
DELIMITER ;

CALL migrate_20260827_07_daily_income_closure_registry();
DROP PROCEDURE migrate_20260827_07_daily_income_closure_registry;
