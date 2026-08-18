ALTER TABLE system_statutory_standards
    DROP COLUMN IF EXISTS scope_key,
    DROP INDEX IF EXISTS uk_statutory_standard_period;
