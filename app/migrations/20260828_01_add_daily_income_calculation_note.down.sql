ALTER TABLE institution_daily_employment_income_workdays
    DROP CONSTRAINT ck_daily_workday_calculation_note,
    DROP COLUMN calculation_note;
