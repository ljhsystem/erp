DROP PROCEDURE IF EXISTS migrate_20260902_42_salary_evidence_backfill;
DELIMITER $$
CREATE PROCEDURE migrate_20260902_42_salary_evidence_backfill()
BEGIN
    IF (SELECT COUNT(*) FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='ledger_evidence_salary_report'
          AND COLUMN_NAME IN ('source_document_id','source_item_id','business_key_hash','work_team_id',
                              'raw_gross_payment_amount','raw_worker_deduction_amount'))<>6 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='상용급여 Evidence 핵심 SSOT 컬럼 6개가 필요합니다.';
    END IF;
    IF EXISTS (
        SELECT 1 FROM ledger_evidence_salary_report evidence
        LEFT JOIN institution_regular_employment_incomes header ON header.id=evidence.source_regular_employment_income_id
        LEFT JOIN institution_regular_employment_income_items item
          ON item.id=evidence.regular_employment_income_item_id
         AND item.regular_employment_income_id=evidence.source_regular_employment_income_id
        WHERE header.id IS NULL OR item.id IS NULL
           OR ROUND(evidence.raw_gross_amount,2)<>ROUND(item.gross_amount,2)
           OR ROUND(evidence.raw_deduction_amount,2)<>ROUND(item.deduction_amount,2)
    ) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='상용급여 Evidence 원천 연결 또는 금액 대사가 불일치합니다.';
    END IF;
    IF EXISTS (
        SELECT 1 FROM ledger_evidence_salary_report
        WHERE (source_document_id IS NULL)<>(source_item_id IS NULL)
           OR (source_document_id IS NULL)<>(business_key_hash IS NULL)
           OR (source_document_id IS NULL)<>(raw_gross_payment_amount IS NULL)
           OR (source_document_id IS NULL)<>(raw_worker_deduction_amount IS NULL)
    ) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='상용급여 Evidence 핵심 SSOT 값이 부분 backfill된 상태입니다.';
    END IF;
    UPDATE ledger_evidence_salary_report evidence
       SET source_document_id=source_regular_employment_income_id,
           source_item_id=regular_employment_income_item_id,
           business_key_hash=SHA2(CONCAT('PAYROLL_REPORT|',source_regular_employment_income_id,'|',regular_employment_income_item_id),256),
           work_team_id=team_id,
           raw_gross_payment_amount=raw_gross_amount,
           raw_worker_deduction_amount=raw_deduction_amount
     WHERE source_document_id IS NULL;
    IF EXISTS (
        SELECT 1 FROM ledger_evidence_salary_report
        WHERE source_document_id<>source_regular_employment_income_id
           OR source_item_id<>regular_employment_income_item_id
           OR business_key_hash NOT REGEXP '^[0-9a-f]{64}$'
           OR NOT (work_team_id<=>team_id)
           OR ROUND(raw_gross_payment_amount,2)<>ROUND(raw_gross_amount,2)
           OR ROUND(raw_worker_deduction_amount,2)<>ROUND(raw_deduction_amount,2)
    ) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='상용급여 Evidence SSOT backfill 후 대사가 실패했습니다.';
    END IF;
END$$
DELIMITER ;
CALL migrate_20260902_42_salary_evidence_backfill();
DROP PROCEDURE migrate_20260902_42_salary_evidence_backfill;
