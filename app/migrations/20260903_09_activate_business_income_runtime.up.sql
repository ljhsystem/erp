DELIMITER $$
CREATE PROCEDURE activate_business_income_runtime()
BEGIN
    IF (SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN (
        'system_client_tax_profiles','institution_business_incomes','institution_business_income_groups','institution_business_income_items',
        'institution_business_income_calculation_revisions','institution_business_income_calculation_lines','institution_business_income_commands',
        'institution_business_income_artifact_links','institution_business_income_closures','ledger_evidence_business_income','ledger_evidence_business_income_raw_lines'
    ))<>11 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='사업소득 P1 선행 Migration이 모두 적용되지 않았습니다.';
    END IF;
    IF NOT EXISTS(SELECT 1 FROM system_page_registry WHERE page_key='web.institution.income_data.business_income' AND is_active=1) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='사업소득 Page Registry가 준비되지 않았습니다.';
    END IF;
    IF NOT EXISTS(SELECT 1 FROM user_approval_templates WHERE document_type='BUSINESS_INCOME' AND is_active=1) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='사업소득 결재 Template이 준비되지 않았습니다.';
    END IF;
    IF NOT EXISTS(SELECT 1 FROM user_approval_template_steps step_row JOIN user_approval_templates template_row ON template_row.id=step_row.template_id WHERE template_row.document_type='BUSINESS_INCOME' AND template_row.is_active=1 AND step_row.is_active=1 AND step_row.step_type IN ('APPROVAL','FINAL_APPROVAL')) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='사업소득 결재 승인 단계가 준비되지 않았습니다.';
    END IF;
    IF NOT EXISTS(SELECT 1 FROM ledger_evidence_metadata WHERE import_type='BUSINESS_INCOME_REPORT' AND source_table='ledger_evidence_business_income' AND evidence_type='DATA' AND process_role='TRANSACTION_REPORT_SSOT' AND transaction_cardinality='SINGLE_TRANSACTION' AND deleted_at IS NULL) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='사업소득 Evidence Metadata가 준비되지 않았습니다.';
    END IF;
    IF @business_income_statutory_leaf_preflight_passed IS NULL
       OR @business_income_statutory_leaf_preflight_passed<>1 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='공용 법정기준 Resolver leaf Preflight가 필요합니다.';
    END IF;
END$$
DELIMITER ;
CALL activate_business_income_runtime();
DROP PROCEDURE activate_business_income_runtime;
