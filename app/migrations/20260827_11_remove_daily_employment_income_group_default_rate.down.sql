SET NAMES utf8mb4;

ALTER TABLE institution_daily_employment_income_groups
    ADD COLUMN default_daily_rate DECIMAL(18,2) NOT NULL DEFAULT 0 AFTER work_description,
    ADD CONSTRAINT ck_daily_income_group_rate CHECK (default_daily_rate >= 0);
