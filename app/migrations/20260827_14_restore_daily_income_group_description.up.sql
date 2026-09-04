SET NAMES utf8mb4;

ALTER TABLE institution_daily_employment_income_groups
    ADD COLUMN work_description VARCHAR(500) NOT NULL AFTER work_team_id,
    ADD CONSTRAINT ck_daily_income_group_description CHECK (CHAR_LENGTH(TRIM(work_description)) > 0);
