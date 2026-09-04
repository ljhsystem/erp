DROP PROCEDURE IF EXISTS migrate_20260902_47_internal_evidence_table_settings;
DELIMITER $$
CREATE PROCEDURE migrate_20260902_47_internal_evidence_table_settings()
BEGIN
    IF EXISTS (
        SELECT 1 FROM system_user_settings
        WHERE setting_type='TABLE'
          AND page_key IN ('evidence-payroll','evidence-payroll-report','evidence-employee-expense-personal','evidence-daily-employment-income')
          AND JSON_VALID(settings_json)=0
    ) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='내부 승인형 Evidence TableSettings JSON이 유효하지 않습니다.';
    END IF;
    IF EXISTS (
        SELECT 1 FROM system_user_settings
        WHERE setting_type='TABLE'
          AND page_key IN ('evidence-payroll','evidence-payroll-report','evidence-employee-expense-personal','evidence-daily-employment-income')
          AND JSON_SEARCH(settings_json,'one','team_id') IS NOT NULL
          AND JSON_SEARCH(settings_json,'one','work_team_id') IS NOT NULL
    ) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='내부 승인형 Evidence TableSettings에 구·신규 작업팀 키가 동시에 존재합니다.';
    END IF;
    UPDATE system_user_settings
       SET settings_json=REPLACE(REPLACE(REPLACE(
           settings_json,
           '"raw_gross_amount"','"raw_gross_payment_amount"'),
           '"raw_deduction_amount"','"raw_worker_deduction_amount"'),
           '"team_id"','"work_team_id"')
     WHERE setting_type='TABLE'
       AND page_key IN ('evidence-payroll','evidence-payroll-report')
       AND JSON_VALID(settings_json)=1
       AND (JSON_SEARCH(settings_json,'one','raw_gross_amount') IS NOT NULL
         OR JSON_SEARCH(settings_json,'one','raw_deduction_amount') IS NOT NULL
         OR JSON_SEARCH(settings_json,'one','team_id') IS NOT NULL);
    UPDATE system_user_settings
       SET settings_json=REPLACE(settings_json,'"team_id"','"work_team_id"')
     WHERE setting_type='TABLE'
       AND page_key='evidence-employee-expense-personal'
       AND JSON_VALID(settings_json)=1
       AND JSON_SEARCH(settings_json,'one','team_id') IS NOT NULL;
    UPDATE system_user_settings
       SET settings_json=REPLACE(settings_json,'"team_id"','"work_team_id"')
     WHERE setting_type='TABLE'
       AND page_key='evidence-daily-employment-income'
       AND JSON_VALID(settings_json)=1
       AND JSON_SEARCH(settings_json,'one','team_id') IS NOT NULL;
END$$
DELIMITER ;
CALL migrate_20260902_47_internal_evidence_table_settings();
DROP PROCEDURE migrate_20260902_47_internal_evidence_table_settings;
