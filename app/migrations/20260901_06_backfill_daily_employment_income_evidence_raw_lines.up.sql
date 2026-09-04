DROP PROCEDURE IF EXISTS backfill_daily_evidence_raw_lines;
DELIMITER $$
CREATE PROCEDURE backfill_daily_evidence_raw_lines()
procedure_body: BEGIN
    IF (SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='ledger_evidence_daily_employment_income_lines')<>1 THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='일용 Evidence Raw Line 테이블이 준비되지 않았습니다.';
    END IF;
    IF (SELECT COUNT(*) FROM ledger_evidence_daily_employment_income_lines)=
       (SELECT COUNT(*) FROM ledger_evidence_daily_employment_income e JOIN institution_daily_employment_income_lines l ON l.daily_employment_income_item_id=e.daily_employment_income_item_id)
       AND NOT EXISTS (SELECT 1 FROM ledger_evidence_daily_employment_income e JOIN institution_daily_employment_income_lines l ON l.daily_employment_income_item_id=e.daily_employment_income_item_id WHERE NOT EXISTS
         (SELECT 1 FROM ledger_evidence_daily_employment_income_lines r WHERE r.evidence_id=e.id AND r.source_calculation_line_id=l.id AND r.calculation_revision_id=e.calculation_revision_id)) THEN LEAVE procedure_body; END IF;
    IF EXISTS (SELECT 1 FROM ledger_evidence_daily_employment_income_lines) THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='일용 Evidence Raw Line backfill이 이미 실행됐거나 부분 적용된 상태입니다.';
    END IF;
    IF EXISTS (SELECT 1 FROM ledger_evidence_daily_employment_income e
      LEFT JOIN institution_daily_employment_income_calculation_revisions r ON r.id=e.calculation_revision_id
      WHERE r.id IS NULL) THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='일용 Evidence 계산 Revision을 결정할 수 없습니다.';
    END IF;
    INSERT INTO ledger_evidence_daily_employment_income_lines (
      id,evidence_id,sort_no,source_calculation_line_id,calculation_revision_id,line_type_code,line_code,line_name_snapshot,
      burden_subject_code,application_status_code,taxability_code,raw_calculation_basis_amount,raw_calculation_rate,
      raw_calculation_before_rounding,raw_calculated_amount,raw_adjustment_amount,raw_final_amount,rounding_method_code,
      rounding_unit,statutory_standard_id,coverage_id,social_insurance_workplace_id,created_at,created_by,updated_at,updated_by)
    SELECT UUID(),e.id,l.sort_no,l.id,e.calculation_revision_id,l.line_type_code,l.line_code,l.line_name_snapshot,
      IF(l.line_type_code='EMPLOYER_BURDEN','EMPLOYER','EMPLOYEE'),l.application_status_code,l.taxability_code,
      l.calculation_basis_amount,l.calculation_rate,l.calculation_before_rounding,l.calculated_amount,l.adjustment_amount,
      l.final_amount,l.rounding_method_code,l.rounding_unit,l.statutory_standard_id,l.coverage_id,l.social_insurance_workplace_id,
      e.approved_at,e.approved_by,e.approved_at,e.approved_by
    FROM ledger_evidence_daily_employment_income e
    JOIN institution_daily_employment_income_lines l ON l.daily_employment_income_item_id=e.daily_employment_income_item_id
    ORDER BY e.id,l.sort_no,l.id;
    IF (SELECT COUNT(*) FROM ledger_evidence_daily_employment_income_lines) <>
       (SELECT COUNT(*) FROM ledger_evidence_daily_employment_income e JOIN institution_daily_employment_income_lines l ON l.daily_employment_income_item_id=e.daily_employment_income_item_id) THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='일용 Evidence Raw Line backfill 건수 대사에 실패했습니다.';
    END IF;
END$$
DELIMITER ;
CALL backfill_daily_evidence_raw_lines();
DROP PROCEDURE backfill_daily_evidence_raw_lines;
