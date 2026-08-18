-- 인사·노무관리 공통 현재상태·기간이력과 인사발령 문서 SSOT를 생성한다.
-- MariaDB 10.11.11 기준. 실행 전 tools/apply_hr_common_ssot_migration.php preflight를 통과해야 한다.

DELIMITER $$
CREATE PROCEDURE `sp_preflight_hr_common_ssot`()
BEGIN
    DECLARE existing_object_count INT DEFAULT 0;
    DECLARE ambiguous_employee_count INT DEFAULT 0;

    SELECT COUNT(*) INTO existing_object_count
    FROM information_schema.TABLES
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME IN (
          'institution_personnel_actions', 'institution_personnel_action_targets',
          'institution_personnel_action_changes', 'user_jobs',
          'user_employee_employment_status_histories', 'user_employee_leave_periods',
          'user_employee_job_assignments', 'user_employee_project_assignments',
          'user_employee_workplace_assignments'
      );

    IF existing_object_count > 0
       OR EXISTS(
           SELECT 1 FROM information_schema.COLUMNS
           WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'user_employees'
             AND COLUMN_NAME IN ('employment_status', 'job_id')
       ) THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'HR common SSOT migration objects already exist';
    END IF;

    SELECT COUNT(*) INTO ambiguous_employee_count
    FROM user_employees e
    LEFT JOIN auth_users u ON u.id = e.user_id
    WHERE u.id IS NULL
       OR (e.real_hire_date IS NULL AND e.doc_hire_date IS NULL)
       OR (e.real_retire_date IS NOT NULL AND COALESCE(e.real_hire_date, e.doc_hire_date) IS NOT NULL
           AND e.real_retire_date < COALESCE(e.real_hire_date, e.doc_hire_date))
       OR (e.doc_retire_date IS NOT NULL AND COALESCE(e.doc_hire_date, e.real_hire_date) IS NOT NULL
           AND e.doc_retire_date < COALESCE(e.doc_hire_date, e.real_hire_date));

    IF ambiguous_employee_count > 0 THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Ambiguous employee rows exist; inspect migration preflight output';
    END IF;
END$$
DELIMITER ;

CALL `sp_preflight_hr_common_ssot`();
DROP PROCEDURE `sp_preflight_hr_common_ssot`;

SET @personnel_code_sort_base := (SELECT COALESCE(MAX(sort_no), 0) FROM system_codes);

INSERT INTO system_codes
    (id, sort_no, code_group, group_name, code, code_name, is_active, created_at, created_by)
SELECT UUID(), @personnel_code_sort_base + seed.ordinal_no,
       seed.code_group, seed.group_name, seed.code, seed.code_name,
       1, CURRENT_TIMESTAMP, 'SYSTEM:PERSONNEL_MIGRATION'
FROM (
    SELECT 1 ordinal_no, 'PERSONNEL_ACTION_STATUS' code_group, '인사발령상태' group_name, 'DRAFT' code, '작성중' code_name UNION ALL
    SELECT 2, 'PERSONNEL_ACTION_STATUS', '인사발령상태', 'APPROVAL_PENDING', '결재대기' UNION ALL
    SELECT 3, 'PERSONNEL_ACTION_STATUS', '인사발령상태', 'APPROVED', '승인' UNION ALL
    SELECT 4, 'PERSONNEL_ACTION_STATUS', '인사발령상태', 'APPLIED', '적용완료' UNION ALL
    SELECT 5, 'PERSONNEL_ACTION_STATUS', '인사발령상태', 'CANCELLED', '취소' UNION ALL
    SELECT 6, 'PERSONNEL_ACTION_TYPE', '인사발령유형', 'HIRE', '입사' UNION ALL
    SELECT 7, 'PERSONNEL_ACTION_TYPE', '인사발령유형', 'DEPARTMENT_TRANSFER', '부서이동' UNION ALL
    SELECT 8, 'PERSONNEL_ACTION_TYPE', '인사발령유형', 'POSITION_CHANGE', '직위변경' UNION ALL
    SELECT 9, 'PERSONNEL_ACTION_TYPE', '인사발령유형', 'PROMOTION', '승진' UNION ALL
    SELECT 10, 'PERSONNEL_ACTION_TYPE', '인사발령유형', 'JOB_CHANGE', '직무변경' UNION ALL
    SELECT 11, 'PERSONNEL_ACTION_TYPE', '인사발령유형', 'PROJECT_ASSIGN', '프로젝트배치' UNION ALL
    SELECT 12, 'PERSONNEL_ACTION_TYPE', '인사발령유형', 'PROJECT_RELEASE', '프로젝트해제' UNION ALL
    SELECT 13, 'PERSONNEL_ACTION_TYPE', '인사발령유형', 'WORKPLACE_CHANGE', '근무지변경' UNION ALL
    SELECT 14, 'PERSONNEL_ACTION_TYPE', '인사발령유형', 'TRANSFER', '전보' UNION ALL
    SELECT 15, 'PERSONNEL_ACTION_TYPE', '인사발령유형', 'LEAVE_OF_ABSENCE', '휴직' UNION ALL
    SELECT 16, 'PERSONNEL_ACTION_TYPE', '인사발령유형', 'REINSTATEMENT', '복직' UNION ALL
    SELECT 17, 'PERSONNEL_ACTION_TYPE', '인사발령유형', 'RETIREMENT', '퇴직' UNION ALL
    SELECT 18, 'PERSONNEL_ACTION_TYPE', '인사발령유형', 'OTHER', '기타' UNION ALL
    SELECT 19, 'EMPLOYMENT_STATUS', '재직상태', 'PENDING_HIRE', '입사예정' UNION ALL
    SELECT 20, 'EMPLOYMENT_STATUS', '재직상태', 'ACTIVE', '재직' UNION ALL
    SELECT 21, 'EMPLOYMENT_STATUS', '재직상태', 'ON_LEAVE', '휴직' UNION ALL
    SELECT 22, 'EMPLOYMENT_STATUS', '재직상태', 'RETIRED', '퇴직' UNION ALL
    SELECT 23, 'EMPLOYEE_LEAVE_TYPE', '휴직유형', 'PERSONAL', '개인사유휴직' UNION ALL
    SELECT 24, 'EMPLOYEE_LEAVE_TYPE', '휴직유형', 'MEDICAL', '질병휴직' UNION ALL
    SELECT 25, 'EMPLOYEE_LEAVE_TYPE', '휴직유형', 'CHILDCARE', '육아휴직' UNION ALL
    SELECT 26, 'EMPLOYEE_LEAVE_TYPE', '휴직유형', 'MATERNITY', '출산휴직' UNION ALL
    SELECT 27, 'EMPLOYEE_LEAVE_TYPE', '휴직유형', 'MILITARY', '병역휴직' UNION ALL
    SELECT 28, 'EMPLOYEE_LEAVE_TYPE', '휴직유형', 'OTHER', '기타' UNION ALL
    SELECT 29, 'EMPLOYEE_WORKPLACE_TYPE', '직원근무지유형', 'HEAD_OFFICE', '본사' UNION ALL
    SELECT 30, 'EMPLOYEE_WORKPLACE_TYPE', '직원근무지유형', 'PROJECT', '프로젝트현장' UNION ALL
    SELECT 31, 'EMPLOYEE_WORKPLACE_TYPE', '직원근무지유형', 'OTHER', '기타' UNION ALL
    SELECT 32, 'EMPLOYEE_ASSIGNMENT_STATUS', '직원배치상태', 'PLANNED', '예정' UNION ALL
    SELECT 33, 'EMPLOYEE_ASSIGNMENT_STATUS', '직원배치상태', 'ACTIVE', '활성' UNION ALL
    SELECT 34, 'EMPLOYEE_ASSIGNMENT_STATUS', '직원배치상태', 'ENDED', '종료' UNION ALL
    SELECT 35, 'EMPLOYEE_ASSIGNMENT_STATUS', '직원배치상태', 'CANCELLED', '취소'
) seed;

