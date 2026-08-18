-- 인사발령 화면·적용 Service에 필요한 승인된 최소 Baseline 보강.
-- action_date는 효력일, issued_date는 문서상 발령일이다.

DELIMITER $$
CREATE PROCEDURE `sp_preflight_personnel_action_runtime`()
BEGIN
    IF EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = 'institution_personnel_actions'
           AND COLUMN_NAME = 'issued_date'
    ) OR EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = 'institution_personnel_action_targets'
           AND COLUMN_NAME = 'applied_by'
    ) OR EXISTS (
        SELECT 1 FROM information_schema.TABLES
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME IN ('user_employee_department_assignments', 'user_employee_position_assignments')
    ) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Personnel action runtime Baseline objects already exist';
    END IF;

    IF EXISTS (SELECT 1 FROM institution_personnel_actions LIMIT 1) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Existing personnel actions require issued_date manual confirmation';
    END IF;

    IF EXISTS (
        SELECT 1
          FROM user_employees e
         WHERE COALESCE(
             (SELECT MIN(h.effective_date)
                FROM user_employee_employment_status_histories h
               WHERE h.employee_id = e.id),
             e.real_hire_date,
             e.doc_hire_date
         ) IS NULL
    ) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Employee Baseline start date is missing';
    END IF;
END$$
DELIMITER ;

CALL `sp_preflight_personnel_action_runtime`();
DROP PROCEDURE `sp_preflight_personnel_action_runtime`;

ALTER TABLE `institution_personnel_actions`
    ADD COLUMN `issued_date` DATE NOT NULL COMMENT '발령일' AFTER `action_type_code`,
    ADD INDEX `idx_personnel_actions_issued_date` (`issued_date`, `deleted_at`);

ALTER TABLE `institution_personnel_action_targets`
    ADD COLUMN `applied_by` VARCHAR(100) NULL COMMENT '적용처리자' AFTER `applied_at`;

