DELIMITER $$
CREATE PROCEDURE migrate_20260827_21_down()
BEGIN
    IF EXISTS (SELECT 1 FROM institution_daily_employment_income_non_taxable_audits LIMIT 1)
       OR EXISTS (
           SELECT 1 FROM institution_daily_employment_income_commands
           WHERE command_type IN (
               'NON_TAX_CREATE','NON_TAX_CONFIRM','NON_TAX_CORRECT',
               'NON_TAX_ATTACHMENT_LINK','NON_TAX_ATTACHMENT_UNLINK'
           ) LIMIT 1
       )
       OR EXISTS (
           SELECT 1 FROM institution_daily_employment_income_non_taxable_revisions
           WHERE revision_status_code IN ('REJECTED','SUPERSEDED') LIMIT 1
       ) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='비과세 Command 또는 Audit 자료가 있어 Down할 수 없습니다.';
    END IF;

    DROP TABLE institution_daily_employment_income_non_taxable_audits;
    ALTER TABLE institution_daily_employment_income_non_taxable_revisions
        DROP CONSTRAINT ck_daily_non_tax_revision_status,
        ADD CONSTRAINT ck_daily_non_tax_revision_status CHECK (
            revision_status_code IN ('DRAFT','CONFIRMED','CORRECTED','CANCELLED')
        );
    ALTER TABLE institution_daily_employment_income_commands
        DROP CONSTRAINT chk_daily_income_command_type,
        DROP CONSTRAINT fk_daily_income_command_target_revision,
        DROP CONSTRAINT fk_daily_income_command_result_revision;
    ALTER TABLE institution_daily_employment_income_commands
        DROP INDEX idx_daily_income_command_target_revision,
        DROP INDEX idx_daily_income_command_result_revision,
        DROP COLUMN target_revision_id,
        DROP COLUMN result_revision_id,
        ADD CONSTRAINT chk_daily_income_command_type CHECK (
            command_type IN ('SAVE','UPDATE','DELETE','SUBMIT','WITHDRAW','RETRY_CLOSURE')
        );
END$$
CALL migrate_20260827_21_down()$$
DROP PROCEDURE migrate_20260827_21_down$$
DELIMITER ;
