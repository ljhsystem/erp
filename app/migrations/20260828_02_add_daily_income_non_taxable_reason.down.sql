ALTER TABLE institution_daily_employment_income_workdays
    DROP CONSTRAINT ck_daily_workday_non_taxable_reason_required,
    DROP CONSTRAINT ck_daily_workday_non_taxable_reason,
    DROP COLUMN non_taxable_reason;
