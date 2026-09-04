DELIMITER $$
CREATE PROCEDURE migrate_20260829_00_down()
BEGIN
    IF EXISTS (SELECT 1 FROM institution_daily_employment_income_non_taxable_revisions LIMIT 1)
       OR EXISTS (SELECT 1 FROM institution_daily_employment_income_lines
                 WHERE non_taxable_revision_id IS NOT NULL LIMIT 1) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='비과세 Revision 또는 연결 Line이 있어 Down할 수 없습니다.';
    END IF;
    IF (SELECT COUNT(*) FROM institution_daily_employment_income_line_backfill_audits
        WHERE migration_id='20260829_00_reconcile_daily_income_non_taxable_line_schema'
          AND verification_status_code='VERIFIED')<>37 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='복구용 감사자료 37건이 완전하지 않습니다.';
    END IF;
    IF EXISTS (
        SELECT 1 FROM institution_daily_employment_income_lines
        GROUP BY daily_employment_income_item_id,workday_scope_key,line_type_code,line_code
        HAVING COUNT(*)>1
    ) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='기존 4열 유일키를 복원할 수 없는 중복이 있습니다.';
    END IF;

    ALTER TABLE institution_daily_employment_income_lines
        ADD UNIQUE KEY uq_daily_income_line_scope (
            daily_employment_income_item_id,workday_scope_key,line_type_code,line_code
        );

    ALTER TABLE institution_daily_employment_income_lines
        DROP CONSTRAINT fk_daily_income_line_non_tax_revision,
        DROP CONSTRAINT ck_daily_income_line_taxability,
        DROP CONSTRAINT ck_daily_income_line_period,
        DROP INDEX uq_daily_income_line_revision_scope,
        DROP INDEX idx_daily_income_line_revision,
        DROP COLUMN period_scope_key,
        DROP COLUMN revision_scope_key,
        DROP COLUMN effective_to,
        DROP COLUMN effective_from,
        DROP COLUMN non_taxable_revision_id,
        DROP COLUMN taxability_code;

    DROP TABLE institution_daily_employment_income_line_backfill_audits;
    DROP TABLE institution_daily_employment_income_non_taxable_revisions;
END$$
CALL migrate_20260829_00_down()$$
DROP PROCEDURE migrate_20260829_00_down$$
DELIMITER ;

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
