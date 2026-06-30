ALTER TABLE `ledger_data_format_columns`
    DROP INDEX `uk_format_original_column`;

ALTER TABLE `ledger_data_format_columns`
    DROP COLUMN `original_column_key`;
