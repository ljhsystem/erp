DELIMITER $$
CREATE PROCEDURE migrate_20260827_23_down()
BEGIN
    IF EXISTS (SELECT 1 FROM institution_daily_employment_income_accounting_links LIMIT 1)
       OR EXISTS (SELECT 1 FROM institution_daily_employment_income_closures LIMIT 1)
       OR EXISTS (SELECT 1 FROM institution_daily_employment_income_non_taxable_revisions LIMIT 1)
       OR EXISTS (SELECT 1 FROM institution_daily_employment_income_lines WHERE non_taxable_revision_id IS NOT NULL LIMIT 1) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='호환 Baseline 업무자료가 있어 Down할 수 없습니다.';
    END IF;
    ALTER TABLE institution_daily_employment_income_lines
        DROP FOREIGN KEY fk_daily_income_line_non_tax_revision,
        DROP INDEX idx_daily_income_line_revision,
        DROP INDEX uq_daily_income_line_scope,
        DROP COLUMN period_scope_key,
        DROP COLUMN revision_scope_key,
        DROP COLUMN workday_scope_key,
        DROP COLUMN effective_to,
        DROP COLUMN effective_from,
        DROP COLUMN non_taxable_revision_id,
        DROP COLUMN taxability_code,
        ADD UNIQUE KEY uq_daily_income_line
            (daily_employment_income_item_id,daily_employment_income_workday_id,line_type_code,line_code);
    DROP TABLE institution_daily_employment_income_non_taxable_revisions;
    DROP TABLE institution_daily_employment_income_accounting_links;
    DROP TABLE institution_daily_employment_income_closures;
END$$
CALL migrate_20260827_23_down()$$
DROP PROCEDURE migrate_20260827_23_down$$
DELIMITER ;
