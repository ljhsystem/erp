DROP PROCEDURE IF EXISTS `rollback_20260825_05_regular_income_generation`;
DELIMITER $$
CREATE PROCEDURE `rollback_20260825_05_regular_income_generation`()
BEGIN
    IF EXISTS (SELECT 1 FROM institution_regular_employment_income_accounting_links)
       OR EXISTS (SELECT 1 FROM institution_regular_income_accounting_schedules)
       OR EXISTS (SELECT 1 FROM ledger_transaction_items WHERE regular_employment_income_line_item_id IS NOT NULL OR statutory_standard_revision_id IS NOT NULL OR calculation_basis_id IS NOT NULL)
       OR EXISTS (SELECT 1 FROM ledger_transaction_settlements WHERE regular_employment_income_line_item_id IS NOT NULL OR statutory_standard_revision_id IS NOT NULL OR calculation_basis_id IS NOT NULL) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='회계생성 또는 신규 원천 데이터가 존재하여 통합 Down을 차단했습니다.';
    END IF;
    IF (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='institution_regular_employment_income_accounting_links' AND COLUMN_NAME IN ('generation_role','aggregation_key','approval_request_id','attribution_month','recognition_date','payload_hash'))<>6
       OR (SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='institution_regular_income_accounting_schedules')<>1 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Migration 05 완성 스키마가 아니므로 통합 Down을 차단했습니다.';
    END IF;

    DROP TABLE institution_regular_income_accounting_schedules;
    ALTER TABLE institution_regular_employment_income_accounting_links
        DROP FOREIGN KEY fk_regular_income_accounting_approval_request,
        DROP CONSTRAINT chk_regular_income_accounting_role,
        DROP CONSTRAINT chk_regular_income_accounting_month,
        DROP CONSTRAINT chk_regular_income_accounting_payload_hash,
        DROP CONSTRAINT chk_regular_income_accounting_role_fields,
        DROP INDEX uk_regular_income_accounting_identity,
        DROP INDEX idx_regular_income_accounting_approval,
        DROP INDEX idx_regular_income_accounting_evidence,
        DROP INDEX idx_regular_income_accounting_attribution,
        DROP COLUMN payload_hash, DROP COLUMN recognition_date, DROP COLUMN attribution_month,
        DROP COLUMN approval_request_id, DROP COLUMN aggregation_key, DROP COLUMN generation_role,
        MODIFY regular_employment_income_item_id varchar(36) NOT NULL,
        MODIFY transaction_id varchar(36) NOT NULL,
        MODIFY payment_schedule_id varchar(36) NOT NULL;
    ALTER TABLE institution_regular_employment_income_accounting_links
        ADD UNIQUE KEY uk_regular_income_accounting_detail (regular_employment_income_item_id);
    ALTER TABLE institution_regular_employment_income_accounting_links
        DROP INDEX idx_regular_income_accounting_detail;

    ALTER TABLE ledger_transaction_settlements
        DROP FOREIGN KEY fk_transaction_settlement_regular_income_line,
        DROP FOREIGN KEY fk_transaction_settlement_statutory_standard,
        DROP FOREIGN KEY fk_transaction_settlement_calculation_basis,
        DROP INDEX idx_transaction_settlement_regular_income_line,
        DROP INDEX idx_transaction_settlement_statutory_standard,
        DROP INDEX idx_transaction_settlement_calculation_basis,
        DROP COLUMN calculation_basis_id, DROP COLUMN statutory_standard_revision_id,
        DROP COLUMN regular_employment_income_line_item_id;
    ALTER TABLE ledger_transaction_items
        DROP FOREIGN KEY fk_transaction_item_regular_income_line,
        DROP FOREIGN KEY fk_transaction_item_statutory_standard,
        DROP FOREIGN KEY fk_transaction_item_calculation_basis,
        DROP INDEX idx_transaction_item_regular_income_line,
        DROP INDEX idx_transaction_item_statutory_standard,
        DROP INDEX idx_transaction_item_calculation_basis,
        DROP COLUMN calculation_basis_id, DROP COLUMN statutory_standard_revision_id,
        DROP COLUMN regular_employment_income_line_item_id;
END$$
DELIMITER ;
CALL rollback_20260825_05_regular_income_generation();
DROP PROCEDURE rollback_20260825_05_regular_income_generation;
