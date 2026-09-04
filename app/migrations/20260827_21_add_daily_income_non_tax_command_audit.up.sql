DELIMITER $$
CREATE PROCEDURE migrate_20260827_21_up()
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.TABLES
        WHERE TABLE_SCHEMA=DATABASE()
          AND TABLE_NAME='institution_daily_employment_income_commands'
    ) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='일용근로소득 Command 원장이 필요합니다.';
    END IF;
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.TABLES
        WHERE TABLE_SCHEMA=DATABASE()
          AND TABLE_NAME='institution_daily_employment_income_non_taxable_revisions'
    ) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='비과세 Revision Migration이 먼저 필요합니다.';
    END IF;
    IF EXISTS (
        SELECT 1 FROM information_schema.TABLES
        WHERE TABLE_SCHEMA=DATABASE()
          AND TABLE_NAME='institution_daily_employment_income_non_taxable_audits'
    ) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='비과세 Audit 원장이 이미 존재합니다.';
    END IF;

    ALTER TABLE institution_daily_employment_income_commands
        DROP CONSTRAINT chk_daily_income_command_type,
        ADD COLUMN target_revision_id VARCHAR(36) NULL AFTER daily_employment_income_id,
        ADD COLUMN result_revision_id VARCHAR(36) NULL AFTER result_reference_id,
        ADD KEY idx_daily_income_command_target_revision (target_revision_id),
        ADD KEY idx_daily_income_command_result_revision (result_revision_id),
        ADD CONSTRAINT fk_daily_income_command_target_revision FOREIGN KEY (target_revision_id)
            REFERENCES institution_daily_employment_income_non_taxable_revisions(id)
            ON DELETE RESTRICT ON UPDATE CASCADE,
        ADD CONSTRAINT fk_daily_income_command_result_revision FOREIGN KEY (result_revision_id)
            REFERENCES institution_daily_employment_income_non_taxable_revisions(id)
            ON DELETE RESTRICT ON UPDATE CASCADE,
        ADD CONSTRAINT chk_daily_income_command_type CHECK (command_type IN (
            'SAVE','UPDATE','DELETE','SUBMIT','WITHDRAW','RETRY_CLOSURE',
            'NON_TAX_CREATE','NON_TAX_CONFIRM','NON_TAX_CORRECT',
            'NON_TAX_ATTACHMENT_LINK','NON_TAX_ATTACHMENT_UNLINK'
        ));

    ALTER TABLE institution_daily_employment_income_non_taxable_revisions
        DROP CONSTRAINT ck_daily_non_tax_revision_status,
        ADD CONSTRAINT ck_daily_non_tax_revision_status CHECK (
            revision_status_code IN (
                'DRAFT','CONFIRMED','REJECTED','SUPERSEDED','CORRECTED','CANCELLED'
            )
        );

    CREATE TABLE institution_daily_employment_income_non_taxable_audits (
        id VARCHAR(36) NOT NULL,
        daily_employment_income_id VARCHAR(36) NOT NULL,
        daily_employment_income_item_id VARCHAR(36) NOT NULL,
        non_taxable_revision_id VARCHAR(36) NOT NULL,
        command_id VARCHAR(36) NOT NULL,
        event_type_code VARCHAR(30) NOT NULL,
        previous_status_code VARCHAR(20) NULL,
        new_status_code VARCHAR(20) NOT NULL,
        previous_snapshot LONGTEXT NULL,
        new_snapshot LONGTEXT NOT NULL,
        request_key VARCHAR(191) NOT NULL,
        payload_hash CHAR(64) NOT NULL,
        occurred_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        processed_by VARCHAR(100) NOT NULL COMMENT 'ActorHelper Actor Token',
        PRIMARY KEY (id),
        UNIQUE KEY uq_daily_non_tax_audit_command_event (command_id,event_type_code,non_taxable_revision_id),
        KEY idx_daily_non_tax_audit_document (daily_employment_income_id,occurred_at),
        KEY idx_daily_non_tax_audit_item (daily_employment_income_item_id,occurred_at),
        KEY idx_daily_non_tax_audit_revision (non_taxable_revision_id,occurred_at),
        KEY idx_daily_non_tax_audit_request (request_key),
        CONSTRAINT fk_daily_non_tax_audit_header FOREIGN KEY (daily_employment_income_id)
            REFERENCES institution_daily_employment_incomes(id) ON DELETE RESTRICT ON UPDATE CASCADE,
        CONSTRAINT fk_daily_non_tax_audit_item FOREIGN KEY (daily_employment_income_item_id)
            REFERENCES institution_daily_employment_income_items(id) ON DELETE RESTRICT ON UPDATE CASCADE,
        CONSTRAINT fk_daily_non_tax_audit_revision FOREIGN KEY (non_taxable_revision_id)
            REFERENCES institution_daily_employment_income_non_taxable_revisions(id) ON DELETE RESTRICT ON UPDATE CASCADE,
        CONSTRAINT fk_daily_non_tax_audit_command FOREIGN KEY (command_id)
            REFERENCES institution_daily_employment_income_commands(id) ON DELETE RESTRICT ON UPDATE CASCADE,
        CONSTRAINT chk_daily_non_tax_audit_event CHECK (event_type_code IN (
            'CREATED','CONFIRMED','REJECTED','CORRECTED','SUPERSEDED',
            'ATTACHMENT_LINKED','ATTACHMENT_UNLINKED'
        )),
        CONSTRAINT chk_daily_non_tax_audit_status CHECK (
            (event_type_code='CREATED' AND previous_status_code IS NULL)
            OR (event_type_code<>'CREATED' AND previous_status_code IS NOT NULL)
        ),
        CONSTRAINT chk_daily_non_tax_audit_previous_status CHECK (
            previous_status_code IS NULL OR previous_status_code IN (
                'DRAFT','CONFIRMED','REJECTED','SUPERSEDED','CORRECTED','CANCELLED'
            )
        ),
        CONSTRAINT chk_daily_non_tax_audit_new_status CHECK (
            new_status_code IN (
                'DRAFT','CONFIRMED','REJECTED','SUPERSEDED','CORRECTED','CANCELLED'
            )
        ),
        CONSTRAINT chk_daily_non_tax_audit_before_json CHECK (
            previous_snapshot IS NULL OR JSON_VALID(previous_snapshot)
        ),
        CONSTRAINT chk_daily_non_tax_audit_after_json CHECK (JSON_VALID(new_snapshot)),
        CONSTRAINT chk_daily_non_tax_audit_hash CHECK (payload_hash REGEXP '^[0-9a-f]{64}$')
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
      COMMENT='일용근로소득 비과세 명령 불변 Audit 원장';
END$$
CALL migrate_20260827_21_up()$$
DROP PROCEDURE migrate_20260827_21_up$$
DELIMITER ;
