SET NAMES utf8mb4;

ALTER TABLE institution_daily_employment_incomes
    DROP KEY idx_daily_income_deleted_at,
    DROP COLUMN deleted_by,
    DROP COLUMN deleted_at;
