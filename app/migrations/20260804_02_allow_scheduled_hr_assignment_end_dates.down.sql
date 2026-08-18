-- 01 Migration의 초기 제약으로 되돌린다. 예정 종료일이 있는 계획·활성 행이 있으면 중단한다.

DELIMITER $$
CREATE PROCEDURE `sp_rollback_scheduled_hr_assignment_end_dates`()
BEGIN
    IF EXISTS(SELECT 1 FROM user_employee_job_assignments WHERE status_code IN ('PLANNED','ACTIVE') AND end_date IS NOT NULL)
       OR EXISTS(SELECT 1 FROM user_employee_project_assignments WHERE status_code IN ('PLANNED','ACTIVE') AND end_date IS NOT NULL)
       OR EXISTS(SELECT 1 FROM user_employee_workplace_assignments WHERE status_code IN ('PLANNED','ACTIVE') AND end_date IS NOT NULL) THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Scheduled assignment end dates exist; down migration is unsafe';
    END IF;

    ALTER TABLE user_employee_job_assignments
        DROP CONSTRAINT `chk_employee_job_assignment_result`,
        ADD CONSTRAINT `chk_employee_job_assignment_result`
            CHECK ((`status_code` IN ('PLANNED','ACTIVE') AND `end_date` IS NULL) OR (`status_code` = 'ENDED' AND `end_date` IS NOT NULL) OR `status_code` = 'CANCELLED');

    ALTER TABLE user_employee_project_assignments
        DROP INDEX `uk_employee_project_active_primary`,
        DROP COLUMN `active_primary_employee_id`,
        ADD COLUMN `active_primary_employee_id` VARCHAR(36)
            AS (CASE WHEN `status_code` = 'ACTIVE' AND `end_date` IS NULL AND `is_primary` = 1 THEN `employee_id` ELSE NULL END)
            PERSISTENT COMMENT '현재 주배치 중복 방지키' AFTER `end_personnel_action_target_id`,
        ADD UNIQUE KEY `uk_employee_project_active_primary` (`active_primary_employee_id`),
        DROP CONSTRAINT `chk_employee_project_assignment_result`,
        ADD CONSTRAINT `chk_employee_project_assignment_result`
            CHECK ((`status_code` IN ('PLANNED','ACTIVE') AND `end_date` IS NULL) OR (`status_code` = 'ENDED' AND `end_date` IS NOT NULL) OR `status_code` = 'CANCELLED');

    ALTER TABLE user_employee_workplace_assignments
        DROP INDEX `uk_employee_workplace_active`,
        DROP COLUMN `active_employee_id`,
        ADD COLUMN `active_employee_id` VARCHAR(36)
            AS (CASE WHEN `status_code` = 'ACTIVE' AND `end_date` IS NULL THEN `employee_id` ELSE NULL END)
            PERSISTENT COMMENT '현재 근무지 중복 방지키' AFTER `end_personnel_action_target_id`,
        ADD UNIQUE KEY `uk_employee_workplace_active` (`active_employee_id`),
        DROP CONSTRAINT `chk_employee_workplace_result`,
        ADD CONSTRAINT `chk_employee_workplace_result`
            CHECK ((`status_code` IN ('PLANNED','ACTIVE') AND `end_date` IS NULL) OR (`status_code` = 'ENDED' AND `end_date` IS NOT NULL) OR `status_code` = 'CANCELLED');
END$$
DELIMITER ;

CALL `sp_rollback_scheduled_hr_assignment_end_dates`();
DROP PROCEDURE `sp_rollback_scheduled_hr_assignment_end_dates`;
