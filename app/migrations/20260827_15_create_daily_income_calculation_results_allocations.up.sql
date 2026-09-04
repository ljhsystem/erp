DELIMITER $$
CREATE PROCEDURE migrate_20260827_15_up()
BEGIN
    IF EXISTS (
        SELECT 1 FROM information_schema.TABLES
        WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN (
            'institution_daily_employment_income_calculation_revisions',
            'institution_daily_employment_income_calculation_results',
            'institution_daily_employment_income_allocations'
        )
    ) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='기관계산 또는 Allocation 테이블 일부가 이미 존재합니다.';
    END IF;

    CREATE TABLE institution_daily_employment_income_calculation_revisions (
        id VARCHAR(36) NOT NULL,
        daily_employment_income_id VARCHAR(36) NOT NULL,
        revision_no INT UNSIGNED NOT NULL,
        calculation_policy_version VARCHAR(100) NOT NULL,
        source_hash CHAR(64) NOT NULL,
        status_code VARCHAR(20) NOT NULL DEFAULT 'DRAFT',
        supersedes_revision_id VARCHAR(36) NULL,
        calculated_by VARCHAR(100) NULL,
        calculated_at DATETIME NULL,
        confirmed_by VARCHAR(100) NULL,
        confirmed_at DATETIME NULL,
        failed_at DATETIME NULL,
        failure_code VARCHAR(100) NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        created_by VARCHAR(100) NOT NULL,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        updated_by VARCHAR(100) NOT NULL,
        PRIMARY KEY (id),
        UNIQUE KEY uq_daily_calc_revision_no (daily_employment_income_id,revision_no),
        UNIQUE KEY uq_daily_calc_source_hash (daily_employment_income_id,source_hash),
        UNIQUE KEY uq_daily_calc_superseded_once (supersedes_revision_id),
        KEY idx_daily_calc_status (daily_employment_income_id,status_code,revision_no),
        CONSTRAINT fk_daily_calc_header FOREIGN KEY (daily_employment_income_id)
            REFERENCES institution_daily_employment_incomes(id) ON DELETE RESTRICT ON UPDATE CASCADE,
        CONSTRAINT fk_daily_calc_supersedes FOREIGN KEY (supersedes_revision_id)
            REFERENCES institution_daily_employment_income_calculation_revisions(id)
            ON DELETE RESTRICT ON UPDATE CASCADE,
        CONSTRAINT ck_daily_calc_source_hash CHECK (source_hash REGEXP '^[0-9a-f]{64}$'),
        CONSTRAINT ck_daily_calc_status CHECK (
            status_code IN ('DRAFT','CALCULATED','CONFIRMED','STALE','FAILED','SUPERSEDED')
        ),
        CONSTRAINT ck_daily_calc_state_fields CHECK (
            (status_code='DRAFT' AND calculated_at IS NULL AND confirmed_at IS NULL AND failed_at IS NULL)
            OR (status_code='CALCULATED' AND calculated_at IS NOT NULL AND confirmed_at IS NULL AND failed_at IS NULL)
            OR (status_code='CONFIRMED' AND calculated_at IS NOT NULL AND confirmed_at IS NOT NULL AND failed_at IS NULL)
            OR (status_code IN ('STALE','SUPERSEDED') AND calculated_at IS NOT NULL AND failed_at IS NULL)
            OR (status_code='FAILED' AND failed_at IS NOT NULL)
        )
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
      COMMENT='일용근로소득 기관계산 불변 Revision';

    CREATE TABLE institution_daily_employment_income_calculation_results (
        id VARCHAR(36) NOT NULL,
        calculation_revision_id VARCHAR(36) NOT NULL,
        result_type_code VARCHAR(40) NOT NULL,
        worker_client_id VARCHAR(36) NOT NULL,
        social_insurance_workplace_id VARCHAR(36) NULL,
        work_date DATE NULL,
        application_from DATE NOT NULL,
        application_to DATE NOT NULL,
        payment_date DATE NOT NULL,
        payment_sequence INT UNSIGNED NOT NULL DEFAULT 1,
        calculation_basis_amount DECIMAL(18,2) NOT NULL DEFAULT 0,
        automatic_employee_amount DECIMAL(18,2) NOT NULL DEFAULT 0,
        automatic_employer_amount DECIMAL(18,2) NOT NULL DEFAULT 0,
        confirmed_employee_amount DECIMAL(18,2) NOT NULL DEFAULT 0,
        confirmed_employer_amount DECIMAL(18,2) NOT NULL DEFAULT 0,
        statutory_standard_id CHAR(36) NOT NULL,
        status_code VARCHAR(20) NOT NULL DEFAULT 'CALCULATED',
        exception_reason VARCHAR(1000) NULL,
        calculation_basis_snapshot LONGTEXT NOT NULL,
        workplace_scope_key VARCHAR(36) NOT NULL COMMENT '보험사업장 ID 또는 NONE',
        workday_scope_key DATE NOT NULL COMMENT '근무일 또는 1000-01-01',
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        created_by VARCHAR(100) NOT NULL,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        updated_by VARCHAR(100) NOT NULL,
        PRIMARY KEY (id),
        UNIQUE KEY uq_daily_calc_result_grain (
            calculation_revision_id,result_type_code,worker_client_id,
            workplace_scope_key,workday_scope_key,application_from,application_to,
            payment_date,payment_sequence
        ),
        KEY idx_daily_calc_result_worker (worker_client_id,result_type_code,payment_date),
        KEY idx_daily_calc_result_workplace (social_insurance_workplace_id,result_type_code,application_from,application_to),
        KEY idx_daily_calc_result_standard (statutory_standard_id),
        CONSTRAINT fk_daily_calc_result_revision FOREIGN KEY (calculation_revision_id)
            REFERENCES institution_daily_employment_income_calculation_revisions(id)
            ON DELETE RESTRICT ON UPDATE CASCADE,
        CONSTRAINT fk_daily_calc_result_worker FOREIGN KEY (worker_client_id)
            REFERENCES system_clients(id) ON DELETE RESTRICT ON UPDATE CASCADE,
        CONSTRAINT fk_daily_calc_result_workplace FOREIGN KEY (social_insurance_workplace_id)
            REFERENCES institution_social_insurance_workplaces(id) ON DELETE RESTRICT ON UPDATE CASCADE,
        CONSTRAINT fk_daily_calc_result_standard FOREIGN KEY (statutory_standard_id)
            REFERENCES system_statutory_standards(id) ON DELETE RESTRICT ON UPDATE CASCADE,
        CONSTRAINT ck_daily_calc_result_type CHECK (result_type_code IN (
            'INCOME_TAX','LOCAL_INCOME_TAX','NATIONAL_PENSION','HEALTH_INSURANCE',
            'LONG_TERM_CARE_INSURANCE','EMPLOYMENT_INSURANCE','INDUSTRIAL_ACCIDENT_INSURANCE'
        )),
        CONSTRAINT ck_daily_calc_result_status CHECK (
            status_code IN ('CALCULATED','CONFIRMED','EXCLUDED','FAILED')
        ),
        CONSTRAINT ck_daily_calc_result_period CHECK (application_from<=application_to),
        CONSTRAINT ck_daily_calc_result_sequence CHECK (payment_sequence>=1),
        CONSTRAINT ck_daily_calc_result_snapshot CHECK (JSON_VALID(calculation_basis_snapshot))
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
      COMMENT='일용근로소득 세금·보험 공식 계산결과';

    CREATE TABLE institution_daily_employment_income_allocations (
        id VARCHAR(36) NOT NULL,
        calculation_result_id VARCHAR(36) NOT NULL,
        allocation_scope_code VARCHAR(20) NOT NULL,
        daily_employment_income_group_id VARCHAR(36) NOT NULL,
        daily_employment_income_item_id VARCHAR(36) NOT NULL,
        daily_employment_income_workday_id VARCHAR(36) NULL,
        allocation_basis_amount DECIMAL(18,2) NOT NULL,
        allocation_numerator DECIMAL(24,6) NOT NULL,
        allocation_denominator DECIMAL(24,6) NOT NULL,
        allocated_employee_amount DECIMAL(18,2) NOT NULL DEFAULT 0,
        allocated_employer_amount DECIMAL(18,2) NOT NULL DEFAULT 0,
        residual_amount DECIMAL(18,2) NOT NULL DEFAULT 0,
        residual_applied TINYINT(1) NOT NULL DEFAULT 0,
        decision_rank INT UNSIGNED NOT NULL,
        allocation_policy_version VARCHAR(100) NOT NULL,
        workday_scope_key VARCHAR(36) NOT NULL COMMENT 'Workday ID 또는 NONE',
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        created_by VARCHAR(100) NOT NULL,
        PRIMARY KEY (id),
        UNIQUE KEY uq_daily_allocation_target (
            calculation_result_id,daily_employment_income_group_id,
            daily_employment_income_item_id,workday_scope_key
        ),
        UNIQUE KEY uq_daily_allocation_rank (calculation_result_id,decision_rank),
        KEY idx_daily_allocation_group (daily_employment_income_group_id,calculation_result_id),
        KEY idx_daily_allocation_item (daily_employment_income_item_id,calculation_result_id),
        KEY idx_daily_allocation_workday (daily_employment_income_workday_id,calculation_result_id),
        CONSTRAINT fk_daily_allocation_result FOREIGN KEY (calculation_result_id)
            REFERENCES institution_daily_employment_income_calculation_results(id)
            ON DELETE RESTRICT ON UPDATE CASCADE,
        CONSTRAINT fk_daily_allocation_group FOREIGN KEY (daily_employment_income_group_id)
            REFERENCES institution_daily_employment_income_groups(id) ON DELETE RESTRICT ON UPDATE CASCADE,
        CONSTRAINT fk_daily_allocation_item FOREIGN KEY (daily_employment_income_item_id)
            REFERENCES institution_daily_employment_income_items(id) ON DELETE RESTRICT ON UPDATE CASCADE,
        CONSTRAINT fk_daily_allocation_workday FOREIGN KEY (daily_employment_income_workday_id)
            REFERENCES institution_daily_employment_income_workdays(id) ON DELETE RESTRICT ON UPDATE CASCADE,
        CONSTRAINT ck_daily_allocation_scope CHECK (allocation_scope_code IN ('TAX','INSURANCE')),
        CONSTRAINT ck_daily_allocation_basis CHECK (
            allocation_basis_amount>=0 AND allocation_numerator>=0
            AND allocation_denominator>0 AND allocation_numerator<=allocation_denominator
        ),
        CONSTRAINT ck_daily_allocation_residual CHECK (residual_applied IN (0,1)),
        CONSTRAINT ck_daily_allocation_rank CHECK (decision_rank>=1)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
      COMMENT='기관 확정금액의 Group·Item·Workday 결정적 배부원장';
END$$
CALL migrate_20260827_15_up()$$
DROP PROCEDURE migrate_20260827_15_up$$
DELIMITER ;
