DELIMITER $$
DROP PROCEDURE IF EXISTS migrate_20260903_24_align_income_evidence_originals$$
CREATE PROCEDURE migrate_20260903_24_align_income_evidence_originals()
procedure_body: BEGIN
    IF EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='ledger_evidence_business_income' AND COLUMN_NAME='supply_amount'
    ) AND EXISTS (SELECT 1 FROM ledger_evidence_business_income) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='사업소득 Evidence 운영 데이터가 있어 세금계산서형 컬럼을 자동 제거할 수 없습니다.';
    END IF;

    IF EXISTS (
        SELECT 1
        FROM ledger_evidence_salary_report evidence
        JOIN institution_regular_employment_income_items item
          ON item.id=evidence.regular_employment_income_item_id
         AND item.regular_employment_income_id=evidence.source_regular_employment_income_id
        JOIN institution_regular_employment_incomes header ON header.id=evidence.source_regular_employment_income_id
        WHERE ROUND(evidence.raw_gross_payment_amount,2)<>ROUND(item.gross_amount,2)
           OR ROUND(evidence.raw_worker_deduction_amount,2)<>ROUND(item.deduction_amount,2)
           OR ROUND(evidence.raw_net_payment_amount,2)<>ROUND(item.net_payment_amount,2)
           OR evidence.raw_income_year_month<>header.income_year_month
           OR NOT (evidence.raw_withholding_date<=>header.withholding_date)
    ) OR EXISTS (
        SELECT 1 FROM ledger_evidence_salary_report evidence
        WHERE NOT EXISTS (
            SELECT 1 FROM institution_regular_employment_income_line_items line
            WHERE line.regular_employment_income_item_id=evidence.regular_employment_income_item_id
        )
    ) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='기존 급여 Evidence와 승인 원천이 달라 불변 Snapshot을 백필할 수 없습니다.';
    END IF;

    IF NOT EXISTS (
        SELECT 1 FROM information_schema.TABLES
        WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='ledger_evidence_salary_report_lines'
    ) THEN
        CREATE TABLE ledger_evidence_salary_report_lines (
            id VARCHAR(36) NOT NULL COMMENT '급여 Evidence 계산 Line 고유번호',
            evidence_id VARCHAR(36) NOT NULL COMMENT '급여 Evidence 고유번호',
            source_line_id VARCHAR(36) NOT NULL COMMENT '승인 당시 급여 원본 Line 고유번호',
            sort_no INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '승인 당시 표시순서',
            raw_item_type_code VARCHAR(30) NOT NULL COMMENT '승인 당시 급여항목 유형',
            raw_item_code VARCHAR(60) NOT NULL COMMENT '승인 당시 급여항목 코드',
            raw_item_name VARCHAR(100) NOT NULL COMMENT '승인 당시 급여항목명',
            raw_application_status_code VARCHAR(30) NULL COMMENT '승인 당시 적용상태',
            raw_calculation_basis_amount DECIMAL(18,2) NULL COMMENT '승인 당시 계산기초금액',
            raw_calculation_rate DECIMAL(18,10) NULL COMMENT '승인 당시 적용요율',
            raw_calculation_before_rounding DECIMAL(24,10) NULL COMMENT '승인 당시 끝수처리 전 금액',
            raw_calculated_amount DECIMAL(18,2) NULL COMMENT '승인 당시 자동계산액',
            raw_adjustment_amount DECIMAL(18,2) NULL COMMENT '승인 당시 조정액',
            raw_final_amount DECIMAL(18,2) NOT NULL COMMENT '승인 당시 확정금액',
            raw_rounding_method_code VARCHAR(30) NULL COMMENT '승인 당시 끝수처리 방식',
            raw_rounding_unit DECIMAL(18,4) NULL COMMENT '승인 당시 끝수처리 단위',
            raw_statutory_standard_id VARCHAR(36) NULL COMMENT '승인 당시 적용 법정기준',
            source_hash CHAR(64) NOT NULL COMMENT '승인 원본 Line SHA-256',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '등록일시',
            created_by VARCHAR(100) NOT NULL COMMENT '등록자',
            PRIMARY KEY (id),
            UNIQUE KEY uk_salary_evidence_line_source (evidence_id,source_line_id),
            KEY ix_salary_evidence_line_order (evidence_id,sort_no),
            CONSTRAINT fk_salary_evidence_line_evidence FOREIGN KEY (evidence_id)
                REFERENCES ledger_evidence_salary_report(id) ON DELETE RESTRICT ON UPDATE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='최종승인 급여 Evidence 계산 원본 Line';
    END IF;

    INSERT INTO ledger_evidence_salary_report_lines (
        id,evidence_id,source_line_id,sort_no,raw_item_type_code,raw_item_code,raw_item_name,
        raw_application_status_code,raw_calculation_basis_amount,raw_calculation_rate,
        raw_calculation_before_rounding,raw_calculated_amount,raw_adjustment_amount,raw_final_amount,
        raw_rounding_method_code,raw_rounding_unit,raw_statutory_standard_id,source_hash,created_by
    )
    SELECT UUID(),evidence.id,line.id,line.sort_no,line.item_type_code,line.item_code,line.item_name_snapshot,
           line.application_status_code,line.calculation_basis_amount,line.calculation_rate,
           line.calculation_before_rounding,line.calculated_amount,line.adjustment_amount,line.final_amount,
           line.rounding_method_code,line.rounding_unit,line.statutory_standard_id,
           SHA2(CONCAT_WS('|',line.id,line.sort_no,line.item_type_code,line.item_code,line.item_name_snapshot,
               COALESCE(line.application_status_code,''),COALESCE(line.calculation_basis_amount,''),
               COALESCE(line.calculation_rate,''),COALESCE(line.calculation_before_rounding,''),
               COALESCE(line.calculated_amount,''),COALESCE(line.adjustment_amount,''),line.final_amount,
               COALESCE(line.rounding_method_code,''),COALESCE(line.rounding_unit,''),COALESCE(line.statutory_standard_id,'')),256),
           'SYSTEM:MIGRATION'
    FROM ledger_evidence_salary_report evidence
    JOIN institution_regular_employment_income_line_items line
      ON line.regular_employment_income_item_id=evidence.regular_employment_income_item_id
    LEFT JOIN ledger_evidence_salary_report_lines existing
      ON existing.evidence_id=evidence.id AND existing.source_line_id=line.id
    WHERE existing.id IS NULL;

    UPDATE ledger_evidence_salary_report evidence
    SET evidence.snapshot_json=JSON_OBJECT(
            'source_regular_employment_income_id',evidence.source_regular_employment_income_id,
            'regular_employment_income_item_id',evidence.regular_employment_income_item_id,
            'approval_request_id',evidence.approval_request_id,
            'raw_income_year_month',evidence.raw_income_year_month,
            'raw_withholding_date',evidence.raw_withholding_date,
            'raw_gross_payment_amount',evidence.raw_gross_payment_amount,
            'raw_worker_deduction_amount',evidence.raw_worker_deduction_amount,
            'raw_net_payment_amount',evidence.raw_net_payment_amount,
            'raw_employer_burden_amount',evidence.raw_employer_burden_amount,
            'calculation_version',evidence.calculation_version,
            'line_items',COALESCE((
                SELECT JSON_ARRAYAGG(JSON_OBJECT(
                    'source_line_id',line.source_line_id,'sort_no',line.sort_no,
                    'item_type_code',line.raw_item_type_code,'item_code',line.raw_item_code,
                    'item_name',line.raw_item_name,'application_status_code',line.raw_application_status_code,
                    'calculation_basis_amount',line.raw_calculation_basis_amount,'calculation_rate',line.raw_calculation_rate,
                    'calculation_before_rounding',line.raw_calculation_before_rounding,
                    'calculated_amount',line.raw_calculated_amount,'adjustment_amount',line.raw_adjustment_amount,
                    'final_amount',line.raw_final_amount,'rounding_method_code',line.raw_rounding_method_code,
                    'rounding_unit',line.raw_rounding_unit,'statutory_standard_id',line.raw_statutory_standard_id
                ) ORDER BY line.sort_no,line.source_line_id)
                FROM ledger_evidence_salary_report_lines line WHERE line.evidence_id=evidence.id
            ),JSON_ARRAY())
        ),
        evidence.snapshot_version=1,
        evidence.snapshot_origin_code='MIGRATION_RECONSTRUCTED',
        evidence.evidence_status='COMPLETED',
        evidence.source_type='INTERNAL_APPROVAL'
    WHERE evidence.snapshot_json IS NULL OR evidence.source_hash IS NULL OR evidence.evidence_status<>'COMPLETED';

    UPDATE ledger_evidence_salary_report
    SET source_hash=SHA2(snapshot_json,256),reconstruction_hash=SHA2(snapshot_json,256)
    WHERE snapshot_json IS NOT NULL;

    UPDATE ledger_evidence_daily_employment_income
    SET source_type='INTERNAL_APPROVAL',evidence_status_code='COMPLETED',evidence_status='COMPLETED'
    WHERE source_type<>'INTERNAL_APPROVAL' OR evidence_status_code<>'COMPLETED' OR evidence_status<>'COMPLETED';

    IF EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='ledger_evidence_business_income' AND COLUMN_NAME='supply_amount'
    ) THEN
        ALTER TABLE ledger_evidence_business_income
            DROP COLUMN income_date,
            DROP COLUMN provider_name,
            DROP COLUMN provider_reg_no,
            DROP COLUMN supply_amount,
            DROP COLUMN vat_amount,
            DROP COLUMN service_amount,
            DROP COLUMN total_amount;
    END IF;
END$$
CALL migrate_20260903_24_align_income_evidence_originals()$$
DROP PROCEDURE migrate_20260903_24_align_income_evidence_originals$$
DELIMITER ;
