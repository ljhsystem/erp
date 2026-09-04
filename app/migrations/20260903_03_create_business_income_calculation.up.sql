CREATE TABLE institution_business_income_calculation_revisions (
    id VARCHAR(36) NOT NULL,
    business_income_id VARCHAR(36) NOT NULL,
    revision_no INT UNSIGNED NOT NULL,
    revision_status VARCHAR(30) NOT NULL DEFAULT 'DRAFT',
    calculation_date DATE NOT NULL,
    policy_status VARCHAR(40) NOT NULL,
    source_hash CHAR(64) NOT NULL,
    calculated_at DATETIME NULL,
    calculated_by VARCHAR(100) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_by VARCHAR(100) NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uk_business_income_revision (business_income_id,revision_no),
    KEY idx_business_income_revision_status (business_income_id,revision_status),
    CONSTRAINT fk_business_income_revision_header FOREIGN KEY (business_income_id) REFERENCES institution_business_incomes(id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT chk_business_income_revision_hash CHECK (source_hash REGEXP '^[0-9a-f]{64}$')
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='사업소득 계산 Revision';

CREATE TABLE institution_business_income_calculation_lines (
    id VARCHAR(36) NOT NULL,
    calculation_revision_id VARCHAR(36) NOT NULL,
    business_income_item_id VARCHAR(36) NOT NULL,
    line_type VARCHAR(30) NOT NULL,
    line_code VARCHAR(50) NOT NULL,
    line_name VARCHAR(100) NOT NULL,
    calculation_base_amount DECIMAL(18,6) NOT NULL,
    applied_rate DECIMAL(18,10) NULL,
    amount_before_rounding DECIMAL(18,6) NOT NULL,
    rounding_method VARCHAR(30) NULL,
    rounding_unit DECIMAL(18,6) NULL,
    calculated_amount DECIMAL(18,2) NOT NULL,
    statutory_standard_revision_id VARCHAR(36) NULL,
    applicability_status VARCHAR(40) NOT NULL,
    sort_no INT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_by VARCHAR(100) NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uk_business_income_calc_line (calculation_revision_id,business_income_item_id,line_code,sort_no),
    KEY idx_business_income_calc_line_item (business_income_item_id,sort_no,id),
    CONSTRAINT fk_business_income_calc_line_revision FOREIGN KEY (calculation_revision_id) REFERENCES institution_business_income_calculation_revisions(id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_business_income_calc_line_item FOREIGN KEY (business_income_item_id) REFERENCES institution_business_income_items(id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_business_income_calc_line_standard FOREIGN KEY (statutory_standard_revision_id) REFERENCES system_statutory_standards(id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT chk_business_income_calc_line_code CHECK (line_code IN ('GROSS_PAYMENT','INCOME_TAX','LOCAL_INCOME_TAX','OTHER_DEDUCTION'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='사업소득 계산 Raw 원본 Line';

ALTER TABLE institution_business_incomes
    ADD CONSTRAINT fk_business_income_current_revision FOREIGN KEY (current_calculation_revision_id) REFERENCES institution_business_income_calculation_revisions(id) ON DELETE RESTRICT ON UPDATE CASCADE;
