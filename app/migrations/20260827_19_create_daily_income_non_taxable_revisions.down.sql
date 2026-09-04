DELIMITER $$
CREATE PROCEDURE migrate_20260827_19_down()
BEGIN
    IF EXISTS (SELECT 1 FROM institution_daily_employment_income_non_taxable_revisions LIMIT 1)
       OR EXISTS (SELECT 1 FROM institution_daily_employment_income_lines WHERE non_taxable_revision_id IS NOT NULL LIMIT 1) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='비과세 Revision 또는 연결 Line이 있어 Down할 수 없습니다.';
    END IF;

    DELETE role_permission
    FROM auth_role_permissions role_permission
    JOIN auth_permissions permission_row ON permission_row.id=role_permission.permission_id
    WHERE permission_row.permission_key IN (
        'api.institution.income_data.daily_employment.non_taxable_list',
        'api.institution.income_data.daily_employment.non_taxable_save',
        'api.institution.income_data.daily_employment.non_taxable_confirm',
        'api.institution.income_data.daily_employment.non_taxable_correct'
    );
    DELETE FROM auth_permissions WHERE permission_key IN (
        'api.institution.income_data.daily_employment.non_taxable_list',
        'api.institution.income_data.daily_employment.non_taxable_save',
        'api.institution.income_data.daily_employment.non_taxable_confirm',
        'api.institution.income_data.daily_employment.non_taxable_correct'
    );

    ALTER TABLE institution_daily_employment_income_lines
        DROP CONSTRAINT fk_daily_income_line_non_tax_revision,
        DROP CONSTRAINT ck_daily_income_line_taxability,
        DROP CONSTRAINT ck_daily_income_line_period,
        DROP CONSTRAINT ck_daily_income_line_non_tax_revision,
        DROP INDEX uq_daily_income_line_scope,
        DROP INDEX idx_daily_income_line_revision,
        DROP COLUMN revision_scope_key,
        DROP COLUMN workday_scope_key,
        DROP COLUMN effective_to,
        DROP COLUMN effective_from,
        DROP COLUMN non_taxable_revision_id,
        DROP COLUMN taxability_code,
        ADD UNIQUE KEY uq_daily_income_line
            (daily_employment_income_item_id,daily_employment_income_workday_id,line_type_code,line_code);
    DROP TABLE institution_daily_employment_income_non_taxable_revisions;
END$$
CALL migrate_20260827_19_down()$$
DROP PROCEDURE migrate_20260827_19_down$$
DELIMITER ;
