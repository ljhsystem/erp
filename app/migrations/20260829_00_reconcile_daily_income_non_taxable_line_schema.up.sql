DELIMITER $$
CREATE PROCEDURE migrate_20260829_00_up()
BEGIN
    IF (SELECT COUNT(*) FROM institution_daily_employment_income_lines) <> 37 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='호환 백필 대상 Line은 정확히 37건이어야 합니다.';
    END IF;
    IF EXISTS (
        SELECT 1 FROM institution_daily_employment_income_lines
        WHERE CONCAT(line_type_code,':',line_code) NOT IN (
            'PAY:BASE_PAY','PAY:TAXABLE_ADDITIONAL_PAY','PAY:NON_TAXABLE_ADDITIONAL_PAY','PAY:PAY_ADJUSTMENT',
            'DEDUCTION:DAILY_WORKER_INCOME_TAX','DEDUCTION:LOCAL_INCOME_TAX',
            'DEDUCTION:NATIONAL_PENSION','DEDUCTION:HEALTH_INSURANCE','DEDUCTION:LONG_TERM_CARE',
            'DEDUCTION:EMPLOYMENT_INSURANCE','EMPLOYER_BURDEN:EMPLOYMENT_INSURANCE',
            'EMPLOYER_BURDEN:EMPLOYMENT_INSURANCE_VOCATIONAL',
            'EMPLOYER_BURDEN:INDUSTRIAL_ACCIDENT_INSURANCE'
        )
    ) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='구조화된 계약으로 판정할 수 없는 Line이 있습니다.';
    END IF;
    IF (SELECT COUNT(*) FROM institution_daily_employment_income_lines
        WHERE line_type_code='PAY' AND line_code='NON_TAXABLE_ADDITIONAL_PAY'
          AND final_amount<>0) <> 0 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Revision이 없는 비과세 실금액 Line이 있습니다.';
    END IF;
    IF (SELECT COUNT(*) FROM institution_daily_employment_income_lines
        WHERE line_type_code='DEDUCTION'
          AND line_code IN ('NATIONAL_PENSION','HEALTH_INSURANCE','LONG_TERM_CARE')
          AND application_status_code IS NULL) <> 3 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='보험 미확정 Line 3건 보존조건이 일치하지 않습니다.';
    END IF;
    IF EXISTS (
        SELECT 1 FROM information_schema.TABLES
        WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN (
            'institution_daily_employment_income_non_taxable_revisions',
            'institution_daily_employment_income_line_backfill_audits'
        )
    ) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='호환 Migration 대상 테이블이 이미 존재합니다.';
    END IF;
    IF EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='institution_daily_employment_income_lines'
          AND COLUMN_NAME IN (
              'taxability_code','non_taxable_revision_id','effective_from','effective_to',
              'revision_scope_key','period_scope_key'
          )
    ) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='호환 Migration 대상 컬럼이 이미 존재합니다.';
    END IF;
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='institution_daily_employment_income_lines'
          AND COLUMN_NAME='workday_scope_key' AND IS_NULLABLE='NO' AND CHARACTER_MAXIMUM_LENGTH=36
    ) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='기존 workday_scope_key 계약이 일치하지 않습니다.';
    END IF;
    IF NOT EXISTS (
        SELECT 1
        FROM information_schema.STATISTICS
        WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='institution_daily_employment_income_lines'
          AND INDEX_NAME='uq_daily_income_line_scope' AND NON_UNIQUE=0
        GROUP BY INDEX_NAME
        HAVING GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX)=
            'daily_employment_income_item_id,workday_scope_key,line_type_code,line_code'
    ) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='기존 4열 유일키 계약이 일치하지 않습니다.';
    END IF;
    IF EXISTS (
        SELECT 1 FROM institution_daily_employment_income_lines
        GROUP BY daily_employment_income_item_id,workday_scope_key,line_type_code,line_code
        HAVING COUNT(*)>1
    ) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='기존 4열 유일키 기준 중복 Line이 있습니다.';
    END IF;

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
        CONSTRAINT ck_daily_non_tax_revision_status CHECK (
            revision_status_code IN ('DRAFT','CONFIRMED','CORRECTED','CANCELLED')
        ),
        CONSTRAINT ck_daily_non_tax_revision_confirmation CHECK (
            (revision_status_code='DRAFT' AND confirmed_by IS NULL AND confirmed_at IS NULL)
            OR (revision_status_code<>'DRAFT' AND confirmed_by IS NOT NULL AND confirmed_at IS NOT NULL)
        )
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
      COMMENT='일용근로소득 비과세 판단 Revision';

    CREATE TABLE institution_daily_employment_income_line_backfill_audits (
        id VARCHAR(36) NOT NULL,
        migration_id VARCHAR(100) NOT NULL,
        daily_employment_income_line_id VARCHAR(36) NOT NULL,
        previous_snapshot LONGTEXT NOT NULL,
        new_snapshot LONGTEXT NOT NULL,
        decision_rule_code VARCHAR(150) NOT NULL,
        decision_basis_id VARCHAR(191) NOT NULL,
        payload_hash CHAR(64) NOT NULL,
        verification_status_code VARCHAR(20) NOT NULL,
        executed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        executed_by VARCHAR(100) NOT NULL COMMENT 'ActorHelper Actor Token',
        PRIMARY KEY (id),
        UNIQUE KEY uq_daily_income_line_backfill_migration (migration_id,daily_employment_income_line_id),
        KEY idx_daily_income_line_backfill_line (daily_employment_income_line_id,executed_at),
        CONSTRAINT fk_daily_income_line_backfill_line FOREIGN KEY (daily_employment_income_line_id)
            REFERENCES institution_daily_employment_income_lines(id) ON DELETE RESTRICT ON UPDATE CASCADE,
        CONSTRAINT ck_daily_income_line_backfill_previous CHECK (JSON_VALID(previous_snapshot)),
        CONSTRAINT ck_daily_income_line_backfill_new CHECK (JSON_VALID(new_snapshot)),
        CONSTRAINT ck_daily_income_line_backfill_hash CHECK (payload_hash REGEXP '^[0-9a-f]{64}$'),
        CONSTRAINT ck_daily_income_line_backfill_status CHECK (
            verification_status_code IN ('EXPECTED','VERIFIED','FAILED')
        )
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
      COMMENT='일용근로소득 Line 호환 백필 감사자료';

    ALTER TABLE institution_daily_employment_income_lines
        ADD COLUMN taxability_code VARCHAR(20) NULL AFTER line_type_code,
        ADD COLUMN non_taxable_revision_id VARCHAR(36) NULL AFTER statutory_standard_id,
        ADD COLUMN effective_from DATE NULL AFTER non_taxable_revision_id,
        ADD COLUMN effective_to DATE NULL AFTER effective_from,
        ADD COLUMN revision_scope_key VARCHAR(36) NULL AFTER workday_scope_key,
        ADD COLUMN period_scope_key VARCHAR(32) NULL AFTER revision_scope_key,
        ADD KEY idx_daily_income_line_revision (non_taxable_revision_id),
        ADD CONSTRAINT fk_daily_income_line_non_tax_revision FOREIGN KEY (non_taxable_revision_id)
            REFERENCES institution_daily_employment_income_non_taxable_revisions(id)
            ON DELETE RESTRICT ON UPDATE CASCADE;

    INSERT INTO institution_daily_employment_income_line_backfill_audits (
        id,migration_id,daily_employment_income_line_id,previous_snapshot,new_snapshot,
        decision_rule_code,decision_basis_id,payload_hash,verification_status_code,executed_at,executed_by
    )
    SELECT UUID(),'20260829_00_reconcile_daily_income_non_taxable_line_schema',line_row.id,
        JSON_OBJECT(
            'taxability_code',NULL,'non_taxable_revision_id',NULL,'effective_from',NULL,'effective_to',NULL,
            'workday_scope_key',line_row.workday_scope_key,'revision_scope_key',NULL,'period_scope_key',NULL,
            'application_status_code',line_row.application_status_code,'final_amount',line_row.final_amount
        ),
        JSON_OBJECT(
            'taxability_code',CASE
                WHEN line_row.line_type_code<>'PAY' THEN NULL
                WHEN line_row.line_code='NON_TAXABLE_ADDITIONAL_PAY' THEN 'NON_TAXABLE'
                ELSE 'TAXABLE' END,
            'non_taxable_revision_id',NULL,'effective_from',NULL,'effective_to',NULL,
            'workday_scope_key',line_row.workday_scope_key,'revision_scope_key','BASE','period_scope_key','NONE',
            'application_status_code',line_row.application_status_code,'final_amount',line_row.final_amount,
            'key_schema_version','DAILY_INCOME_LINE_SCOPE_V1'
        ),
        CONCAT('DAILY_INCOME_LINE_CONTRACT_V1:',line_row.line_type_code,':',line_row.line_code),
        CONCAT(line_row.daily_employment_income_item_id,':',line_row.workday_scope_key),
        SHA2(CONCAT_WS('|',line_row.id,line_row.line_type_code,line_row.line_code,
            COALESCE(line_row.application_status_code,'<NULL>'),line_row.final_amount,
            line_row.workday_scope_key,'BASE','NONE'),256),
        'EXPECTED',NOW(),'SYSTEM:MIGRATION'
    FROM institution_daily_employment_income_lines line_row;

    UPDATE institution_daily_employment_income_lines
    SET taxability_code=CASE
            WHEN line_type_code<>'PAY' THEN NULL
            WHEN line_code='NON_TAXABLE_ADDITIONAL_PAY' THEN 'NON_TAXABLE'
            ELSE 'TAXABLE'
        END,
        revision_scope_key='BASE',
        period_scope_key='NONE',
        updated_at=updated_at;

    IF ROW_COUNT()<>37 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='호환 백필 변경 건수가 37건과 일치하지 않습니다.';
    END IF;
    IF EXISTS (
        SELECT 1 FROM institution_daily_employment_income_lines
        GROUP BY daily_employment_income_item_id,workday_scope_key,line_type_code,line_code,
                 revision_scope_key,period_scope_key
        HAVING COUNT(*)>1
    ) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='최종 유일키 기준 중복 Line이 있습니다.';
    END IF;

    ALTER TABLE institution_daily_employment_income_lines
        MODIFY COLUMN revision_scope_key VARCHAR(36) NOT NULL,
        MODIFY COLUMN period_scope_key VARCHAR(32) NOT NULL,
        ADD UNIQUE KEY uq_daily_income_line_revision_scope (
            daily_employment_income_item_id,workday_scope_key,line_type_code,line_code,
            revision_scope_key,period_scope_key
        ),
        ADD CONSTRAINT ck_daily_income_line_taxability CHECK (
            (line_type_code='PAY' AND taxability_code IN ('TAXABLE','NON_TAXABLE'))
            OR (line_type_code<>'PAY' AND taxability_code IS NULL)
        ),
        ADD CONSTRAINT ck_daily_income_line_period CHECK (
            (effective_from IS NULL AND effective_to IS NULL)
            OR (effective_from IS NOT NULL AND effective_to IS NOT NULL AND effective_from<=effective_to)
        );

    ALTER TABLE institution_daily_employment_income_lines
        DROP INDEX uq_daily_income_line_scope;

    UPDATE institution_daily_employment_income_line_backfill_audits audit_row
    JOIN institution_daily_employment_income_lines line_row
      ON line_row.id=audit_row.daily_employment_income_line_id
    SET audit_row.verification_status_code='VERIFIED'
    WHERE audit_row.migration_id='20260829_00_reconcile_daily_income_non_taxable_line_schema'
      AND JSON_UNQUOTE(JSON_EXTRACT(audit_row.new_snapshot,'$.workday_scope_key'))=line_row.workday_scope_key
      AND JSON_UNQUOTE(JSON_EXTRACT(audit_row.new_snapshot,'$.revision_scope_key'))=line_row.revision_scope_key
      AND JSON_UNQUOTE(JSON_EXTRACT(audit_row.new_snapshot,'$.period_scope_key'))=line_row.period_scope_key
      AND COALESCE(NULLIF(JSON_UNQUOTE(JSON_EXTRACT(audit_row.new_snapshot,'$.taxability_code')),'null'),'<NULL>')
          =COALESCE(line_row.taxability_code,'<NULL>');

    IF (SELECT COUNT(*) FROM institution_daily_employment_income_line_backfill_audits
        WHERE migration_id='20260829_00_reconcile_daily_income_non_taxable_line_schema'
          AND verification_status_code='VERIFIED')<>37 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='호환 백필 감사 검증이 37건 모두 통과하지 않았습니다.';
    END IF;
END$$
CALL migrate_20260829_00_up()$$
DROP PROCEDURE migrate_20260829_00_up$$
DELIMITER ;

INSERT INTO auth_permissions
    (id,sort_no,page,permission_source,category,permission_key,permission_name,description,page_key,is_active,created_at,created_by,updated_at,updated_by)
SELECT UUID(),(SELECT COALESCE(MAX(p.sort_no),0)+v.seq FROM auth_permissions p),
       '일용근로소득','ROUTE','대외기관업무',v.permission_key,v.permission_name,v.description,
       'web.institution.income_data.daily_employment',1,NOW(),'SYSTEM:MIGRATION',NOW(),'SYSTEM:MIGRATION'
FROM (
    SELECT 1 seq,'api.institution.income_data.daily_employment.non_taxable_list' permission_key,'비과세 조회' permission_name,'일용근로소득 비과세 Revision 조회' description
    UNION ALL SELECT 2,'api.institution.income_data.daily_employment.non_taxable_save','비과세 입력·수정','일용근로소득 DRAFT 비과세 Revision 입력·수정'
    UNION ALL SELECT 3,'api.institution.income_data.daily_employment.non_taxable_confirm','비과세 확인','일용근로소득 비과세 Revision 확인'
    UNION ALL SELECT 4,'api.institution.income_data.daily_employment.non_taxable_correct','비과세 정정','일용근로소득 비과세 정정 Revision 생성'
) v
WHERE NOT EXISTS (SELECT 1 FROM auth_permissions x WHERE x.permission_key=v.permission_key);

INSERT INTO auth_role_permissions (id,role_id,permission_id,created_at,created_by)
SELECT UUID(),source.role_id,target.id,NOW(),'SYSTEM:MIGRATION'
FROM auth_role_permissions source
JOIN auth_permissions page_permission ON page_permission.id=source.permission_id
JOIN auth_permissions target ON target.permission_key='api.institution.income_data.daily_employment.non_taxable_list'
LEFT JOIN auth_role_permissions existing ON existing.role_id=source.role_id AND existing.permission_id=target.id
WHERE page_permission.permission_key='web.institution.income_data.daily_employment' AND existing.id IS NULL;

INSERT INTO auth_role_permissions (id,role_id,permission_id,created_at,created_by)
SELECT UUID(),source.role_id,target.id,NOW(),'SYSTEM:MIGRATION'
FROM auth_role_permissions source
JOIN auth_permissions save_permission ON save_permission.id=source.permission_id
JOIN auth_permissions target ON target.permission_key='api.institution.income_data.daily_employment.non_taxable_save'
LEFT JOIN auth_role_permissions existing ON existing.role_id=source.role_id AND existing.permission_id=target.id
WHERE save_permission.permission_key='api.institution.income_data.daily_employment.save' AND existing.id IS NULL;

INSERT INTO auth_role_permissions (id,role_id,permission_id,created_at,created_by)
SELECT UUID(),role_row.id,permission_row.id,NOW(),'SYSTEM:MIGRATION'
FROM auth_roles role_row
JOIN auth_permissions permission_row ON permission_row.permission_key IN (
    'api.institution.income_data.daily_employment.non_taxable_confirm',
    'api.institution.income_data.daily_employment.non_taxable_correct'
)
LEFT JOIN auth_role_permissions existing
    ON existing.role_id=role_row.id AND existing.permission_id=permission_row.id
WHERE role_row.role_key='super_admin' AND role_row.is_active=1 AND existing.id IS NULL;
