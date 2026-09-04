DELIMITER $$
CREATE PROCEDURE migrate_20260827_16_down()
BEGIN
    IF EXISTS (SELECT 1 FROM institution_daily_employment_income_reconciliations LIMIT 1)
       OR EXISTS (SELECT 1 FROM institution_daily_employment_income_reconciliation_checks LIMIT 1)
       OR EXISTS (SELECT 1 FROM institution_daily_employment_income_accounting_links LIMIT 1)
       OR EXISTS (
           SELECT 1 FROM institution_daily_employment_income_closures
           WHERE calculation_revision_id IS NOT NULL OR reconciliation_id IS NOT NULL LIMIT 1
       )
       OR EXISTS (
           SELECT 1 FROM institution_daily_employment_income_items
           WHERE calculation_revision_id IS NOT NULL OR calculation_status_code<>'DRAFT' LIMIT 1
       )
       OR EXISTS (
           SELECT 1 FROM institution_daily_employment_incomes
           WHERE calculation_revision_id IS NOT NULL OR calculation_status_code<>'DRAFT' LIMIT 1
       ) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='대사·Closure·계산 Snapshot 자료가 있어 Down할 수 없습니다.';
    END IF;

    ALTER TABLE institution_daily_employment_income_accounting_links
        DROP CONSTRAINT chk_daily_income_accounting_business_hash,
        DROP CONSTRAINT chk_daily_income_accounting_role,
        DROP CONSTRAINT fk_daily_income_accounting_worker,
        DROP CONSTRAINT fk_daily_income_accounting_result,
        DROP CONSTRAINT fk_daily_income_accounting_calculation,
        DROP CONSTRAINT fk_daily_income_accounting_item;

    ALTER TABLE institution_daily_employment_income_accounting_links
        DROP INDEX uq_daily_income_accounting_business,
        DROP INDEX idx_daily_income_accounting_document,
        DROP INDEX idx_daily_income_accounting_calculation,
        DROP INDEX idx_daily_income_accounting_worker,
        DROP INDEX idx_daily_income_accounting_voucher,
        DROP COLUMN voucher_id,
        DROP COLUMN worker_client_id,
        DROP COLUMN calculation_result_id,
        DROP COLUMN calculation_revision_id,
        DROP COLUMN business_key_hash,
        MODIFY COLUMN daily_employment_income_item_id VARCHAR(36) NOT NULL,
        ADD UNIQUE KEY uk_daily_income_accounting_role
            (daily_employment_income_id,daily_employment_income_item_id,generation_role),
        ADD CONSTRAINT fk_daily_income_accounting_item FOREIGN KEY (daily_employment_income_item_id)
            REFERENCES institution_daily_employment_income_items(id) ON DELETE RESTRICT ON UPDATE CASCADE,
        ADD CONSTRAINT chk_daily_income_accounting_role CHECK (
            generation_role IN ('DAILY_INCOME_EVIDENCE','WORKER_PAYMENT')
        ),
        ADD CONSTRAINT chk_daily_income_accounting_role_fields CHECK (
            (generation_role='DAILY_INCOME_EVIDENCE' AND transaction_id IS NULL)
            OR generation_role='WORKER_PAYMENT'
        );

    ALTER TABLE institution_daily_employment_income_closures
        DROP CONSTRAINT fk_daily_closure_reconciliation,
        DROP CONSTRAINT fk_daily_closure_calculation,
        DROP INDEX idx_daily_closure_calculation,
        DROP COLUMN reconciliation_id,
        DROP COLUMN calculation_revision_id;

    ALTER TABLE institution_daily_employment_incomes
        DROP CONSTRAINT ck_daily_header_calculation_status,
        DROP CONSTRAINT fk_daily_header_calculation_revision,
        DROP INDEX idx_daily_header_calculation,
        DROP COLUMN calculation_revision_id,
        DROP COLUMN calculation_policy_version,
        DROP COLUMN calculation_status_code,
        DROP COLUMN total_employee_contribution_amount,
        DROP COLUMN total_local_income_tax_amount,
        DROP COLUMN total_income_tax_amount,
        DROP COLUMN total_non_taxable_income_amount,
        DROP COLUMN total_taxable_income_amount,
        DROP COLUMN group_count;

    ALTER TABLE institution_daily_employment_income_items
        DROP CONSTRAINT ck_daily_item_calculation_status,
        DROP CONSTRAINT fk_daily_item_calculation_revision,
        DROP INDEX idx_daily_item_calculation,
        DROP COLUMN calculation_revision_id,
        DROP COLUMN calculation_policy_version,
        DROP COLUMN calculation_status_code,
        DROP COLUMN net_payment_amount,
        DROP COLUMN employer_contribution_amount,
        DROP COLUMN employee_contribution_amount,
        DROP COLUMN withholding_amount,
        DROP COLUMN gross_payment_amount,
        DROP COLUMN non_taxable_income_amount,
        DROP COLUMN taxable_income_amount,
        DROP COLUMN adjustment_amount,
        DROP COLUMN non_taxable_adjustment_amount,
        DROP COLUMN taxable_adjustment_amount,
        DROP COLUMN base_payment_amount;

    DROP TABLE institution_daily_employment_income_reconciliation_checks;
    DROP TABLE institution_daily_employment_income_reconciliations;
END$$
CALL migrate_20260827_16_down()$$
DROP PROCEDURE migrate_20260827_16_down$$
DELIMITER ;
