DELIMITER $$
CREATE PROCEDURE migrate_20260831_04_down()
BEGIN
    IF (SELECT COUNT(*) FROM system_codes
        WHERE code_group='STATUTORY_STANDARD_TYPE'
          AND code IN('NATIONAL_PENSION','HEALTH_INSURANCE','LONG_TERM_CARE','EMPLOYMENT_INSURANCE','INDUSTRIAL_ACCIDENT')
          AND JSON_CONTAINS_PATH(extra_data,'all','$.field_sets.eligibility','$.component_templates')=1) <> 5 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='보험별 입력 템플릿 Down 기준선이 다릅니다.';
    END IF;
    UPDATE system_codes
       SET extra_data=JSON_REMOVE(extra_data,'$.field_sets','$.component_templates')
     WHERE code_group='STATUTORY_STANDARD_TYPE'
       AND code IN('NATIONAL_PENSION','HEALTH_INSURANCE','LONG_TERM_CARE','EMPLOYMENT_INSURANCE','INDUSTRIAL_ACCIDENT');
    IF ROW_COUNT() <> 5 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='보험별 입력 템플릿 5건 제거에 실패했습니다.';
    END IF;
END$$
CALL migrate_20260831_04_down()$$
DROP PROCEDURE migrate_20260831_04_down$$
DELIMITER ;
