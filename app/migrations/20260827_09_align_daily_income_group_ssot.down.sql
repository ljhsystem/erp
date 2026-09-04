SET NAMES utf8mb4;

DELIMITER $$
CREATE PROCEDURE rollback_daily_income_group_ssot()
BEGIN
    IF EXISTS (
        SELECT 1
        FROM institution_daily_employment_income_items
        WHERE project_id IS NULL OR work_team_id IS NULL OR business_unit <> 'CONSTRUCTION'
        LIMIT 1
    ) THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Daily income group data cannot be represented by the previous schema';
    END IF;

    ALTER TABLE institution_social_insurance_workplaces
        DROP INDEX uq_social_workplace_start,
        DROP INDEX idx_social_workplace_resolve,
        ADD UNIQUE KEY uq_social_workplace_start (company_id, scope_project_key, effective_from),
        ADD KEY idx_social_workplace_resolve (
            company_id,
            scope_project_key,
            effective_from,
            effective_to,
            confirmation_status_code
        ),
        DROP COLUMN business_unit;

    ALTER TABLE institution_social_insurance_workplaces
        DROP INDEX idx_social_workplace_company;

    ALTER TABLE institution_daily_employment_income_items
        DROP CONSTRAINT ck_daily_item_scope,
        DROP INDEX uq_daily_income_item_business,
        DROP COLUMN work_team_scope_key,
        DROP COLUMN scope_project_key,
        MODIFY COLUMN work_team_id VARCHAR(36) NOT NULL;

    ALTER TABLE institution_daily_employment_income_items
        ADD COLUMN scope_project_key VARCHAR(50)
            GENERATED ALWAYS AS (IFNULL(project_id, 'HEAD_OFFICE')) STORED AFTER project_id,
        ADD UNIQUE KEY uq_daily_income_item_business (
            daily_employment_income_id,
            scope_project_key,
            work_team_id,
            worker_client_id
        ),
        ADD CONSTRAINT ck_daily_item_scope CHECK (
            (work_scope_code = 'PROJECT' AND project_id IS NOT NULL)
            OR (work_scope_code = 'HEAD_OFFICE' AND project_id IS NULL)
        );

    ALTER TABLE institution_daily_employment_income_items
        DROP INDEX idx_daily_income_item_header;

    ALTER TABLE system_projects
        DROP INDEX idx_project_business_unit_period,
        DROP COLUMN business_unit;

    UPDATE system_codes
    SET extra_data = JSON_REMOVE(COALESCE(NULLIF(extra_data, ''), '{}'), '$.daily_employment_income')
    WHERE code_group = 'BUSINESS_UNIT'
      AND code IN ('HQ', 'CONSTRUCTION', 'ECOMMERCE');
END$$
DELIMITER ;

CALL rollback_daily_income_group_ssot();
DROP PROCEDURE rollback_daily_income_group_ssot;
