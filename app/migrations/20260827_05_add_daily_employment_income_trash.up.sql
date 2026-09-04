SET NAMES utf8mb4;

ALTER TABLE institution_daily_employment_incomes
    ADD COLUMN deleted_at DATETIME NULL AFTER updated_by,
    ADD COLUMN deleted_by VARCHAR(100) NULL AFTER deleted_at,
    ADD KEY idx_daily_income_deleted_at (deleted_at);
