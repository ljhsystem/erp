SET NAMES utf8mb4;

UPDATE system_codes
SET extra_data = JSON_SET(
    COALESCE(NULLIF(extra_data, ''), '{}'),
    '$.daily_employment_income',
    CASE code
        WHEN 'CONSTRUCTION' THEN JSON_OBJECT('uses_project', TRUE, 'requires_project', TRUE, 'uses_work_team', TRUE, 'requires_work_team', TRUE)
        ELSE JSON_OBJECT('uses_project', FALSE, 'requires_project', FALSE, 'uses_work_team', FALSE, 'requires_work_team', FALSE)
    END
)
WHERE code_group = 'BUSINESS_UNIT'
  AND code IN ('HQ', 'CONSTRUCTION', 'ECOMMERCE');

ALTER TABLE system_projects
    ADD COLUMN business_unit VARCHAR(30) NULL COMMENT '사업구분(BUSINESS_UNIT)' AFTER project_name;

UPDATE system_projects
SET business_unit = 'CONSTRUCTION'
WHERE business_unit IS NULL;

ALTER TABLE system_projects
    MODIFY COLUMN business_unit VARCHAR(30) NOT NULL DEFAULT 'CONSTRUCTION' COMMENT '사업구분(BUSINESS_UNIT)',
    ADD KEY idx_project_business_unit_period (business_unit, is_active, start_date, completion_date, sort_no);

ALTER TABLE institution_daily_employment_income_items
    ADD KEY idx_daily_income_item_header (daily_employment_income_id);

ALTER TABLE institution_daily_employment_income_items
    DROP CONSTRAINT ck_daily_item_scope,
    DROP INDEX uq_daily_income_item_business,
    DROP COLUMN scope_project_key,
    MODIFY COLUMN work_team_id VARCHAR(36) NULL;

ALTER TABLE institution_daily_employment_income_items
    ADD COLUMN scope_project_key VARCHAR(50)
        GENERATED ALWAYS AS (IFNULL(project_id, 'NO_PROJECT')) STORED AFTER project_id,
    ADD COLUMN work_team_scope_key VARCHAR(50)
        GENERATED ALWAYS AS (IFNULL(work_team_id, 'NO_WORK_TEAM')) STORED AFTER work_team_id,
    ADD UNIQUE KEY uq_daily_income_item_business (
        daily_employment_income_id,
        business_unit,
        scope_project_key,
        work_team_scope_key,
        worker_client_id
    ),
    ADD CONSTRAINT ck_daily_item_scope CHECK (
        (work_scope_code = 'PROJECT' AND project_id IS NOT NULL)
        OR (work_scope_code = 'HEAD_OFFICE' AND project_id IS NULL)
    );

ALTER TABLE institution_social_insurance_workplaces
    ADD COLUMN business_unit VARCHAR(30) NULL COMMENT '사업구분(BUSINESS_UNIT)' AFTER company_id;

UPDATE institution_social_insurance_workplaces
SET business_unit = CASE WHEN project_id IS NULL THEN 'HQ' ELSE 'CONSTRUCTION' END
WHERE business_unit IS NULL;

ALTER TABLE institution_social_insurance_workplaces
    ADD KEY idx_social_workplace_company (company_id);

ALTER TABLE institution_social_insurance_workplaces
    MODIFY COLUMN business_unit VARCHAR(30) NOT NULL COMMENT '사업구분(BUSINESS_UNIT)',
    DROP INDEX uq_social_workplace_start,
    DROP INDEX idx_social_workplace_resolve,
    ADD UNIQUE KEY uq_social_workplace_start (
        company_id,
        business_unit,
        scope_project_key,
        effective_from
    ),
    ADD KEY idx_social_workplace_resolve (
        company_id,
        business_unit,
        scope_project_key,
        effective_from,
        effective_to,
        confirmation_status_code
    );
