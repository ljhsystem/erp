DROP PROCEDURE IF EXISTS create_daily_evidence_raw_lines;
DELIMITER $$
CREATE PROCEDURE create_daily_evidence_raw_lines()
procedure_body: BEGIN
    IF EXISTS (SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='ledger_evidence_daily_employment_income_lines') THEN
        IF (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='ledger_evidence_daily_employment_income_lines')=26
          AND (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='ledger_evidence_daily_employment_income_lines' AND COLUMN_NAME IN ('id','evidence_id','source_calculation_line_id','calculation_revision_id','line_type_code','raw_final_amount'))=6 THEN LEAVE procedure_body; END IF;
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='일용 Evidence Raw Line 테이블이 불완전합니다.';
    END IF;
    CREATE TABLE ledger_evidence_daily_employment_income_lines (
      id VARCHAR(36) NOT NULL,
      evidence_id VARCHAR(36) NOT NULL,
      sort_no INT NOT NULL,
      source_calculation_line_id VARCHAR(36) NOT NULL,
      calculation_revision_id VARCHAR(36) NOT NULL,
      line_type_code VARCHAR(30) NOT NULL,
      line_code VARCHAR(50) NOT NULL,
      line_name_snapshot VARCHAR(100) NOT NULL,
      burden_subject_code VARCHAR(20) NOT NULL,
      application_status_code VARCHAR(30) NULL,
      taxability_code VARCHAR(20) NULL,
      raw_calculation_basis_amount DECIMAL(18,6) NULL,
      raw_calculation_rate DECIMAL(18,10) NULL,
      raw_calculation_before_rounding DECIMAL(24,10) NULL,
      raw_calculated_amount DECIMAL(18,2) NULL,
      raw_adjustment_amount DECIMAL(18,2) NULL,
      raw_final_amount DECIMAL(18,2) NULL,
      rounding_method_code VARCHAR(30) NULL,
      rounding_unit DECIMAL(18,6) NULL,
      statutory_standard_id CHAR(36) NULL,
      coverage_id VARCHAR(36) NULL,
      social_insurance_workplace_id VARCHAR(36) NULL,
      created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
      created_by VARCHAR(100) NOT NULL,
      updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      updated_by VARCHAR(100) NOT NULL,
      PRIMARY KEY (id),
      UNIQUE KEY uq_daily_evidence_raw_line (evidence_id,source_calculation_line_id),
      KEY idx_daily_evidence_raw_line_revision (calculation_revision_id),
      KEY idx_daily_evidence_raw_line_code (line_type_code,line_code,application_status_code),
      KEY idx_daily_evidence_raw_line_standard (statutory_standard_id),
      KEY idx_daily_evidence_raw_line_coverage (coverage_id),
      KEY idx_daily_evidence_raw_line_workplace (social_insurance_workplace_id),
      CONSTRAINT fk_daily_evidence_raw_line_evidence FOREIGN KEY (evidence_id) REFERENCES ledger_evidence_daily_employment_income(id),
      CONSTRAINT fk_daily_evidence_raw_line_source FOREIGN KEY (source_calculation_line_id) REFERENCES institution_daily_employment_income_lines(id),
      CONSTRAINT fk_daily_evidence_raw_line_revision FOREIGN KEY (calculation_revision_id) REFERENCES institution_daily_employment_income_calculation_revisions(id),
      CONSTRAINT fk_daily_evidence_raw_line_standard FOREIGN KEY (statutory_standard_id) REFERENCES system_statutory_standards(id),
      CONSTRAINT fk_daily_evidence_raw_line_coverage FOREIGN KEY (coverage_id) REFERENCES institution_daily_worker_social_insurance_coverages(id),
      CONSTRAINT fk_daily_evidence_raw_line_workplace FOREIGN KEY (social_insurance_workplace_id) REFERENCES institution_social_insurance_workplaces(id),
      CONSTRAINT ck_daily_evidence_raw_line_type CHECK (line_type_code IN ('PAY','DEDUCTION','EMPLOYER_BURDEN')),
      CONSTRAINT ck_daily_evidence_raw_line_subject CHECK (burden_subject_code IN ('EMPLOYEE','EMPLOYER')),
      CONSTRAINT ck_daily_evidence_raw_line_application CHECK (application_status_code IS NULL OR application_status_code IN ('APPLICABLE','EXCLUDED','NOT_APPLICABLE')),
      CONSTRAINT ck_daily_evidence_raw_line_amounts CHECK ((raw_calculated_amount IS NULL OR raw_calculated_amount>=0) AND (raw_final_amount IS NULL OR raw_final_amount>=0))
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='승인 당시 일용근로소득 계산 Line 불변 Projection';
END$$
DELIMITER ;
CALL create_daily_evidence_raw_lines();
DROP PROCEDURE create_daily_evidence_raw_lines;
