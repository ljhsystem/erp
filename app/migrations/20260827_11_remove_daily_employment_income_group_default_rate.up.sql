SET NAMES utf8mb4;

ALTER TABLE institution_daily_employment_income_groups
    DROP CONSTRAINT ck_daily_income_group_rate,
    DROP COLUMN default_daily_rate;
