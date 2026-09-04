DELIMITER $$
CREATE PROCEDURE migrate_20260831_09_up()
BEGIN
    IF (SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN (
        'institution_daily_employment_incomes','institution_daily_employment_income_groups','institution_daily_employment_income_items',
        'institution_daily_employment_income_calculation_revisions','ledger_evidence_daily_employment_income','ledger_transactions',
        'ledger_evidence_links','user_approval_templates','user_approval_template_steps','user_approval_requests','system_clients')) <> 11 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='일용근로소득 승인 Closure 선행 구조가 완전하지 않습니다.';
    END IF;
    IF EXISTS (SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN (
        'institution_daily_employment_income_closures','institution_daily_employment_income_accounting_links')) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='일용근로소득 승인 Closure가 이미 적용되어 있습니다.';
    END IF;
    IF EXISTS (SELECT 1 FROM user_approval_templates WHERE document_type='DAILY_EMPLOYMENT_INCOME') THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='일용근로소득 결재 템플릿이 이미 존재합니다.';
    END IF;
    IF EXISTS (SELECT 1 FROM ledger_evidence_daily_employment_income LIMIT 1) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='기존 일용근로소득 Evidence가 있어 확장할 수 없습니다.';
    END IF;

    CREATE TABLE institution_daily_employment_income_closures (
        id VARCHAR(36) NOT NULL,daily_employment_income_id VARCHAR(36) NOT NULL,approval_request_id VARCHAR(36) NOT NULL,
        calculation_revision_id VARCHAR(36) NOT NULL,source_hash CHAR(64) NOT NULL,status_code VARCHAR(20) NOT NULL DEFAULT 'PROCESSING',
        payload_hash CHAR(64) NOT NULL,completed_at DATETIME NULL,created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        created_by VARCHAR(100) NOT NULL COMMENT 'ActorHelper Actor Token',updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        updated_by VARCHAR(100) NOT NULL COMMENT 'ActorHelper Actor Token',PRIMARY KEY(id),
        UNIQUE KEY uq_daily_income_closure_approval(daily_employment_income_id,approval_request_id),
        KEY idx_daily_closure_revision(calculation_revision_id),KEY idx_daily_closure_status(status_code,updated_at),
        CONSTRAINT fk_daily_closure_document FOREIGN KEY(daily_employment_income_id) REFERENCES institution_daily_employment_incomes(id) ON DELETE RESTRICT ON UPDATE CASCADE,
        CONSTRAINT fk_daily_closure_approval FOREIGN KEY(approval_request_id) REFERENCES user_approval_requests(id) ON DELETE RESTRICT ON UPDATE CASCADE,
        CONSTRAINT fk_daily_closure_revision FOREIGN KEY(calculation_revision_id) REFERENCES institution_daily_employment_income_calculation_revisions(id) ON DELETE RESTRICT ON UPDATE CASCADE,
        CONSTRAINT ck_daily_closure_status CHECK(status_code IN('PROCESSING','COMPLETED')),
        CONSTRAINT ck_daily_closure_source_hash CHECK(source_hash REGEXP '^[0-9a-f]{64}$'),
        CONSTRAINT ck_daily_closure_payload_hash CHECK(payload_hash REGEXP '^[0-9a-f]{64}$')
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='일용근로소득 최종승인 후속처리 멱등 원장';

    CREATE TABLE institution_daily_employment_income_accounting_links (
        id VARCHAR(36) NOT NULL,closure_id VARCHAR(36) NOT NULL,daily_employment_income_id VARCHAR(36) NOT NULL,
        daily_employment_income_group_id VARCHAR(36) NOT NULL,daily_employment_income_item_id VARCHAR(36) NOT NULL,
        worker_client_id VARCHAR(36) NOT NULL,artifact_role VARCHAR(30) NOT NULL,business_key_hash CHAR(64) NOT NULL,
        payload_hash CHAR(64) NOT NULL,evidence_id VARCHAR(36) NOT NULL,transaction_id VARCHAR(36) NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,created_by VARCHAR(100) NOT NULL COMMENT 'ActorHelper Actor Token',PRIMARY KEY(id),
        UNIQUE KEY uq_daily_accounting_business_key(business_key_hash),KEY idx_daily_accounting_group_worker(daily_employment_income_group_id,worker_client_id),
        KEY idx_daily_accounting_evidence(evidence_id),KEY idx_daily_accounting_transaction(transaction_id),
        CONSTRAINT fk_daily_accounting_closure FOREIGN KEY(closure_id) REFERENCES institution_daily_employment_income_closures(id) ON DELETE RESTRICT ON UPDATE CASCADE,
        CONSTRAINT fk_daily_accounting_document FOREIGN KEY(daily_employment_income_id) REFERENCES institution_daily_employment_incomes(id) ON DELETE RESTRICT ON UPDATE CASCADE,
        CONSTRAINT fk_daily_accounting_group FOREIGN KEY(daily_employment_income_group_id) REFERENCES institution_daily_employment_income_groups(id) ON DELETE RESTRICT ON UPDATE CASCADE,
        CONSTRAINT fk_daily_accounting_item FOREIGN KEY(daily_employment_income_item_id) REFERENCES institution_daily_employment_income_items(id) ON DELETE RESTRICT ON UPDATE CASCADE,
        CONSTRAINT fk_daily_accounting_worker FOREIGN KEY(worker_client_id) REFERENCES system_clients(id) ON DELETE RESTRICT ON UPDATE CASCADE,
        CONSTRAINT fk_daily_accounting_evidence FOREIGN KEY(evidence_id) REFERENCES ledger_evidence_daily_employment_income(id) ON DELETE RESTRICT ON UPDATE CASCADE,
        CONSTRAINT fk_daily_accounting_transaction FOREIGN KEY(transaction_id) REFERENCES ledger_transactions(id) ON DELETE RESTRICT ON UPDATE CASCADE,
        CONSTRAINT ck_daily_accounting_role CHECK(artifact_role IN('EVIDENCE','WORKER_PAYMENT')),
        CONSTRAINT ck_daily_accounting_business_hash CHECK(business_key_hash REGEXP '^[0-9a-f]{64}$'),
        CONSTRAINT ck_daily_accounting_payload_hash CHECK(payload_hash REGEXP '^[0-9a-f]{64}$')
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='일용근로소득 Group×근로자 Evidence·지급거래 연결';

    ALTER TABLE ledger_evidence_daily_employment_income
        MODIFY COLUMN work_team_id VARCHAR(36) NULL COMMENT '작업팀',
        ADD COLUMN daily_employment_income_group_id VARCHAR(36) NOT NULL COMMENT '일용근로소득 근무그룹' AFTER daily_employment_income_item_id,
        ADD COLUMN calculation_revision_id VARCHAR(36) NOT NULL COMMENT '공식 계산 Revision' AFTER approval_request_id,
        ADD COLUMN source_hash CHAR(64) NOT NULL COMMENT '공식 계산 Source Hash' AFTER calculation_revision_id,
        ADD COLUMN snapshot_json LONGTEXT NOT NULL COMMENT '승인 불변 Snapshot' AFTER total_employer_burden_amount,
        ADD COLUMN business_key_hash CHAR(64) NOT NULL COMMENT 'Group×근로자 멱등키 Hash' AFTER snapshot_json,
        ADD UNIQUE KEY uq_daily_income_evidence_business_key(business_key_hash),ADD KEY idx_daily_evidence_group(daily_employment_income_group_id),
        ADD KEY idx_daily_evidence_revision(calculation_revision_id),
        ADD CONSTRAINT fk_daily_evidence_group FOREIGN KEY(daily_employment_income_group_id) REFERENCES institution_daily_employment_income_groups(id) ON DELETE RESTRICT ON UPDATE CASCADE,
        ADD CONSTRAINT fk_daily_evidence_revision FOREIGN KEY(calculation_revision_id) REFERENCES institution_daily_employment_income_calculation_revisions(id) ON DELETE RESTRICT ON UPDATE CASCADE,
        ADD CONSTRAINT ck_daily_evidence_source_hash CHECK(source_hash REGEXP '^[0-9a-f]{64}$'),
        ADD CONSTRAINT ck_daily_evidence_snapshot CHECK(JSON_VALID(snapshot_json)),
        ADD CONSTRAINT ck_daily_evidence_business_hash CHECK(business_key_hash REGEXP '^[0-9a-f]{64}$');

    ALTER TABLE institution_daily_employment_incomes DROP CONSTRAINT ck_daily_income_status;
    ALTER TABLE institution_daily_employment_incomes ADD CONSTRAINT ck_daily_income_status CHECK(status_code IN('DRAFT','PENDING','APPROVED','REJECTED','WITHDRAWN'));
    INSERT INTO user_approval_templates(id,sort_no,template_key,template_name,document_type,description,is_active,created_by,updated_by)
    SELECT '20260831-0900-4000-8000-000000000001',COALESCE(MAX(sort_no),0)+1,'daily_employment_income','일용근로소득 결재',
        'DAILY_EMPLOYMENT_INCOME','Group×근로자 일용근로소득 결재',1,'SYSTEM:DAILY_EMPLOYMENT_INCOME_CLOSURE','SYSTEM:DAILY_EMPLOYMENT_INCOME_CLOSURE' FROM user_approval_templates;
    INSERT INTO user_approval_template_steps(id,sort_no,template_id,step_name,step_type,role_id,approver_id,is_active,created_by,updated_by)
    VALUES('20260831-0900-4000-8000-000000000002',1,'20260831-0900-4000-8000-000000000001','발의','SUBMIT',NULL,NULL,1,
        'SYSTEM:DAILY_EMPLOYMENT_INCOME_CLOSURE','SYSTEM:DAILY_EMPLOYMENT_INCOME_CLOSURE');
    INSERT INTO user_approval_template_steps(id,sort_no,template_id,step_name,step_type,role_id,approver_id,is_active,created_by,updated_by)
    SELECT '20260831-0900-4000-8000-000000000003',2,'20260831-0900-4000-8000-000000000001',source_step.step_name,source_step.step_type,
        source_step.role_id,source_step.approver_id,1,'SYSTEM:DAILY_EMPLOYMENT_INCOME_CLOSURE','SYSTEM:DAILY_EMPLOYMENT_INCOME_CLOSURE'
    FROM user_approval_template_steps source_step JOIN user_approval_templates source_template ON source_template.id=source_step.template_id
    WHERE source_template.document_type='REGULAR_EMPLOYMENT_INCOME' AND source_template.is_active=1
      AND source_step.is_active=1 AND source_step.step_type='FINAL_APPROVAL';
    IF (SELECT COUNT(*) FROM user_approval_template_steps WHERE template_id='20260831-0900-4000-8000-000000000001') <> 2 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='상용근로소득 공식 최종 결재선을 한 건으로 확정할 수 없습니다.';
    END IF;
END$$
CALL migrate_20260831_09_up()$$
DROP PROCEDURE migrate_20260831_09_up$$
DELIMITER ;