CREATE TABLE `user_employee_department_assignments` (
    `id` VARCHAR(36) NOT NULL COMMENT '직원 부서 기간이력 ID(UUID)',
    `employee_id` VARCHAR(36) NOT NULL COMMENT '직원 ID',
    `department_id` VARCHAR(36) NOT NULL COMMENT '부서 ID',
    `effective_from` DATE NOT NULL COMMENT '유효시작일',
    `effective_to` DATE NULL COMMENT '유효종료일',
    `start_action_target_id` VARCHAR(36) NULL COMMENT '시작 인사발령 대상자',
    `end_action_target_id` VARCHAR(36) NULL COMMENT '종료 인사발령 대상자',
    `current_employee_id` VARCHAR(36) AS (CASE WHEN `effective_to` IS NULL THEN `employee_id` ELSE NULL END) PERSISTENT COMMENT '현재 부서 중복 방지키',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '생성일시',
    `created_by` VARCHAR(100) NOT NULL COMMENT '생성 Actor',
    `updated_at` DATETIME NULL COMMENT '수정일시',
    `updated_by` VARCHAR(100) NULL COMMENT '수정 Actor',
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_employee_department_start` (`employee_id`, `effective_from`),
    UNIQUE KEY `uk_employee_department_current` (`current_employee_id`),
    KEY `idx_employee_department_period` (`employee_id`, `effective_from`, `effective_to`),
    KEY `idx_employee_department_department` (`department_id`, `effective_from`, `effective_to`),
    KEY `idx_employee_department_start_action` (`start_action_target_id`),
    KEY `idx_employee_department_end_action` (`end_action_target_id`),
    CONSTRAINT `fk_employee_department_employee` FOREIGN KEY (`employee_id`) REFERENCES `user_employees` (`id`) ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT `fk_employee_department_department` FOREIGN KEY (`department_id`) REFERENCES `user_departments` (`id`) ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT `fk_employee_department_start_action` FOREIGN KEY (`start_action_target_id`) REFERENCES `institution_personnel_action_targets` (`id`) ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT `fk_employee_department_end_action` FOREIGN KEY (`end_action_target_id`) REFERENCES `institution_personnel_action_targets` (`id`) ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT `chk_employee_department_period` CHECK (`effective_to` IS NULL OR `effective_to` >= `effective_from`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='직원 부서 기간 이력 SSOT';

CREATE TABLE `user_employee_position_assignments` (
    `id` VARCHAR(36) NOT NULL COMMENT '직원 직위·직책 기간이력 ID(UUID)',
    `employee_id` VARCHAR(36) NOT NULL COMMENT '직원 ID',
    `position_id` VARCHAR(36) NOT NULL COMMENT '직위·직책 ID',
    `effective_from` DATE NOT NULL COMMENT '유효시작일',
    `effective_to` DATE NULL COMMENT '유효종료일',
    `start_action_target_id` VARCHAR(36) NULL COMMENT '시작 인사발령 대상자',
    `end_action_target_id` VARCHAR(36) NULL COMMENT '종료 인사발령 대상자',
    `current_employee_id` VARCHAR(36) AS (CASE WHEN `effective_to` IS NULL THEN `employee_id` ELSE NULL END) PERSISTENT COMMENT '현재 직위·직책 중복 방지키',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '생성일시',
    `created_by` VARCHAR(100) NOT NULL COMMENT '생성 Actor',
    `updated_at` DATETIME NULL COMMENT '수정일시',
    `updated_by` VARCHAR(100) NULL COMMENT '수정 Actor',
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_employee_position_start` (`employee_id`, `effective_from`),
    UNIQUE KEY `uk_employee_position_current` (`current_employee_id`),
    KEY `idx_employee_position_period` (`employee_id`, `effective_from`, `effective_to`),
    KEY `idx_employee_position_position` (`position_id`, `effective_from`, `effective_to`),
    KEY `idx_employee_position_start_action` (`start_action_target_id`),
    KEY `idx_employee_position_end_action` (`end_action_target_id`),
    CONSTRAINT `fk_employee_position_employee` FOREIGN KEY (`employee_id`) REFERENCES `user_employees` (`id`) ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT `fk_employee_position_position` FOREIGN KEY (`position_id`) REFERENCES `user_positions` (`id`) ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT `fk_employee_position_start_action` FOREIGN KEY (`start_action_target_id`) REFERENCES `institution_personnel_action_targets` (`id`) ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT `fk_employee_position_end_action` FOREIGN KEY (`end_action_target_id`) REFERENCES `institution_personnel_action_targets` (`id`) ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT `chk_employee_position_period` CHECK (`effective_to` IS NULL OR `effective_to` >= `effective_from`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='직원 직위·직책 기간 이력 SSOT';

INSERT INTO `user_employee_department_assignments`
    (`id`, `employee_id`, `department_id`, `effective_from`, `effective_to`, `created_at`, `created_by`)
SELECT UUID(), e.id, e.department_id,
       COALESCE((SELECT MIN(h.effective_date) FROM user_employee_employment_status_histories h WHERE h.employee_id = e.id), e.real_hire_date, e.doc_hire_date),
       CASE WHEN e.employment_status = 'RETIRED'
            THEN COALESCE(e.real_retire_date, e.doc_retire_date, (SELECT MAX(h.ended_date) FROM user_employee_employment_status_histories h WHERE h.employee_id = e.id))
            ELSE NULL END,
       CURRENT_TIMESTAMP, 'SYSTEM:PERSONNEL_RUNTIME_BASELINE'
  FROM user_employees e
 WHERE e.department_id IS NOT NULL;

INSERT INTO `user_employee_position_assignments`
    (`id`, `employee_id`, `position_id`, `effective_from`, `effective_to`, `created_at`, `created_by`)
SELECT UUID(), e.id, e.position_id,
       COALESCE((SELECT MIN(h.effective_date) FROM user_employee_employment_status_histories h WHERE h.employee_id = e.id), e.real_hire_date, e.doc_hire_date),
       CASE WHEN e.employment_status = 'RETIRED'
            THEN COALESCE(e.real_retire_date, e.doc_retire_date, (SELECT MAX(h.ended_date) FROM user_employee_employment_status_histories h WHERE h.employee_id = e.id))
            ELSE NULL END,
       CURRENT_TIMESTAMP, 'SYSTEM:PERSONNEL_RUNTIME_BASELINE'
  FROM user_employees e
 WHERE e.position_id IS NOT NULL;

DELIMITER $$
CREATE PROCEDURE `sp_verify_personnel_action_runtime`()
BEGIN
    IF EXISTS (SELECT 1 FROM user_employee_department_assignments WHERE effective_to < effective_from)
       OR EXISTS (SELECT 1 FROM user_employee_position_assignments WHERE effective_to < effective_from) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Invalid employee assignment period';
    END IF;
    IF EXISTS (
        SELECT 1 FROM user_employee_department_assignments a
        LEFT JOIN user_employees e ON e.id = a.employee_id
        LEFT JOIN user_departments d ON d.id = a.department_id
        WHERE e.id IS NULL OR d.id IS NULL
    ) OR EXISTS (
        SELECT 1 FROM user_employee_position_assignments a
        LEFT JOIN user_employees e ON e.id = a.employee_id
        LEFT JOIN user_positions p ON p.id = a.position_id
        WHERE e.id IS NULL OR p.id IS NULL
    ) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Orphan employee assignment exists';
    END IF;
END$$
DELIMITER ;

CALL `sp_verify_personnel_action_runtime`();
DROP PROCEDURE `sp_verify_personnel_action_runtime`;
