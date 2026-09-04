SET NAMES utf8mb4;

ALTER TABLE system_work_teams
    ADD COLUMN business_unit VARCHAR(30) NULL COMMENT '사업구분(BUSINESS_UNIT)' AFTER team_name;

UPDATE system_work_teams
SET business_unit = 'CONSTRUCTION'
WHERE business_unit IS NULL;

ALTER TABLE system_work_teams
    MODIFY COLUMN business_unit VARCHAR(30) NOT NULL DEFAULT 'CONSTRUCTION' COMMENT '사업구분(BUSINESS_UNIT)',
    ADD KEY idx_work_team_business_unit (business_unit, is_active, sort_no);

ALTER TABLE institution_daily_employment_income_items
    ADD COLUMN business_unit VARCHAR(30) NULL COMMENT '사업구분(BUSINESS_UNIT)' AFTER sort_no;

UPDATE institution_daily_employment_income_items
SET business_unit = CASE WHEN work_scope_code = 'HEAD_OFFICE' THEN 'HQ' ELSE 'CONSTRUCTION' END
WHERE business_unit IS NULL;

ALTER TABLE institution_daily_employment_income_items
    MODIFY COLUMN business_unit VARCHAR(30) NOT NULL COMMENT '사업구분(BUSINESS_UNIT)',
    ADD KEY idx_daily_income_item_business_unit (business_unit, work_team_id, worker_client_id);

ALTER TABLE institution_daily_employment_income_workdays
    MODIFY COLUMN work_team_assignment_id VARCHAR(36) NULL;
