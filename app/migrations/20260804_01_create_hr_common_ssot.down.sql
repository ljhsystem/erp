-- 인사·노무 공통 SSOT 운영 데이터가 생긴 뒤에는 자동 롤백하지 않는다.

DELIMITER $$
CREATE PROCEDURE `sp_rollback_hr_common_ssot`()
BEGIN
    DECLARE business_row_count BIGINT DEFAULT 0;

    SELECT
        (SELECT COUNT(*) FROM institution_personnel_actions)
      + (SELECT COUNT(*) FROM user_jobs)
      + (SELECT COUNT(*) FROM user_employee_leave_periods)
      + (SELECT COUNT(*) FROM user_employee_job_assignments)
      + (SELECT COUNT(*) FROM user_employee_project_assignments)
      + (SELECT COUNT(*) FROM user_employee_workplace_assignments)
      + (SELECT COUNT(*) FROM user_employee_employment_status_histories WHERE created_by <> 'SYSTEM:PERSONNEL_MIGRATION')
    INTO business_row_count;

    IF business_row_count > 0 OR EXISTS(SELECT 1 FROM user_employees WHERE job_id IS NOT NULL) THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'HR common SSOT business data exists; down migration is unsafe';
    END IF;

    DELETE FROM user_employee_employment_status_histories
    WHERE created_by = 'SYSTEM:PERSONNEL_MIGRATION';

    ALTER TABLE user_employees
        DROP FOREIGN KEY `fk_user_employees_job`,
        DROP CONSTRAINT `chk_user_employees_employment_status`,
        DROP INDEX `idx_user_employees_job`,
        DROP INDEX `idx_user_employees_employment_status`,
        DROP COLUMN `job_id`,
        DROP COLUMN `employment_status`;

    DROP TABLE institution_personnel_action_changes;
    DROP TABLE user_employee_workplace_assignments;
    DROP TABLE user_employee_project_assignments;
    DROP TABLE user_employee_job_assignments;
    DROP TABLE user_employee_leave_periods;
    DROP TABLE user_employee_employment_status_histories;
    DROP TABLE institution_personnel_action_targets;
    DROP TABLE institution_personnel_actions;
    DROP TABLE user_jobs;

    DELETE FROM system_codes
    WHERE code_group IN (
        'PERSONNEL_ACTION_TYPE', 'PERSONNEL_ACTION_STATUS', 'EMPLOYMENT_STATUS',
        'EMPLOYEE_LEAVE_TYPE', 'EMPLOYEE_WORKPLACE_TYPE', 'EMPLOYEE_ASSIGNMENT_STATUS'
    ) AND created_by = 'SYSTEM:PERSONNEL_MIGRATION';
END$$
DELIMITER ;

CALL `sp_rollback_hr_common_ssot`();
DROP PROCEDURE `sp_rollback_hr_common_ssot`;
