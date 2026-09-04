DELIMITER $$
CREATE PROCEDURE migrate_20260831_09_down()
BEGIN
    IF EXISTS(SELECT 1 FROM institution_daily_employment_income_closures LIMIT 1)
       OR EXISTS(SELECT 1 FROM institution_daily_employment_income_accounting_links LIMIT 1)
       OR EXISTS(SELECT 1 FROM ledger_evidence_daily_employment_income WHERE daily_employment_income_group_id IS NOT NULL LIMIT 1)
       OR EXISTS(SELECT 1 FROM user_approval_requests WHERE document_type='DAILY_EMPLOYMENT_INCOME' LIMIT 1) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='일용근로소득 Closure 업무자료가 있어 Down할 수 없습니다.';
    END IF;
    IF EXISTS(SELECT 1 FROM institution_daily_employment_incomes WHERE status_code='WITHDRAWN' LIMIT 1) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='회수 상태 문서가 있어 Down할 수 없습니다.';
    END IF;
    IF EXISTS(SELECT 1 FROM ledger_evidence_daily_employment_income WHERE work_team_id IS NULL LIMIT 1) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='작업팀 없는 일용근로소득 Evidence가 있어 Down할 수 없습니다.';
    END IF;
    DELETE FROM user_approval_template_steps WHERE template_id='20260831-0900-4000-8000-000000000001';
    DELETE FROM user_approval_templates WHERE id='20260831-0900-4000-8000-000000000001';
    ALTER TABLE institution_daily_employment_incomes DROP CONSTRAINT ck_daily_income_status;
    ALTER TABLE institution_daily_employment_incomes ADD CONSTRAINT ck_daily_income_status CHECK(status_code IN('DRAFT','PENDING','APPROVED','REJECTED'));
    DROP TABLE institution_daily_employment_income_accounting_links;
    DROP TABLE institution_daily_employment_income_closures;
    ALTER TABLE ledger_evidence_daily_employment_income
        DROP CONSTRAINT ck_daily_evidence_business_hash,DROP CONSTRAINT ck_daily_evidence_snapshot,DROP CONSTRAINT ck_daily_evidence_source_hash,
        DROP CONSTRAINT fk_daily_evidence_revision,DROP CONSTRAINT fk_daily_evidence_group,DROP INDEX idx_daily_evidence_revision,
        DROP INDEX idx_daily_evidence_group,DROP INDEX uq_daily_income_evidence_business_key,DROP COLUMN business_key_hash,
        DROP COLUMN snapshot_json,DROP COLUMN source_hash,DROP COLUMN calculation_revision_id,DROP COLUMN daily_employment_income_group_id,
        MODIFY COLUMN work_team_id VARCHAR(36) NOT NULL COMMENT '작업팀';
END$$
CALL migrate_20260831_09_down()$$
DROP PROCEDURE migrate_20260831_09_down$$
DELIMITER ;
