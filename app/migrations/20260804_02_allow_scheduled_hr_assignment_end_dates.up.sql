-- 계획·활성 배치의 예정 종료일을 허용하고 현재 활성 중복키를 상태 기준으로 정규화한다.

ALTER TABLE `user_employee_job_assignments`
    DROP CONSTRAINT `chk_employee_job_assignment_result`,
    ADD CONSTRAINT `chk_employee_job_assignment_result`
        CHECK (`status_code` IN ('PLANNED','ACTIVE','CANCELLED') OR (`status_code` = 'ENDED' AND `end_date` IS NOT NULL));

ALTER TABLE `user_employee_project_assignments`
    DROP INDEX `uk_employee_project_active_primary`,
    DROP COLUMN `active_primary_employee_id`,
    ADD COLUMN `active_primary_employee_id` VARCHAR(36)
        AS (CASE WHEN `status_code` = 'ACTIVE' AND `is_primary` = 1 THEN `employee_id` ELSE NULL END)
        PERSISTENT COMMENT '현재 주배치 중복 방지키' AFTER `end_personnel_action_target_id`,
    ADD UNIQUE KEY `uk_employee_project_active_primary` (`active_primary_employee_id`),
    DROP CONSTRAINT `chk_employee_project_assignment_result`,
    ADD CONSTRAINT `chk_employee_project_assignment_result`
        CHECK (`status_code` IN ('PLANNED','ACTIVE','CANCELLED') OR (`status_code` = 'ENDED' AND `end_date` IS NOT NULL));

ALTER TABLE `user_employee_workplace_assignments`
    DROP INDEX `uk_employee_workplace_active`,
    DROP COLUMN `active_employee_id`,
    ADD COLUMN `active_employee_id` VARCHAR(36)
        AS (CASE WHEN `status_code` = 'ACTIVE' THEN `employee_id` ELSE NULL END)
        PERSISTENT COMMENT '현재 근무지 중복 방지키' AFTER `end_personnel_action_target_id`,
    ADD UNIQUE KEY `uk_employee_workplace_active` (`active_employee_id`),
    DROP CONSTRAINT `chk_employee_workplace_result`,
    ADD CONSTRAINT `chk_employee_workplace_result`
        CHECK (`status_code` IN ('PLANNED','ACTIVE','CANCELLED') OR (`status_code` = 'ENDED' AND `end_date` IS NOT NULL));
