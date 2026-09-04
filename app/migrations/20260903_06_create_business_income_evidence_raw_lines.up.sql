CREATE TABLE ledger_evidence_business_income_raw_lines (
    id VARCHAR(36) NOT NULL,
    evidence_id VARCHAR(36) NOT NULL,
    source_calculation_line_id VARCHAR(36) NOT NULL,
    calculation_revision_id VARCHAR(36) NOT NULL,
    raw_line_type VARCHAR(30) NOT NULL,
    raw_line_code VARCHAR(50) NOT NULL,
    raw_line_name VARCHAR(100) NOT NULL,
    raw_applicability_status VARCHAR(40) NOT NULL,
    raw_calculation_base_amount DECIMAL(18,6) NOT NULL,
    raw_applied_rate DECIMAL(18,10) NULL,
    raw_amount_before_rounding DECIMAL(18,6) NOT NULL,
    raw_rounding_method VARCHAR(30) NULL,
    raw_rounding_unit DECIMAL(18,6) NULL,
    raw_calculated_amount DECIMAL(18,2) NOT NULL,
    raw_statutory_standard_revision_id VARCHAR(36) NULL,
    raw_sort_no INT UNSIGNED NOT NULL,
    source_hash CHAR(64) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_by VARCHAR(100) NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uk_business_income_raw_line_source (evidence_id,source_calculation_line_id),
    KEY idx_business_income_raw_line_order (evidence_id,raw_sort_no,id),
    CONSTRAINT fk_business_income_raw_line_evidence FOREIGN KEY (evidence_id) REFERENCES ledger_evidence_business_income(id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_business_income_raw_line_source FOREIGN KEY (source_calculation_line_id) REFERENCES institution_business_income_calculation_lines(id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_business_income_raw_line_revision FOREIGN KEY (calculation_revision_id) REFERENCES institution_business_income_calculation_revisions(id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT chk_business_income_raw_line_hash CHECK (source_hash REGEXP '^[0-9a-f]{64}$')
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='승인 사업소득 계산 Raw Line';

ALTER TABLE institution_business_income_artifact_links
    ADD CONSTRAINT fk_business_income_artifact_evidence FOREIGN KEY (evidence_id) REFERENCES ledger_evidence_business_income(id) ON DELETE RESTRICT ON UPDATE CASCADE,
    ADD CONSTRAINT fk_business_income_artifact_transaction FOREIGN KEY (transaction_id) REFERENCES ledger_transactions(id) ON DELETE RESTRICT ON UPDATE CASCADE;
