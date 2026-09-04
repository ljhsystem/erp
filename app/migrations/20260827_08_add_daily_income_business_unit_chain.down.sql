SET NAMES utf8mb4;

ALTER TABLE institution_daily_employment_income_workdays
    MODIFY COLUMN work_team_assignment_id VARCHAR(36) NOT NULL;

ALTER TABLE institution_daily_employment_income_items
    DROP KEY idx_daily_income_item_business_unit,
    DROP COLUMN business_unit;

ALTER TABLE system_work_teams
    DROP KEY idx_work_team_business_unit,
    DROP COLUMN business_unit;
