DELIMITER $$
CREATE PROCEDURE migrate_20260827_23_up()
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.TABLES
        WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='institution_daily_employment_income_commands'
    ) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='일용근로소득 Command 선행 구조가 필요합니다.';
    END IF;
    IF EXISTS (
        SELECT 1 FROM information_schema.TABLES
        WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN (
            'institution_daily_employment_income_closures',
            'institution_daily_employment_income_accounting_links',
            'institution_daily_employment_income_non_taxable_revisions'
        )
    ) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='호환 Baseline 대상 테이블 일부 또는 전체가 이미 존재합니다.';
    END IF;
    IF EXISTS (SELECT 1 FROM institution_daily_employment_income_lines LIMIT 1) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='기존 Line 자료가 있어 물리 Scope Key 백필계획이 필요합니다.';
    END IF;

    CREATE TABLE institution_daily_employment_income_closures (
        id VARCHAR(36) NOT NULL,
        daily_employment_income_id VARCHAR(36) NOT NULL,
        approval_request_id VARCHAR(36) NOT NULL,
        status VARCHAR(20) NOT NULL DEFAULT 'PENDING',
        attempt_count INT UNSIGNED NOT NULL DEFAULT 0,
        processing_token VARCHAR(64) NULL,
        started_at DATETIME NULL,
        completed_at DATETIME NULL,
        failed_at DATETIME NULL,
        last_error_code VARCHAR(100) NULL,
        last_error_message VARCHAR(500) NULL,
        processed_by VARCHAR(100) NOT NULL COMMENT 'ActorHelper Actor Token',
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY uk_daily_income_closure_approval (daily_employment_income_id,approval_request_id),
        KEY idx_daily_income_closure_status (status,updated_at),
        CONSTRAINT fk_daily_income_closure_document FOREIGN KEY (daily_employment_income_id)
            REFERENCES institution_daily_employment_incomes(id) ON DELETE RESTRICT ON UPDATE CASCADE,
        CONSTRAINT fk_daily_income_closure_approval FOREIGN KEY (approval_request_id)
            REFERENCES user_approval_requests(id) ON DELETE RESTRICT ON UPDATE CASCADE,
        CONSTRAINT chk_daily_income_closure_status CHECK (status IN ('PENDING','PROCESSING','COMPLETED','FAILED')),
        CONSTRAINT chk_daily_income_closure_attempt CHECK (attempt_count>=0),
        CONSTRAINT chk_daily_income_closure_processing CHECK (
            (status='PROCESSING' AND processing_token IS NOT NULL AND started_at IS NOT NULL)
            OR status<>'PROCESSING'
        )
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
      COMMENT='일용근로소득 최종승인 후속처리 실행 원장';

    CREATE TABLE institution_daily_employment_income_accounting_links (
        id VARCHAR(36) NOT NULL,
        closure_id VARCHAR(36) NOT NULL,
        daily_employment_income_id VARCHAR(36) NOT NULL,
        daily_employment_income_item_id VARCHAR(36) NOT NULL,
        generation_role VARCHAR(40) NOT NULL,
        generation_status VARCHAR(20) NOT NULL DEFAULT 'PENDING',
        evidence_id VARCHAR(36) NULL,
        transaction_id VARCHAR(36) NULL,
        result_hash CHAR(64) NULL,
        error_code VARCHAR(100) NULL,
        processed_by VARCHAR(100) NOT NULL COMMENT 'ActorHelper Actor Token',
        started_at DATETIME NULL,
        completed_at DATETIME NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY uk_daily_income_accounting_role (daily_employment_income_id,daily_employment_income_item_id,generation_role),
        KEY idx_daily_income_accounting_closure (closure_id,generation_status),
        KEY idx_daily_income_accounting_evidence (evidence_id),
        KEY idx_daily_income_accounting_transaction (transaction_id),
        CONSTRAINT fk_daily_income_accounting_closure FOREIGN KEY (closure_id)
            REFERENCES institution_daily_employment_income_closures(id) ON DELETE RESTRICT ON UPDATE CASCADE,
        CONSTRAINT fk_daily_income_accounting_document FOREIGN KEY (daily_employment_income_id)
            REFERENCES institution_daily_employment_incomes(id) ON DELETE RESTRICT ON UPDATE CASCADE,
        CONSTRAINT fk_daily_income_accounting_item FOREIGN KEY (daily_employment_income_item_id)
            REFERENCES institution_daily_employment_income_items(id) ON DELETE RESTRICT ON UPDATE CASCADE,
        CONSTRAINT fk_daily_income_accounting_evidence FOREIGN KEY (evidence_id)
            REFERENCES ledger_evidence_daily_employment_income(id) ON DELETE RESTRICT ON UPDATE CASCADE,
        CONSTRAINT fk_daily_income_accounting_transaction FOREIGN KEY (transaction_id)
            REFERENCES ledger_transactions(id) ON DELETE RESTRICT ON UPDATE CASCADE,
        CONSTRAINT chk_daily_income_accounting_role CHECK (generation_role IN ('DAILY_INCOME_EVIDENCE','WORKER_PAYMENT')),
        CONSTRAINT chk_daily_income_accounting_status CHECK (generation_status IN ('PENDING','PROCESSING','COMPLETED','FAILED')),
        CONSTRAINT chk_daily_income_accounting_hash CHECK (result_hash IS NULL OR result_hash REGEXP '^[0-9a-f]{64}$'),
        CONSTRAINT chk_daily_income_accounting_role_fields CHECK (1=1)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
      COMMENT='일용근로소득 기관결과·지급·전표 생성 Registry';

    CREATE TABLE institution_daily_employment_income_non_taxable_revisions (
        id VARCHAR(36) NOT NULL,
        daily_employment_income_id VARCHAR(36) NOT NULL,
        daily_employment_income_item_id VARCHAR(36) NOT NULL,
        daily_employment_income_workday_id VARCHAR(36) NULL,
        revision_no INT UNSIGNED NOT NULL,
        non_taxable_item_code VARCHAR(50) NOT NULL,
        applied_amount DECIMAL(18,2) NOT NULL,
        effective_from DATE NULL,
        effective_to DATE NULL,
        application_reason VARCHAR(1000) NOT NULL,
        legal_basis TEXT NOT NULL,
        calculation_details TEXT NOT NULL,
        statutory_standard_id CHAR(36) NOT NULL,
        revision_status_code VARCHAR(20) NOT NULL DEFAULT 'DRAFT',
        confirmed_by VARCHAR(100) NULL,
        confirmed_at DATETIME NULL,
        corrects_revision_id VARCHAR(36) NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        created_by VARCHAR(100) NOT NULL,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        updated_by VARCHAR(100) NOT NULL,
        PRIMARY KEY (id),
        UNIQUE KEY uq_daily_non_tax_revision_no (daily_employment_income_item_id,revision_no),
        KEY idx_daily_non_tax_revision_header (daily_employment_income_id,revision_status_code),
        KEY idx_daily_non_tax_revision_period (daily_employment_income_item_id,effective_from,effective_to),
        KEY idx_daily_non_tax_revision_correction (corrects_revision_id),
        CONSTRAINT fk_daily_non_tax_revision_header FOREIGN KEY (daily_employment_income_id)
            REFERENCES institution_daily_employment_incomes(id) ON DELETE RESTRICT ON UPDATE CASCADE,
        CONSTRAINT fk_daily_non_tax_revision_item FOREIGN KEY (daily_employment_income_item_id)
            REFERENCES institution_daily_employment_income_items(id) ON DELETE RESTRICT ON UPDATE CASCADE,
        CONSTRAINT fk_daily_non_tax_revision_workday FOREIGN KEY (daily_employment_income_workday_id)
            REFERENCES institution_daily_employment_income_workdays(id) ON DELETE RESTRICT ON UPDATE CASCADE,
        CONSTRAINT fk_daily_non_tax_revision_standard FOREIGN KEY (statutory_standard_id)
            REFERENCES system_statutory_standards(id) ON DELETE RESTRICT ON UPDATE CASCADE,
        CONSTRAINT fk_daily_non_tax_revision_correction FOREIGN KEY (corrects_revision_id)
            REFERENCES institution_daily_employment_income_non_taxable_revisions(id) ON DELETE RESTRICT ON UPDATE CASCADE,
        CONSTRAINT ck_daily_non_tax_revision_amount CHECK (applied_amount>0),
        CONSTRAINT ck_daily_non_tax_revision_status CHECK
            (revision_status_code IN ('DRAFT','CONFIRMED','CORRECTED','CANCELLED'))
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
      COMMENT='일용근로소득 비과세 불변 Revision';

    ALTER TABLE institution_daily_employment_income_lines
        DROP INDEX uq_daily_income_line,
        ADD COLUMN taxability_code VARCHAR(20) NULL AFTER line_type_code,
        ADD COLUMN non_taxable_revision_id VARCHAR(36) NULL AFTER statutory_standard_id,
        ADD COLUMN effective_from DATE NULL AFTER non_taxable_revision_id,
        ADD COLUMN effective_to DATE NULL AFTER effective_from,
        ADD COLUMN workday_scope_key VARCHAR(36) NOT NULL AFTER effective_to,
        ADD COLUMN revision_scope_key VARCHAR(36) NOT NULL AFTER workday_scope_key,
        ADD COLUMN period_scope_key VARCHAR(32) NOT NULL AFTER revision_scope_key,
        ADD UNIQUE KEY uq_daily_income_line_scope (
            daily_employment_income_item_id,workday_scope_key,line_type_code,line_code,
            revision_scope_key,period_scope_key
        ),
        ADD KEY idx_daily_income_line_revision (non_taxable_revision_id),
        ADD CONSTRAINT fk_daily_income_line_non_tax_revision FOREIGN KEY (non_taxable_revision_id)
            REFERENCES institution_daily_employment_income_non_taxable_revisions(id)
            ON DELETE RESTRICT ON UPDATE CASCADE;
END$$
CALL migrate_20260827_23_up()$$
DROP PROCEDURE migrate_20260827_23_up$$
DELIMITER ;
