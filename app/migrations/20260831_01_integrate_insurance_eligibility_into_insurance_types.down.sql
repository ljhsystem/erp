DELIMITER $$
CREATE PROCEDURE migrate_20260831_01_down()
BEGIN
    IF EXISTS (
        SELECT 1 FROM institution_daily_employment_income_calculation_results
        WHERE eligibility_revision_id LIKE '20260831-10%'
    ) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='통합 가입자격 Revision을 참조하는 계산결과가 있어 Down할 수 없습니다.';
    END IF;

    UPDATE system_statutory_standard_sources source_row
    JOIN system_statutory_standards integrated ON integrated.id=source_row.standard_id
       SET source_row.standard_id=CONCAT('20260829-03',RIGHT(SUBSTRING_INDEX(SUBSTRING_INDEX(integrated.id,'-',2),'-',-1),2),'-4000-8000-',RIGHT(integrated.id,12))
     WHERE integrated.id LIKE '20260831-10%' AND integrated.policy_component_code='ELIGIBILITY';
    DELETE FROM system_statutory_standards
     WHERE id LIKE '20260831-10%' AND policy_component_code='ELIGIBILITY';

    UPDATE system_statutory_standards
       SET policy_component_code=NULL, employment_type_code=NULL, work_scope_code=NULL,
           additional_dimension_data=NULL, additional_dimension_key=NULL
     WHERE standard_type_code IN('NATIONAL_PENSION','HEALTH_INSURANCE','LONG_TERM_CARE','EMPLOYMENT_INSURANCE','INDUSTRIAL_ACCIDENT');

    ALTER TABLE system_statutory_standards
      DROP CONSTRAINT ck_statutory_standard_additional_dimension_key,
      DROP CONSTRAINT ck_statutory_standard_additional_dimension_json,
      DROP CONSTRAINT ck_statutory_standard_work_scope,
      DROP CONSTRAINT ck_statutory_standard_employment_type,
      DROP CONSTRAINT ck_statutory_standard_policy_component,
      DROP INDEX idx_statutory_standard_component_resolve,
      DROP COLUMN work_scope_code,
      DROP COLUMN employment_type_code,
      DROP COLUMN policy_component_code,
      DROP COLUMN additional_dimension_key,
      DROP COLUMN additional_dimension_data;
END$$
CALL migrate_20260831_01_down()$$
DROP PROCEDURE migrate_20260831_01_down$$
DELIMITER ;
