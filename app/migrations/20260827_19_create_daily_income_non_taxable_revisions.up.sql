DELIMITER $$
CREATE PROCEDURE migrate_20260827_19_up()
BEGIN
    IF EXISTS (
        SELECT 1 FROM information_schema.TABLES
        WHERE TABLE_SCHEMA=DATABASE()
          AND TABLE_NAME='institution_daily_employment_income_non_taxable_revisions'
    ) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='비과세 Revision 테이블이 이미 존재합니다.';
    END IF;
    IF EXISTS (SELECT 1 FROM institution_daily_employment_income_lines LIMIT 1) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='기존 Line 자료가 있어 비과세 Grain 전환 전 별도 백필이 필요합니다.';
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
        UNIQUE KEY uq_daily_non_tax_revision_no (daily_employment_income_item_id, revision_no),
        KEY idx_daily_non_tax_revision_header (daily_employment_income_id, revision_status_code),
        KEY idx_daily_non_tax_revision_period (daily_employment_income_item_id, effective_from, effective_to),
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
        CONSTRAINT ck_daily_non_tax_revision_amount CHECK (applied_amount > 0),
        CONSTRAINT ck_daily_non_tax_revision_scope CHECK (
            (daily_employment_income_workday_id IS NOT NULL AND effective_from IS NULL AND effective_to IS NULL)
            OR (daily_employment_income_workday_id IS NULL AND effective_from IS NOT NULL
                AND effective_to IS NOT NULL AND effective_from <= effective_to)
        ),
        CONSTRAINT ck_daily_non_tax_revision_status CHECK
            (revision_status_code IN ('DRAFT','CONFIRMED','CORRECTED','CANCELLED')),
        CONSTRAINT ck_daily_non_tax_revision_confirmation CHECK (
            (revision_status_code='DRAFT' AND confirmed_by IS NULL AND confirmed_at IS NULL)
            OR (revision_status_code<>'DRAFT' AND confirmed_by IS NOT NULL AND confirmed_at IS NOT NULL)
        )
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
      COMMENT='일용근로소득 비과세 불변 Revision';

    ALTER TABLE institution_daily_employment_income_lines
        DROP INDEX uq_daily_income_line,
        ADD COLUMN taxability_code VARCHAR(20) NULL AFTER line_type_code,
        ADD COLUMN non_taxable_revision_id VARCHAR(36) NULL AFTER statutory_standard_id,
        ADD COLUMN effective_from DATE NULL AFTER non_taxable_revision_id,
        ADD COLUMN effective_to DATE NULL AFTER effective_from,
        ADD COLUMN workday_scope_key VARCHAR(36)
            GENERATED ALWAYS AS (COALESCE(daily_employment_income_workday_id,'ITEM')) STORED,
        ADD COLUMN revision_scope_key VARCHAR(36)
            GENERATED ALWAYS AS (COALESCE(non_taxable_revision_id,'BASE')) STORED,
        ADD UNIQUE KEY uq_daily_income_line_scope
            (daily_employment_income_item_id,workday_scope_key,line_type_code,line_code,revision_scope_key),
        ADD KEY idx_daily_income_line_revision (non_taxable_revision_id),
        ADD CONSTRAINT fk_daily_income_line_non_tax_revision FOREIGN KEY (non_taxable_revision_id)
            REFERENCES institution_daily_employment_income_non_taxable_revisions(id)
            ON DELETE RESTRICT ON UPDATE CASCADE,
        ADD CONSTRAINT ck_daily_income_line_taxability CHECK (
            (line_type_code='PAY' AND taxability_code IN ('TAXABLE','NON_TAXABLE'))
            OR (line_type_code<>'PAY' AND taxability_code IS NULL)
        ),
        ADD CONSTRAINT ck_daily_income_line_period CHECK (
            (effective_from IS NULL AND effective_to IS NULL)
            OR (effective_from IS NOT NULL AND effective_to IS NOT NULL AND effective_from <= effective_to)
        ),
        ADD CONSTRAINT ck_daily_income_line_non_tax_revision CHECK (
            (taxability_code='NON_TAXABLE' AND non_taxable_revision_id IS NOT NULL)
            OR (taxability_code<>'NON_TAXABLE' AND non_taxable_revision_id IS NULL)
            OR taxability_code IS NULL
        );
END$$
CALL migrate_20260827_19_up()$$
DROP PROCEDURE migrate_20260827_19_up$$
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