CREATE TABLE `user_jobs` (
    `id` VARCHAR(36) NOT NULL COMMENT '직무 ID(UUID)',
    `sort_no` INT NOT NULL COMMENT '정렬순서',
    `job_code` VARCHAR(50) NOT NULL COMMENT '직무코드',
    `job_name` VARCHAR(100) NOT NULL COMMENT '직무명',
    `description` VARCHAR(500) NULL COMMENT '직무설명',
    `effective_from` DATE NULL COMMENT '유효시작일',
    `effective_to` DATE NULL COMMENT '유효종료일',
    `is_active` TINYINT(1) NOT NULL DEFAULT 1 COMMENT '사용여부',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '생성일시',
    `created_by` VARCHAR(100) NOT NULL COMMENT '생성 Actor',
    `updated_at` DATETIME NULL COMMENT '수정일시',
    `updated_by` VARCHAR(100) NULL COMMENT '수정 Actor',
    `deleted_at` DATETIME NULL COMMENT '삭제일시',
    `deleted_by` VARCHAR(100) NULL COMMENT '삭제 Actor',
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_user_jobs_sort_no` (`sort_no`),
    UNIQUE KEY `uk_user_jobs_code` (`job_code`),
    KEY `idx_user_jobs_active_period` (`is_active`, `effective_from`, `effective_to`, `deleted_at`),
    CONSTRAINT `chk_user_jobs_active` CHECK (`is_active` IN (0, 1)),
    CONSTRAINT `chk_user_jobs_period` CHECK (`effective_to` IS NULL OR `effective_from` IS NULL OR `effective_to` >= `effective_from`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='인사·노무 공용 직무 마스터';

CREATE TABLE `institution_personnel_actions` (
    `id` VARCHAR(36) NOT NULL COMMENT '인사발령 ID(UUID)',
    `sort_no` INT NOT NULL COMMENT '정렬순서',
    `action_no` VARCHAR(50) NOT NULL COMMENT '발령번호',
    `action_name` VARCHAR(150) NOT NULL COMMENT '발령명',
    `action_type_code` VARCHAR(50) NOT NULL COMMENT '발령유형 공용코드',
    `action_date` DATE NOT NULL COMMENT '발령 적용기준일',
    `action_reason` TEXT NULL COMMENT '발령사유',
    `business_status` VARCHAR(30) NOT NULL DEFAULT 'DRAFT' COMMENT '발령 업무상태',
    `current_approval_request_id` VARCHAR(36) NULL COMMENT '현재 전자결재 요청',
    `original_action_id` VARCHAR(36) NULL COMMENT '정정·취소 원본 발령',
    `correction_kind` VARCHAR(20) NULL COMMENT '원본처리구분: CORRECTION/CANCELLATION',
    `approved_at` DATETIME NULL COMMENT '최종 승인일시',
    `applied_at` DATETIME NULL COMMENT '현재상태·기간이력 적용완료일시',
    `cancelled_at` DATETIME NULL COMMENT '취소일시',
    `cancelled_reason` VARCHAR(500) NULL COMMENT '취소사유',
    `note` VARCHAR(500) NULL COMMENT '비고',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '생성일시',
    `created_by` VARCHAR(100) NOT NULL COMMENT '작성 Actor',
    `updated_at` DATETIME NULL COMMENT '수정일시',
    `updated_by` VARCHAR(100) NULL COMMENT '수정 Actor',
    `deleted_at` DATETIME NULL COMMENT '삭제일시',
    `deleted_by` VARCHAR(100) NULL COMMENT '삭제 Actor',
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_personnel_actions_sort_no` (`sort_no`),
    UNIQUE KEY `uk_personnel_actions_action_no` (`action_no`),
    KEY `idx_personnel_actions_status_date` (`business_status`, `action_date`, `deleted_at`),
    KEY `idx_personnel_actions_type_date` (`action_type_code`, `action_date`),
    KEY `idx_personnel_actions_approval` (`current_approval_request_id`),
    KEY `idx_personnel_actions_original` (`original_action_id`),
    CONSTRAINT `fk_personnel_actions_approval` FOREIGN KEY (`current_approval_request_id`) REFERENCES `user_approval_requests` (`id`) ON UPDATE RESTRICT ON DELETE SET NULL,
    CONSTRAINT `fk_personnel_actions_original` FOREIGN KEY (`original_action_id`) REFERENCES `institution_personnel_actions` (`id`) ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT `chk_personnel_actions_status` CHECK (`business_status` IN ('DRAFT','APPROVAL_PENDING','APPROVED','APPLIED','CANCELLED')),
    CONSTRAINT `chk_personnel_actions_correction` CHECK ((`original_action_id` IS NULL AND `correction_kind` IS NULL) OR (`original_action_id` IS NOT NULL AND `correction_kind` IN ('CORRECTION','CANCELLATION'))),
    CONSTRAINT `chk_personnel_actions_self_ref` CHECK (`original_action_id` IS NULL OR `original_action_id` <> `id`),
    CONSTRAINT `chk_personnel_actions_dates` CHECK (
        (`business_status` IN ('DRAFT','APPROVAL_PENDING') AND `approved_at` IS NULL AND `applied_at` IS NULL AND `cancelled_at` IS NULL)
        OR (`business_status` = 'APPROVED' AND `approved_at` IS NOT NULL AND `applied_at` IS NULL AND `cancelled_at` IS NULL)
        OR (`business_status` = 'APPLIED' AND `approved_at` IS NOT NULL AND `applied_at` IS NOT NULL AND `cancelled_at` IS NULL)
        OR (`business_status` = 'CANCELLED' AND `cancelled_at` IS NOT NULL AND `cancelled_reason` IS NOT NULL)
    )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='인사발령 문서 헤더 및 적용 생명주기 SSOT';

CREATE TABLE `institution_personnel_action_targets` (
    `id` VARCHAR(36) NOT NULL COMMENT '발령 대상자 ID(UUID)',
    `personnel_action_id` VARCHAR(36) NOT NULL COMMENT '인사발령 ID',
    `employee_id` VARCHAR(36) NOT NULL COMMENT '대상 직원 ID',
    `sort_no` INT NOT NULL COMMENT '문서 내 대상자 순번',
    `individual_reason` VARCHAR(500) NULL COMMENT '개별 발령사유',
    `application_status` VARCHAR(20) NOT NULL DEFAULT 'PENDING' COMMENT '적용상태: PENDING/APPLIED/FAILED/CANCELLED',
    `applied_at` DATETIME NULL COMMENT '대상자 적용일시',
    `application_error` VARCHAR(1000) NULL COMMENT '적용 오류내용',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '생성일시',
    `created_by` VARCHAR(100) NOT NULL COMMENT '생성 Actor',
    `updated_at` DATETIME NULL COMMENT '수정일시',
    `updated_by` VARCHAR(100) NULL COMMENT '수정 Actor',
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_personnel_action_target_employee` (`personnel_action_id`, `employee_id`),
    UNIQUE KEY `uk_personnel_action_target_sort` (`personnel_action_id`, `sort_no`),
    KEY `idx_personnel_action_target_employee_status` (`employee_id`, `application_status`),
    KEY `idx_personnel_action_target_action_status` (`personnel_action_id`, `application_status`),
    CONSTRAINT `fk_personnel_action_target_action` FOREIGN KEY (`personnel_action_id`) REFERENCES `institution_personnel_actions` (`id`) ON UPDATE RESTRICT ON DELETE CASCADE,
    CONSTRAINT `fk_personnel_action_target_employee` FOREIGN KEY (`employee_id`) REFERENCES `user_employees` (`id`) ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT `chk_personnel_action_target_status` CHECK (`application_status` IN ('PENDING','APPLIED','FAILED','CANCELLED')),
    CONSTRAINT `chk_personnel_action_target_result` CHECK (
        (`application_status` = 'PENDING' AND `applied_at` IS NULL AND `application_error` IS NULL)
        OR (`application_status` = 'APPLIED' AND `applied_at` IS NOT NULL AND `application_error` IS NULL)
        OR (`application_status` = 'FAILED' AND `applied_at` IS NULL AND `application_error` IS NOT NULL)
        OR (`application_status` = 'CANCELLED' AND `applied_at` IS NULL)
    )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='인사발령 대상 직원과 적용결과';

CREATE TABLE `user_employee_employment_status_histories` (
    `id` VARCHAR(36) NOT NULL COMMENT '재직상태 이력 ID(UUID)',
    `employee_id` VARCHAR(36) NOT NULL COMMENT '직원 ID',
    `status_code` VARCHAR(30) NOT NULL COMMENT '재직상태 공용코드',
    `effective_date` DATE NOT NULL COMMENT '상태 적용일',
    `ended_date` DATE NULL COMMENT '상태 종료일',
    `reason` VARCHAR(500) NULL COMMENT '상태 변경사유',
    `source_personnel_action_target_id` VARCHAR(36) NULL COMMENT '원본 인사발령 대상자',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '생성일시',
    `created_by` VARCHAR(100) NOT NULL COMMENT '생성 Actor',
    `updated_at` DATETIME NULL COMMENT '수정일시',
    `updated_by` VARCHAR(100) NULL COMMENT '수정 Actor',
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_employee_status_history_point` (`employee_id`, `effective_date`, `status_code`),
    KEY `idx_employee_status_history_period` (`employee_id`, `effective_date`, `ended_date`),
    KEY `idx_employee_status_history_status` (`status_code`, `effective_date`),
    KEY `idx_employee_status_history_source` (`source_personnel_action_target_id`),
    CONSTRAINT `fk_employee_status_history_employee` FOREIGN KEY (`employee_id`) REFERENCES `user_employees` (`id`) ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT `fk_employee_status_history_source` FOREIGN KEY (`source_personnel_action_target_id`) REFERENCES `institution_personnel_action_targets` (`id`) ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT `chk_employee_status_history_code` CHECK (`status_code` IN ('PENDING_HIRE','ACTIVE','ON_LEAVE','RETIRED')),
    CONSTRAINT `chk_employee_status_history_period` CHECK (`ended_date` IS NULL OR `ended_date` >= `effective_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='직원 재직상태 기간 이력 SSOT';

CREATE TABLE `user_employee_leave_periods` (
    `id` VARCHAR(36) NOT NULL COMMENT '휴직기간 ID(UUID)',
    `employee_id` VARCHAR(36) NOT NULL COMMENT '직원 ID',
    `leave_type_code` VARCHAR(30) NOT NULL COMMENT '휴직유형 공용코드',
    `start_date` DATE NOT NULL COMMENT '휴직 시작일',
    `planned_end_date` DATE NULL COMMENT '예정 종료일',
    `actual_end_date` DATE NULL COMMENT '실제 종료일',
    `status_code` VARCHAR(30) NOT NULL COMMENT '기간상태 공용코드',
    `reason` VARCHAR(500) NULL COMMENT '휴직사유',
    `leave_personnel_action_target_id` VARCHAR(36) NULL COMMENT '휴직 원본 발령 대상자',
    `return_personnel_action_target_id` VARCHAR(36) NULL COMMENT '복직 원본 발령 대상자',
    `active_employee_id` VARCHAR(36) AS (CASE WHEN `status_code` = 'ACTIVE' AND `actual_end_date` IS NULL THEN `employee_id` ELSE NULL END) PERSISTENT COMMENT '활성 휴직 직원 중복 방지키',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '생성일시',
    `created_by` VARCHAR(100) NOT NULL COMMENT '생성 Actor',
    `updated_at` DATETIME NULL COMMENT '수정일시',
    `updated_by` VARCHAR(100) NULL COMMENT '수정 Actor',
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_employee_leave_period_start` (`employee_id`, `start_date`),
    UNIQUE KEY `uk_employee_leave_active` (`active_employee_id`),
    KEY `idx_employee_leave_period` (`employee_id`, `start_date`, `actual_end_date`),
    KEY `idx_employee_leave_status` (`status_code`, `start_date`),
    KEY `idx_employee_leave_origin` (`leave_personnel_action_target_id`),
    KEY `idx_employee_leave_return` (`return_personnel_action_target_id`),
    CONSTRAINT `fk_employee_leave_employee` FOREIGN KEY (`employee_id`) REFERENCES `user_employees` (`id`) ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT `fk_employee_leave_origin` FOREIGN KEY (`leave_personnel_action_target_id`) REFERENCES `institution_personnel_action_targets` (`id`) ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT `fk_employee_leave_return` FOREIGN KEY (`return_personnel_action_target_id`) REFERENCES `institution_personnel_action_targets` (`id`) ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT `chk_employee_leave_status` CHECK (`status_code` IN ('PLANNED','ACTIVE','ENDED','CANCELLED')),
    CONSTRAINT `chk_employee_leave_period` CHECK ((`planned_end_date` IS NULL OR `planned_end_date` >= `start_date`) AND (`actual_end_date` IS NULL OR `actual_end_date` >= `start_date`)),
    CONSTRAINT `chk_employee_leave_result` CHECK ((`status_code` IN ('PLANNED','ACTIVE') AND `actual_end_date` IS NULL) OR (`status_code` = 'ENDED' AND `actual_end_date` IS NOT NULL) OR `status_code` = 'CANCELLED')
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='휴가와 분리된 직원 휴직·복직 기간 SSOT';

CREATE TABLE `user_employee_job_assignments` (
    `id` VARCHAR(36) NOT NULL COMMENT '직무배치 ID(UUID)',
    `employee_id` VARCHAR(36) NOT NULL COMMENT '직원 ID',
    `job_id` VARCHAR(36) NOT NULL COMMENT '직무 ID',
    `start_date` DATE NOT NULL COMMENT '직무 시작일',
    `end_date` DATE NULL COMMENT '직무 종료일',
    `status_code` VARCHAR(30) NOT NULL COMMENT '배치상태 공용코드',
    `assignment_personnel_action_target_id` VARCHAR(36) NULL COMMENT '배치 원본 발령 대상자',
    `end_personnel_action_target_id` VARCHAR(36) NULL COMMENT '종료 원본 발령 대상자',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '생성일시',
    `created_by` VARCHAR(100) NOT NULL COMMENT '생성 Actor',
    `updated_at` DATETIME NULL COMMENT '수정일시',
    `updated_by` VARCHAR(100) NULL COMMENT '수정 Actor',
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_employee_job_assignment_start` (`employee_id`, `start_date`),
    KEY `idx_employee_job_assignment_period` (`employee_id`, `start_date`, `end_date`),
    KEY `idx_employee_job_assignment_job` (`job_id`, `start_date`, `end_date`),
    KEY `idx_employee_job_assignment_status` (`status_code`, `start_date`),
    KEY `idx_employee_job_assignment_origin` (`assignment_personnel_action_target_id`),
    KEY `idx_employee_job_assignment_end_origin` (`end_personnel_action_target_id`),
    CONSTRAINT `fk_employee_job_assignment_employee` FOREIGN KEY (`employee_id`) REFERENCES `user_employees` (`id`) ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT `fk_employee_job_assignment_job` FOREIGN KEY (`job_id`) REFERENCES `user_jobs` (`id`) ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT `fk_employee_job_assignment_origin` FOREIGN KEY (`assignment_personnel_action_target_id`) REFERENCES `institution_personnel_action_targets` (`id`) ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT `fk_employee_job_assignment_end_origin` FOREIGN KEY (`end_personnel_action_target_id`) REFERENCES `institution_personnel_action_targets` (`id`) ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT `chk_employee_job_assignment_status` CHECK (`status_code` IN ('PLANNED','ACTIVE','ENDED','CANCELLED')),
    CONSTRAINT `chk_employee_job_assignment_period` CHECK (`end_date` IS NULL OR `end_date` >= `start_date`),
    CONSTRAINT `chk_employee_job_assignment_result` CHECK ((`status_code` IN ('PLANNED','ACTIVE') AND `end_date` IS NULL) OR (`status_code` = 'ENDED' AND `end_date` IS NOT NULL) OR `status_code` = 'CANCELLED')
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='직원 직무 기간배치 이력 SSOT';

CREATE TABLE `user_employee_project_assignments` (
    `id` VARCHAR(36) NOT NULL COMMENT '프로젝트배치 ID(UUID)',
    `employee_id` VARCHAR(36) NOT NULL COMMENT '직원 ID',
    `project_id` VARCHAR(36) NOT NULL COMMENT '프로젝트 ID',
    `job_id` VARCHAR(36) NULL COMMENT '배치 직무 ID',
    `assignment_role` VARCHAR(100) NULL COMMENT '프로젝트 배치 역할',
    `start_date` DATE NOT NULL COMMENT '배치 시작일',
    `end_date` DATE NULL COMMENT '배치 종료일',
    `is_primary` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '주배치 여부',
    `status_code` VARCHAR(30) NOT NULL COMMENT '배치상태 공용코드',
    `assignment_personnel_action_target_id` VARCHAR(36) NULL COMMENT '배치 원본 발령 대상자',
    `end_personnel_action_target_id` VARCHAR(36) NULL COMMENT '종료 원본 발령 대상자',
    `active_primary_employee_id` VARCHAR(36) AS (CASE WHEN `status_code` = 'ACTIVE' AND `end_date` IS NULL AND `is_primary` = 1 THEN `employee_id` ELSE NULL END) PERSISTENT COMMENT '현재 주배치 중복 방지키',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '생성일시',
    `created_by` VARCHAR(100) NOT NULL COMMENT '생성 Actor',
    `updated_at` DATETIME NULL COMMENT '수정일시',
    `updated_by` VARCHAR(100) NULL COMMENT '수정 Actor',
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_employee_project_assignment_start` (`employee_id`, `project_id`, `start_date`),
    UNIQUE KEY `uk_employee_project_active_primary` (`active_primary_employee_id`),
    KEY `idx_employee_project_assignment_period` (`employee_id`, `start_date`, `end_date`),
    KEY `idx_employee_project_assignment_project` (`project_id`, `start_date`, `end_date`),
    KEY `idx_employee_project_assignment_job` (`job_id`),
    KEY `idx_employee_project_assignment_status` (`status_code`, `start_date`),
    KEY `idx_employee_project_assignment_origin` (`assignment_personnel_action_target_id`),
    KEY `idx_employee_project_assignment_end_origin` (`end_personnel_action_target_id`),
    CONSTRAINT `fk_employee_project_assignment_employee` FOREIGN KEY (`employee_id`) REFERENCES `user_employees` (`id`) ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT `fk_employee_project_assignment_project` FOREIGN KEY (`project_id`) REFERENCES `system_projects` (`id`) ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT `fk_employee_project_assignment_job` FOREIGN KEY (`job_id`) REFERENCES `user_jobs` (`id`) ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT `fk_employee_project_assignment_origin` FOREIGN KEY (`assignment_personnel_action_target_id`) REFERENCES `institution_personnel_action_targets` (`id`) ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT `fk_employee_project_assignment_end_origin` FOREIGN KEY (`end_personnel_action_target_id`) REFERENCES `institution_personnel_action_targets` (`id`) ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT `chk_employee_project_assignment_primary` CHECK (`is_primary` IN (0, 1)),
    CONSTRAINT `chk_employee_project_assignment_status` CHECK (`status_code` IN ('PLANNED','ACTIVE','ENDED','CANCELLED')),
    CONSTRAINT `chk_employee_project_assignment_period` CHECK (`end_date` IS NULL OR `end_date` >= `start_date`),
    CONSTRAINT `chk_employee_project_assignment_result` CHECK ((`status_code` IN ('PLANNED','ACTIVE') AND `end_date` IS NULL) OR (`status_code` = 'ENDED' AND `end_date` IS NOT NULL) OR `status_code` = 'CANCELLED')
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='직원 다중 프로젝트 기간배치 SSOT';

CREATE TABLE `user_employee_workplace_assignments` (
    `id` VARCHAR(36) NOT NULL COMMENT '근무지배치 ID(UUID)',
    `employee_id` VARCHAR(36) NOT NULL COMMENT '직원 ID',
    `workplace_type_code` VARCHAR(30) NOT NULL COMMENT '근무지유형 공용코드',
    `project_id` VARCHAR(36) NULL COMMENT '프로젝트 현장 ID',
    `workplace_name_snapshot` VARCHAR(150) NULL COMMENT '기타 장소명 또는 현장명 스냅샷',
    `workplace_address_snapshot` VARCHAR(500) NULL COMMENT '근무지 주소 스냅샷',
    `start_date` DATE NOT NULL COMMENT '근무지 시작일',
    `end_date` DATE NULL COMMENT '근무지 종료일',
    `status_code` VARCHAR(30) NOT NULL COMMENT '배치상태 공용코드',
    `assignment_personnel_action_target_id` VARCHAR(36) NULL COMMENT '배치 원본 발령 대상자',
    `end_personnel_action_target_id` VARCHAR(36) NULL COMMENT '종료 원본 발령 대상자',
    `active_employee_id` VARCHAR(36) AS (CASE WHEN `status_code` = 'ACTIVE' AND `end_date` IS NULL THEN `employee_id` ELSE NULL END) PERSISTENT COMMENT '현재 근무지 중복 방지키',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '생성일시',
    `created_by` VARCHAR(100) NOT NULL COMMENT '생성 Actor',
    `updated_at` DATETIME NULL COMMENT '수정일시',
    `updated_by` VARCHAR(100) NULL COMMENT '수정 Actor',
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_employee_workplace_assignment_start` (`employee_id`, `start_date`),
    UNIQUE KEY `uk_employee_workplace_active` (`active_employee_id`),
    KEY `idx_employee_workplace_period` (`employee_id`, `start_date`, `end_date`),
    KEY `idx_employee_workplace_project` (`project_id`, `start_date`, `end_date`),
    KEY `idx_employee_workplace_status` (`status_code`, `start_date`),
    KEY `idx_employee_workplace_origin` (`assignment_personnel_action_target_id`),
    KEY `idx_employee_workplace_end_origin` (`end_personnel_action_target_id`),
    CONSTRAINT `fk_employee_workplace_employee` FOREIGN KEY (`employee_id`) REFERENCES `user_employees` (`id`) ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT `fk_employee_workplace_project` FOREIGN KEY (`project_id`) REFERENCES `system_projects` (`id`) ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT `fk_employee_workplace_origin` FOREIGN KEY (`assignment_personnel_action_target_id`) REFERENCES `institution_personnel_action_targets` (`id`) ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT `fk_employee_workplace_end_origin` FOREIGN KEY (`end_personnel_action_target_id`) REFERENCES `institution_personnel_action_targets` (`id`) ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT `chk_employee_workplace_type` CHECK (`workplace_type_code` IN ('HEAD_OFFICE','PROJECT','OTHER')),
    CONSTRAINT `chk_employee_workplace_status` CHECK (`status_code` IN ('PLANNED','ACTIVE','ENDED','CANCELLED')),
    CONSTRAINT `chk_employee_workplace_period` CHECK (`end_date` IS NULL OR `end_date` >= `start_date`),
    CONSTRAINT `chk_employee_workplace_project_type` CHECK ((`workplace_type_code` = 'PROJECT' AND `project_id` IS NOT NULL) OR (`workplace_type_code` <> 'PROJECT' AND `project_id` IS NULL)),
    CONSTRAINT `chk_employee_workplace_other_name` CHECK (`workplace_type_code` <> 'OTHER' OR `workplace_name_snapshot` IS NOT NULL),
    CONSTRAINT `chk_employee_workplace_result` CHECK ((`status_code` IN ('PLANNED','ACTIVE') AND `end_date` IS NULL) OR (`status_code` = 'ENDED' AND `end_date` IS NOT NULL) OR `status_code` = 'CANCELLED')
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='프로젝트 배치와 분리된 직원 근무지 기간이력 SSOT';

CREATE TABLE `institution_personnel_action_changes` (
    `id` VARCHAR(36) NOT NULL COMMENT '발령 변경항목 ID(UUID)',
    `personnel_action_target_id` VARCHAR(36) NOT NULL COMMENT '발령 대상자 ID',
    `sort_no` INT NOT NULL COMMENT '대상자 내 변경항목 순번',
    `change_type_code` VARCHAR(40) NOT NULL COMMENT '변경항목 유형',
    `effective_date` DATE NOT NULL COMMENT '변경 적용기준일',
    `before_display_snapshot` VARCHAR(500) NULL COMMENT '변경 전 표시 스냅샷',
    `after_display_snapshot` VARCHAR(500) NULL COMMENT '변경 후 표시 스냅샷',
    `before_employment_status` VARCHAR(30) NULL COMMENT '변경 전 재직상태',
    `after_employment_status` VARCHAR(30) NULL COMMENT '변경 후 재직상태',
    `before_department_id` VARCHAR(36) NULL COMMENT '변경 전 부서',
    `after_department_id` VARCHAR(36) NULL COMMENT '변경 후 부서',
    `before_position_id` VARCHAR(36) NULL COMMENT '변경 전 직위',
    `after_position_id` VARCHAR(36) NULL COMMENT '변경 후 직위',
    `before_job_id` VARCHAR(36) NULL COMMENT '변경 전 직무',
    `after_job_id` VARCHAR(36) NULL COMMENT '변경 후 직무',
    `project_id` VARCHAR(36) NULL COMMENT '배치·해제 프로젝트',
    `project_assignment_id` VARCHAR(36) NULL COMMENT '해제 대상 프로젝트 배치',
    `assignment_job_id` VARCHAR(36) NULL COMMENT '프로젝트 배치 직무',
    `assignment_role` VARCHAR(100) NULL COMMENT '프로젝트 배치 역할',
    `assignment_start_date` DATE NULL COMMENT '프로젝트 배치 시작일',
    `assignment_end_date` DATE NULL COMMENT '프로젝트 배치 종료일',
    `is_primary_assignment` TINYINT(1) NULL COMMENT '주배치 여부',
    `workplace_assignment_id` VARCHAR(36) NULL COMMENT '변경 전 근무지 배치',
    `workplace_type_code` VARCHAR(30) NULL COMMENT '변경 후 근무지유형',
    `workplace_project_id` VARCHAR(36) NULL COMMENT '변경 후 프로젝트 현장',
    `workplace_name_snapshot` VARCHAR(150) NULL COMMENT '변경 후 근무지명 스냅샷',
    `workplace_address_snapshot` VARCHAR(500) NULL COMMENT '변경 후 근무지 주소 스냅샷',
    `leave_period_id` VARCHAR(36) NULL COMMENT '복직 대상 휴직기간',
    `leave_type_code` VARCHAR(30) NULL COMMENT '휴직유형',
    `leave_start_date` DATE NULL COMMENT '휴직 시작일',
    `leave_planned_end_date` DATE NULL COMMENT '휴직 예정종료일',
    `leave_actual_end_date` DATE NULL COMMENT '복직 실제종료일',
    `date_kind` VARCHAR(20) NULL COMMENT '입퇴사일 종류: DOCUMENT/ACTUAL',
    `before_date` DATE NULL COMMENT '변경 전 입퇴사일',
    `after_date` DATE NULL COMMENT '변경 후 입퇴사일',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '생성일시',
    `created_by` VARCHAR(100) NOT NULL COMMENT '생성 Actor',
    `updated_at` DATETIME NULL COMMENT '수정일시',
    `updated_by` VARCHAR(100) NULL COMMENT '수정 Actor',
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_personnel_action_change_type` (`personnel_action_target_id`, `change_type_code`),
    UNIQUE KEY `uk_personnel_action_change_sort` (`personnel_action_target_id`, `sort_no`),
    KEY `idx_personnel_action_change_type_date` (`change_type_code`, `effective_date`),
    KEY `idx_personnel_action_change_department_after` (`after_department_id`),
    KEY `idx_personnel_action_change_position_after` (`after_position_id`),
    KEY `idx_personnel_action_change_job_after` (`after_job_id`),
    KEY `idx_personnel_action_change_project` (`project_id`),
    CONSTRAINT `fk_personnel_action_change_target` FOREIGN KEY (`personnel_action_target_id`) REFERENCES `institution_personnel_action_targets` (`id`) ON UPDATE RESTRICT ON DELETE CASCADE,
    CONSTRAINT `fk_personnel_action_change_department_before` FOREIGN KEY (`before_department_id`) REFERENCES `user_departments` (`id`) ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT `fk_personnel_action_change_department_after` FOREIGN KEY (`after_department_id`) REFERENCES `user_departments` (`id`) ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT `fk_personnel_action_change_position_before` FOREIGN KEY (`before_position_id`) REFERENCES `user_positions` (`id`) ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT `fk_personnel_action_change_position_after` FOREIGN KEY (`after_position_id`) REFERENCES `user_positions` (`id`) ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT `fk_personnel_action_change_job_before` FOREIGN KEY (`before_job_id`) REFERENCES `user_jobs` (`id`) ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT `fk_personnel_action_change_job_after` FOREIGN KEY (`after_job_id`) REFERENCES `user_jobs` (`id`) ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT `fk_personnel_action_change_project` FOREIGN KEY (`project_id`) REFERENCES `system_projects` (`id`) ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT `fk_personnel_action_change_project_assignment` FOREIGN KEY (`project_assignment_id`) REFERENCES `user_employee_project_assignments` (`id`) ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT `fk_personnel_action_change_assignment_job` FOREIGN KEY (`assignment_job_id`) REFERENCES `user_jobs` (`id`) ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT `fk_personnel_action_change_workplace_assignment` FOREIGN KEY (`workplace_assignment_id`) REFERENCES `user_employee_workplace_assignments` (`id`) ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT `fk_personnel_action_change_workplace_project` FOREIGN KEY (`workplace_project_id`) REFERENCES `system_projects` (`id`) ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT `fk_personnel_action_change_leave_period` FOREIGN KEY (`leave_period_id`) REFERENCES `user_employee_leave_periods` (`id`) ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT `chk_personnel_action_change_type` CHECK (`change_type_code` IN ('EMPLOYMENT_STATUS','DEPARTMENT','POSITION','JOB','PROJECT_ASSIGNMENT','PROJECT_RELEASE','WORKPLACE','LEAVE','RETURN_FROM_LEAVE','HIRE_DATE','RETIRE_DATE')),
    CONSTRAINT `chk_personnel_action_change_required` CHECK (
        (`change_type_code` = 'EMPLOYMENT_STATUS' AND `before_employment_status` IS NOT NULL AND `after_employment_status` IS NOT NULL AND `before_employment_status` <> `after_employment_status`)
        OR (`change_type_code` = 'DEPARTMENT' AND NOT (`before_department_id` <=> `after_department_id`))
        OR (`change_type_code` = 'POSITION' AND NOT (`before_position_id` <=> `after_position_id`))
        OR (`change_type_code` = 'JOB' AND NOT (`before_job_id` <=> `after_job_id`))
        OR (`change_type_code` = 'PROJECT_ASSIGNMENT' AND `project_id` IS NOT NULL AND `assignment_start_date` IS NOT NULL AND `is_primary_assignment` IN (0,1))
        OR (`change_type_code` = 'PROJECT_RELEASE' AND `project_assignment_id` IS NOT NULL AND `assignment_end_date` IS NOT NULL)
        OR (`change_type_code` = 'WORKPLACE' AND `workplace_type_code` IS NOT NULL)
        OR (`change_type_code` = 'LEAVE' AND `leave_type_code` IS NOT NULL AND `leave_start_date` IS NOT NULL)
        OR (`change_type_code` = 'RETURN_FROM_LEAVE' AND `leave_period_id` IS NOT NULL AND `leave_actual_end_date` IS NOT NULL)
        OR (`change_type_code` IN ('HIRE_DATE','RETIRE_DATE') AND `date_kind` IN ('DOCUMENT','ACTUAL') AND NOT (`before_date` <=> `after_date`))
    ),
    CONSTRAINT `chk_personnel_action_change_status_columns` CHECK ((`before_employment_status` IS NULL AND `after_employment_status` IS NULL) OR `change_type_code` = 'EMPLOYMENT_STATUS'),
    CONSTRAINT `chk_personnel_action_change_department_columns` CHECK ((`before_department_id` IS NULL AND `after_department_id` IS NULL) OR `change_type_code` = 'DEPARTMENT'),
    CONSTRAINT `chk_personnel_action_change_position_columns` CHECK ((`before_position_id` IS NULL AND `after_position_id` IS NULL) OR `change_type_code` = 'POSITION'),
    CONSTRAINT `chk_personnel_action_change_job_columns` CHECK ((`before_job_id` IS NULL AND `after_job_id` IS NULL) OR `change_type_code` = 'JOB'),
    CONSTRAINT `chk_personnel_action_change_project_columns` CHECK ((`project_id` IS NULL AND `project_assignment_id` IS NULL AND `assignment_job_id` IS NULL AND `assignment_role` IS NULL AND `assignment_start_date` IS NULL AND `assignment_end_date` IS NULL AND `is_primary_assignment` IS NULL) OR `change_type_code` IN ('PROJECT_ASSIGNMENT','PROJECT_RELEASE')),
    CONSTRAINT `chk_personnel_action_change_workplace_columns` CHECK ((`workplace_assignment_id` IS NULL AND `workplace_type_code` IS NULL AND `workplace_project_id` IS NULL AND `workplace_name_snapshot` IS NULL AND `workplace_address_snapshot` IS NULL) OR `change_type_code` = 'WORKPLACE'),
    CONSTRAINT `chk_personnel_action_change_leave_columns` CHECK ((`leave_period_id` IS NULL AND `leave_type_code` IS NULL AND `leave_start_date` IS NULL AND `leave_planned_end_date` IS NULL AND `leave_actual_end_date` IS NULL) OR `change_type_code` IN ('LEAVE','RETURN_FROM_LEAVE')),
    CONSTRAINT `chk_personnel_action_change_date_columns` CHECK ((`date_kind` IS NULL AND `before_date` IS NULL AND `after_date` IS NULL) OR `change_type_code` IN ('HIRE_DATE','RETIRE_DATE')),
    CONSTRAINT `chk_personnel_action_change_workplace_project` CHECK (`change_type_code` <> 'WORKPLACE' OR (`workplace_type_code` = 'PROJECT' AND `workplace_project_id` IS NOT NULL) OR (`workplace_type_code` <> 'PROJECT' AND `workplace_project_id` IS NULL)),
    CONSTRAINT `chk_personnel_action_change_leave_period` CHECK (`leave_planned_end_date` IS NULL OR `leave_start_date` IS NULL OR `leave_planned_end_date` >= `leave_start_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='인사발령 대상자별 타입 안전 변경명령';

ALTER TABLE `user_employees`
    ADD COLUMN `employment_status` VARCHAR(30) NULL COMMENT '현재 재직상태 공용코드' AFTER `position_id`,
    ADD COLUMN `job_id` VARCHAR(36) NULL COMMENT '현재 직무 캐시; 인사발령 적용 Service만 갱신' AFTER `employment_status`,
    ADD INDEX `idx_user_employees_employment_status` (`employment_status`),
    ADD INDEX `idx_user_employees_job` (`job_id`),
    ADD CONSTRAINT `fk_user_employees_job` FOREIGN KEY (`job_id`) REFERENCES `user_jobs` (`id`) ON UPDATE RESTRICT ON DELETE RESTRICT;

UPDATE `user_employees`
SET `employment_status` = CASE
    WHEN COALESCE(`real_hire_date`, `doc_hire_date`) > CURRENT_DATE THEN 'PENDING_HIRE'
    WHEN COALESCE(`real_retire_date`, `doc_retire_date`) IS NOT NULL
         AND COALESCE(`real_retire_date`, `doc_retire_date`) <= CURRENT_DATE THEN 'RETIRED'
    ELSE 'ACTIVE'
END;

ALTER TABLE `user_employees`
    MODIFY COLUMN `employment_status` VARCHAR(30) NOT NULL COMMENT '현재 재직상태 공용코드',
    ADD CONSTRAINT `chk_user_employees_employment_status` CHECK (`employment_status` IN ('PENDING_HIRE','ACTIVE','ON_LEAVE','RETIRED'));

INSERT INTO `user_employee_employment_status_histories`
    (`id`, `employee_id`, `status_code`, `effective_date`, `ended_date`, `reason`, `created_at`, `created_by`)
SELECT UUID(), e.id,
       CASE WHEN COALESCE(e.real_hire_date, e.doc_hire_date) > CURRENT_DATE THEN 'PENDING_HIRE' ELSE 'ACTIVE' END,
       COALESCE(e.real_hire_date, e.doc_hire_date),
       CASE
           WHEN COALESCE(e.real_retire_date, e.doc_retire_date) IS NOT NULL
                AND COALESCE(e.real_retire_date, e.doc_retire_date) <= CURRENT_DATE
           THEN DATE_SUB(COALESCE(e.real_retire_date, e.doc_retire_date), INTERVAL 1 DAY)
           ELSE NULL
       END,
       '기존 입사일 기준 재직상태 초기 이력', CURRENT_TIMESTAMP, 'SYSTEM:PERSONNEL_MIGRATION'
FROM `user_employees` e;

INSERT INTO `user_employee_employment_status_histories`
    (`id`, `employee_id`, `status_code`, `effective_date`, `ended_date`, `reason`, `created_at`, `created_by`)
SELECT UUID(), e.id, 'RETIRED', COALESCE(e.real_retire_date, e.doc_retire_date), NULL,
       '기존 퇴사일 기준 퇴직상태 초기 이력', CURRENT_TIMESTAMP, 'SYSTEM:PERSONNEL_MIGRATION'
FROM `user_employees` e
WHERE COALESCE(e.real_retire_date, e.doc_retire_date) IS NOT NULL
  AND COALESCE(e.real_retire_date, e.doc_retire_date) <= CURRENT_DATE;
