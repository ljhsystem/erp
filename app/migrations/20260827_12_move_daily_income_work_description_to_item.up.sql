SET NAMES utf8mb4;

ALTER TABLE institution_daily_employment_income_items
    ADD COLUMN work_type_code VARCHAR(50) NULL AFTER worker_registration_number_snapshot,
    ADD COLUMN work_description VARCHAR(500) NULL AFTER work_type_code;

UPDATE institution_daily_employment_income_items i
JOIN institution_daily_employment_income_groups g ON g.id=i.daily_employment_income_group_id
SET i.work_description=g.work_description,
    i.work_type_code='UNCLASSIFIED';

ALTER TABLE institution_daily_employment_income_items
    MODIFY COLUMN work_type_code VARCHAR(50) NOT NULL,
    MODIFY COLUMN work_description VARCHAR(500) NOT NULL,
    ADD KEY idx_daily_income_item_work_type (work_type_code, worker_client_id),
    ADD CONSTRAINT ck_daily_income_item_description CHECK (CHAR_LENGTH(TRIM(work_description)) > 0);

ALTER TABLE institution_daily_employment_income_groups
    DROP CONSTRAINT ck_daily_income_group_description,
    DROP COLUMN work_description;

ALTER TABLE institution_daily_employment_incomes
    DROP INDEX uq_daily_income_document_number,
    DROP COLUMN document_number;
