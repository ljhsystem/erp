-- 출장·재택 근무지 데이터가 존재하면 Baseline 유형 축소를 중단한다.

DELIMITER $$
CREATE PROCEDURE `sp_rollback_hr_common_ssot_baseline`()
BEGIN
    IF EXISTS(
        SELECT 1
        FROM user_employee_workplace_assignments
        WHERE workplace_type_code IN ('BUSINESS_TRIP','REMOTE')
    ) THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Business trip or remote workplace rows exist; down migration is unsafe';
    END IF;

    ALTER TABLE user_employee_workplace_assignments
        DROP CONSTRAINT `chk_employee_workplace_type`,
        ADD CONSTRAINT `chk_employee_workplace_type`
            CHECK (`workplace_type_code` IN ('HEAD_OFFICE','PROJECT','OTHER'));

    DELETE FROM system_codes
    WHERE code_group = 'EMPLOYEE_WORKPLACE_TYPE'
      AND code IN ('BUSINESS_TRIP','REMOTE')
      AND created_by = 'SYSTEM:PERSONNEL_BASELINE';
END$$
DELIMITER ;

CALL `sp_rollback_hr_common_ssot_baseline`();
DROP PROCEDURE `sp_rollback_hr_common_ssot_baseline`;
