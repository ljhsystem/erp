DELIMITER $$
CREATE PROCEDURE migrate_20260827_16_up()
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.TABLES
        WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN (
            'institution_daily_employment_income_calculation_revisions',
            'institution_daily_employment_income_calculation_results',
            'institution_daily_employment_income_allocations',
            'institution_daily_employment_income_closures',
            'institution_daily_employment_income_accounting_links'
        ) GROUP BY TABLE_SCHEMA HAVING COUNT(*)=5
    ) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='_07 Closure와 _15 기관계산 Migration이 먼저 필요합니다.';
    END IF;
    IF EXISTS (
        SELECT 1 FROM information_schema.TABLES
        WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN (
            'institution_daily_employment_income_reconciliations',
            'institution_daily_employment_income_reconciliation_checks'
        )
    ) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Reconciliation 테이블 일부가 이미 존재합니다.';
    END IF;
    IF EXISTS (SELECT 1 FROM institution_daily_employment_income_accounting_links LIMIT 1) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='기존 Closure Link 자료가 있어 Grain 전환 백필이 필요합니다.';
    END IF;

    CREATE TABLE institution_daily_employment_income_reconciliations (
        id VARCHAR(36) NOT NULL,
        daily_employment_income_id VARCHAR(36) NOT NULL,
        calculation_revision_id VARCHAR(36) NOT NULL,
        source_hash CHAR(64) NOT NULL,
        status_code VARCHAR(20) NOT NULL,
        blocking_issue_count INT UNSIGNED NOT NULL DEFAULT 0,
        checked_by VARCHAR(100) NOT NULL,
        checked_at DATETIME NOT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        created_by VARCHAR(100) NOT NULL,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        updated_by VARCHAR(100) NOT NULL,
        PRIMARY KEY (id),
        UNIQUE KEY uq_daily_reconciliation_revision (calculation_revision_id),
        KEY idx_daily_reconciliation_header (daily_employment_income_id,status_code,checked_at),
        CONSTRAINT fk_daily_reconciliation_header FOREIGN KEY (daily_employment_income_id)
            REFERENCES institution_daily_employment_incomes(id) ON DELETE RESTRICT ON UPDATE CASCADE,
        CONSTRAINT fk_daily_reconciliation_revision FOREIGN KEY (calculation_revision_id)
            REFERENCES institution_daily_employment_income_calculation_revisions(id)
            ON DELETE RESTRICT ON UPDATE CASCADE,
        CONSTRAINT ck_daily_reconciliation_hash CHECK (source_hash REGEXP '^[0-9a-f]{64}$'),
        CONSTRAINT ck_daily_reconciliation_status CHECK (status_code IN ('PASSED','FAILED','STALE')),
        CONSTRAINT ck_daily_reconciliation_count CHECK (
            (status_code='PASSED' AND blocking_issue_count=0)
            OR (status_code='FAILED' AND blocking_issue_count>0)
            OR status_code='STALE'
        )
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
      COMMENT='일용근로소득 기관계산 대사 Header';

    CREATE TABLE institution_daily_employment_income_reconciliation_checks (
        id VARCHAR(36) NOT NULL,
        reconciliation_id VARCHAR(36) NOT NULL,
        check_type_code VARCHAR(50) NOT NULL,
        result_type_code VARCHAR(40) NULL,
        worker_client_id VARCHAR(36) NULL,
        daily_employment_income_group_id VARCHAR(36) NULL,
        daily_employment_income_item_id VARCHAR(36) NULL,
        daily_employment_income_workday_id VARCHAR(36) NULL,
        source_amount DECIMAL(18,2) NOT NULL,
        target_amount DECIMAL(18,2) NOT NULL,
        difference_amount DECIMAL(18,2) NOT NULL,
        status_code VARCHAR(20) NOT NULL,
        blocking TINYINT(1) NOT NULL DEFAULT 1,
        detail_message VARCHAR(1000) NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        created_by VARCHAR(100) NOT NULL,
        PRIMARY KEY (id),
        KEY idx_daily_recon_check_header (reconciliation_id,status_code,check_type_code),
        KEY idx_daily_recon_check_worker (worker_client_id,result_type_code),
        KEY idx_daily_recon_check_group (daily_employment_income_group_id),
        KEY idx_daily_recon_check_item (daily_employment_income_item_id),
        KEY idx_daily_recon_check_workday (daily_employment_income_workday_id),
        CONSTRAINT fk_daily_recon_check_header FOREIGN KEY (reconciliation_id)
            REFERENCES institution_daily_employment_income_reconciliations(id)
            ON DELETE RESTRICT ON UPDATE CASCADE,
        CONSTRAINT fk_daily_recon_check_worker FOREIGN KEY (worker_client_id)
            REFERENCES system_clients(id) ON DELETE RESTRICT ON UPDATE CASCADE,
        CONSTRAINT fk_daily_recon_check_group FOREIGN KEY (daily_employment_income_group_id)
            REFERENCES institution_daily_employment_income_groups(id) ON DELETE RESTRICT ON UPDATE CASCADE,
        CONSTRAINT fk_daily_recon_check_item FOREIGN KEY (daily_employment_income_item_id)
            REFERENCES institution_daily_employment_income_items(id) ON DELETE RESTRICT ON UPDATE CASCADE,
        CONSTRAINT fk_daily_recon_check_workday FOREIGN KEY (daily_employment_income_workday_id)
            REFERENCES institution_daily_employment_income_workdays(id) ON DELETE RESTRICT ON UPDATE CASCADE,
        CONSTRAINT ck_daily_recon_check_type CHECK (check_type_code IN (
            'SOURCE_TAX_BASIS','INCOME_TAX_ALLOCATION','LOCAL_TAX_ALLOCATION',
            'EMPLOYEE_INSURANCE_ALLOCATION','EMPLOYER_INSURANCE_ALLOCATION',
            'ITEM_GROUP_TOTAL','GROUP_HEADER_TOTAL','NET_PAYMENT'
        )),
        CONSTRAINT ck_daily_recon_check_result_type CHECK (
            result_type_code IS NULL OR result_type_code IN (
                'INCOME_TAX','LOCAL_INCOME_TAX','NATIONAL_PENSION','HEALTH_INSURANCE',
                'LONG_TERM_CARE_INSURANCE','EMPLOYMENT_INSURANCE','INDUSTRIAL_ACCIDENT_INSURANCE'
            )
        ),
        CONSTRAINT ck_daily_recon_check_status CHECK (status_code IN ('PASSED','FAILED')),
        CONSTRAINT ck_daily_recon_check_block CHECK (blocking IN (0,1)),
        CONSTRAINT ck_daily_recon_check_difference CHECK (
            difference_amount=target_amount-source_amount
            AND ((ABS(difference_amount)>=1 AND status_code='FAILED')
                OR (ABS(difference_amount)<1 AND status_code='PASSED'))
        )
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
      COMMENT='일용근로소득 대사 검사항목';

    ALTER TABLE institution_daily_employment_income_items
        ADD COLUMN base_payment_amount DECIMAL(18,2) NOT NULL DEFAULT 0 AFTER total_work_days,
        ADD COLUMN taxable_adjustment_amount DECIMAL(18,2) NOT NULL DEFAULT 0 AFTER base_payment_amount,
        ADD COLUMN non_taxable_adjustment_amount DECIMAL(18,2) NOT NULL DEFAULT 0 AFTER taxable_adjustment_amount,
        ADD COLUMN adjustment_amount DECIMAL(18,2) NOT NULL DEFAULT 0 AFTER non_taxable_adjustment_amount,
        ADD COLUMN taxable_income_amount DECIMAL(18,2) NOT NULL DEFAULT 0 AFTER adjustment_amount,
        ADD COLUMN non_taxable_income_amount DECIMAL(18,2) NOT NULL DEFAULT 0 AFTER taxable_income_amount,
        ADD COLUMN gross_payment_amount DECIMAL(18,2) NOT NULL DEFAULT 0 AFTER non_taxable_income_amount,
        ADD COLUMN withholding_amount DECIMAL(18,2) NOT NULL DEFAULT 0 AFTER gross_payment_amount,
        ADD COLUMN employee_contribution_amount DECIMAL(18,2) NOT NULL DEFAULT 0 AFTER withholding_amount,
        ADD COLUMN employer_contribution_amount DECIMAL(18,2) NOT NULL DEFAULT 0 AFTER employee_contribution_amount,
        ADD COLUMN net_payment_amount DECIMAL(18,2) NOT NULL DEFAULT 0 AFTER employer_contribution_amount,
        ADD COLUMN calculation_status_code VARCHAR(20) NOT NULL DEFAULT 'DRAFT' AFTER net_payment_amount,
        ADD COLUMN calculation_policy_version VARCHAR(100) NULL AFTER calculation_status_code,
        ADD COLUMN calculation_revision_id VARCHAR(36) NULL AFTER calculation_policy_version,
        ADD KEY idx_daily_item_calculation (calculation_revision_id,calculation_status_code),
        ADD CONSTRAINT fk_daily_item_calculation_revision FOREIGN KEY (calculation_revision_id)
            REFERENCES institution_daily_employment_income_calculation_revisions(id)
            ON DELETE RESTRICT ON UPDATE CASCADE,
        ADD CONSTRAINT ck_daily_item_calculation_status CHECK (
            calculation_status_code IN ('DRAFT','CALCULATED','CONFIRMED','STALE','FAILED')
        );

    ALTER TABLE institution_daily_employment_incomes
        ADD COLUMN group_count INT UNSIGNED NOT NULL DEFAULT 0 AFTER work_team_count,
        ADD COLUMN total_taxable_income_amount DECIMAL(18,2) NOT NULL DEFAULT 0 AFTER total_work_days,
        ADD COLUMN total_non_taxable_income_amount DECIMAL(18,2) NOT NULL DEFAULT 0 AFTER total_taxable_income_amount,
        ADD COLUMN total_income_tax_amount DECIMAL(18,2) NOT NULL DEFAULT 0 AFTER total_gross_amount,
        ADD COLUMN total_local_income_tax_amount DECIMAL(18,2) NOT NULL DEFAULT 0 AFTER total_income_tax_amount,
        ADD COLUMN total_employee_contribution_amount DECIMAL(18,2) NOT NULL DEFAULT 0 AFTER total_local_income_tax_amount,
        ADD COLUMN calculation_status_code VARCHAR(20) NOT NULL DEFAULT 'DRAFT' AFTER total_employer_burden_amount,
        ADD COLUMN calculation_policy_version VARCHAR(100) NULL AFTER calculation_status_code,
        ADD COLUMN calculation_revision_id VARCHAR(36) NULL AFTER calculation_policy_version,
        ADD KEY idx_daily_header_calculation (calculation_revision_id,calculation_status_code),
        ADD CONSTRAINT fk_daily_header_calculation_revision FOREIGN KEY (calculation_revision_id)
            REFERENCES institution_daily_employment_income_calculation_revisions(id)
            ON DELETE RESTRICT ON UPDATE CASCADE,
        ADD CONSTRAINT ck_daily_header_calculation_status CHECK (
            calculation_status_code IN ('DRAFT','CALCULATED','CONFIRMED','STALE','FAILED')
        );

    ALTER TABLE institution_daily_employment_income_closures
        ADD COLUMN calculation_revision_id VARCHAR(36) NULL AFTER approval_request_id,
        ADD COLUMN reconciliation_id VARCHAR(36) NULL AFTER calculation_revision_id,
        ADD KEY idx_daily_closure_calculation (calculation_revision_id,reconciliation_id),
        ADD CONSTRAINT fk_daily_closure_calculation FOREIGN KEY (calculation_revision_id)
            REFERENCES institution_daily_employment_income_calculation_revisions(id)
            ON DELETE RESTRICT ON UPDATE CASCADE,
        ADD CONSTRAINT fk_daily_closure_reconciliation FOREIGN KEY (reconciliation_id)
            REFERENCES institution_daily_employment_income_reconciliations(id)
            ON DELETE RESTRICT ON UPDATE CASCADE;

    ALTER TABLE institution_daily_employment_income_accounting_links
        DROP CONSTRAINT chk_daily_income_accounting_role_fields,
        DROP CONSTRAINT chk_daily_income_accounting_role,
        DROP CONSTRAINT fk_daily_income_accounting_item;

    ALTER TABLE institution_daily_employment_income_accounting_links
        DROP INDEX uk_daily_income_accounting_role,
        MODIFY COLUMN daily_employment_income_item_id VARCHAR(36) NULL,
        ADD COLUMN business_key_hash CHAR(64) NOT NULL AFTER generation_role,
        ADD COLUMN calculation_revision_id VARCHAR(36) NOT NULL AFTER business_key_hash,
        ADD COLUMN calculation_result_id VARCHAR(36) NULL AFTER calculation_revision_id,
        ADD COLUMN worker_client_id VARCHAR(36) NULL AFTER calculation_result_id,
        ADD COLUMN voucher_id VARCHAR(36) NULL AFTER transaction_id,
        ADD UNIQUE KEY uq_daily_income_accounting_business (closure_id,generation_role,business_key_hash),
        ADD KEY idx_daily_income_accounting_document (daily_employment_income_id),
        ADD KEY idx_daily_income_accounting_calculation (calculation_revision_id,calculation_result_id),
        ADD KEY idx_daily_income_accounting_worker (worker_client_id),
        ADD KEY idx_daily_income_accounting_voucher (voucher_id),
        ADD CONSTRAINT fk_daily_income_accounting_item FOREIGN KEY (daily_employment_income_item_id)
            REFERENCES institution_daily_employment_income_items(id) ON DELETE RESTRICT ON UPDATE CASCADE,
        ADD CONSTRAINT fk_daily_income_accounting_calculation FOREIGN KEY (calculation_revision_id)
            REFERENCES institution_daily_employment_income_calculation_revisions(id)
            ON DELETE RESTRICT ON UPDATE CASCADE,
        ADD CONSTRAINT fk_daily_income_accounting_result FOREIGN KEY (calculation_result_id)
            REFERENCES institution_daily_employment_income_calculation_results(id)
            ON DELETE RESTRICT ON UPDATE CASCADE,
        ADD CONSTRAINT fk_daily_income_accounting_worker FOREIGN KEY (worker_client_id)
            REFERENCES system_clients(id) ON DELETE RESTRICT ON UPDATE CASCADE,
        ADD CONSTRAINT chk_daily_income_accounting_role CHECK (
            generation_role IN ('INSTITUTION_EVIDENCE','WORKER_PAYMENT','ACCOUNTING_VOUCHER')
        ),
        ADD CONSTRAINT chk_daily_income_accounting_business_hash CHECK (
            business_key_hash REGEXP '^[0-9a-f]{64}$'
        );
END$$
CALL migrate_20260827_16_up()$$
DROP PROCEDURE migrate_20260827_16_up$$
DELIMITER ;
