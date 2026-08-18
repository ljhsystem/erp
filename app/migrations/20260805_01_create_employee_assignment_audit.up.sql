-- 직무·프로젝트 직접 등록의 감사 증적과 역할 기반 행위 권한을 생성한다.

CREATE TABLE `user_employee_assignment_audits` (
    `id` VARCHAR(36) NOT NULL COMMENT '배치 감사 ID(UUID)',
    `assignment_domain` VARCHAR(20) NOT NULL COMMENT '배치 도메인: JOB/PROJECT/WORKPLACE',
    `job_assignment_id` VARCHAR(36) NULL COMMENT '직무 이력 ID',
    `project_assignment_id` VARCHAR(36) NULL COMMENT '프로젝트 배치 ID',
    `workplace_assignment_id` VARCHAR(36) NULL COMMENT '근무지 배치 ID',
    `employee_id` VARCHAR(36) NOT NULL COMMENT '직원 ID',
    `action_type` VARCHAR(30) NOT NULL COMMENT '작업구분 공용코드',
    `source_type` VARCHAR(50) NOT NULL COMMENT '등록출처 공용코드',
    `reason` VARCHAR(1000) NOT NULL COMMENT '처리사유',
    `personnel_action_target_id` VARCHAR(36) NULL COMMENT '관련 인사발령 대상자 ID',
    `request_key` VARCHAR(64) NOT NULL COMMENT '멱등 요청 식별값',
    `before_data` LONGTEXT COLLATE utf8mb4_bin NULL COMMENT '변경 전 JSON',
    `after_data` LONGTEXT COLLATE utf8mb4_bin NOT NULL COMMENT '변경 후 JSON',
    `processed_by` VARCHAR(100) NOT NULL COMMENT '처리 Actor',
    `processed_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '처리일시',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '감사행 생성일시',
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_employee_assignment_audit_request` (`request_key`),
    KEY `idx_employee_assignment_audit_employee` (`employee_id`, `processed_at`),
    KEY `idx_employee_assignment_audit_job` (`job_assignment_id`, `processed_at`),
    KEY `idx_employee_assignment_audit_project` (`project_assignment_id`, `processed_at`),
    KEY `idx_employee_assignment_audit_workplace` (`workplace_assignment_id`, `processed_at`),
    KEY `idx_employee_assignment_audit_action` (`action_type`, `source_type`, `processed_at`),
    KEY `idx_employee_assignment_audit_personnel_action` (`personnel_action_target_id`),
    CONSTRAINT `fk_employee_assignment_audit_employee` FOREIGN KEY (`employee_id`) REFERENCES `user_employees` (`id`) ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT `fk_employee_assignment_audit_job` FOREIGN KEY (`job_assignment_id`) REFERENCES `user_employee_job_assignments` (`id`) ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT `fk_employee_assignment_audit_project` FOREIGN KEY (`project_assignment_id`) REFERENCES `user_employee_project_assignments` (`id`) ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT `fk_employee_assignment_audit_workplace` FOREIGN KEY (`workplace_assignment_id`) REFERENCES `user_employee_workplace_assignments` (`id`) ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT `fk_employee_assignment_audit_personnel_action` FOREIGN KEY (`personnel_action_target_id`) REFERENCES `institution_personnel_action_targets` (`id`) ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT `chk_employee_assignment_audit_domain` CHECK (
        (`assignment_domain` = 'JOB' AND `job_assignment_id` IS NOT NULL AND `project_assignment_id` IS NULL AND `workplace_assignment_id` IS NULL)
        OR (`assignment_domain` = 'PROJECT' AND `job_assignment_id` IS NULL AND `project_assignment_id` IS NOT NULL AND `workplace_assignment_id` IS NULL)
        OR (`assignment_domain` = 'WORKPLACE' AND `job_assignment_id` IS NULL AND `project_assignment_id` IS NULL AND `workplace_assignment_id` IS NOT NULL)
    ),
    CONSTRAINT `chk_employee_assignment_audit_reason` CHECK (CHAR_LENGTH(TRIM(`reason`)) > 0),
    CONSTRAINT `chk_employee_assignment_audit_before_json` CHECK (`before_data` IS NULL OR JSON_VALID(`before_data`)),
    CONSTRAINT `chk_employee_assignment_audit_after_json` CHECK (JSON_VALID(`after_data`))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='직원 직무·프로젝트·근무지 배치 작업 감사 증적';

SET @assignment_code_sort_base := (SELECT COALESCE(MAX(sort_no), 0) FROM system_codes);

INSERT INTO system_codes
    (id, sort_no, code_group, group_name, code, code_name, is_active, created_at, created_by)
SELECT UUID(), @assignment_code_sort_base + seed.ordinal_no,
       seed.code_group, seed.group_name, seed.code, seed.code_name,
       1, CURRENT_TIMESTAMP, 'SYSTEM:EMPLOYEE_ASSIGNMENT'
FROM (
    SELECT 1 ordinal_no, 'EMPLOYEE_ASSIGNMENT_AUDIT_ACTION' code_group, '직원배치감사작업' group_name, 'CREATE' code, '등록' code_name
    UNION ALL SELECT 2, 'EMPLOYEE_ASSIGNMENT_AUDIT_ACTION', '직원배치감사작업', 'END', '종료'
    UNION ALL SELECT 3, 'EMPLOYEE_ASSIGNMENT_AUDIT_ACTION', '직원배치감사작업', 'CORRECT', '정정'
    UNION ALL SELECT 4, 'EMPLOYEE_ASSIGNMENT_SOURCE', '직원배치등록출처', 'INITIAL_MIGRATION', '초기 데이터 이관'
    UNION ALL SELECT 5, 'EMPLOYEE_ASSIGNMENT_SOURCE', '직원배치등록출처', 'PRE_PERSONNEL_ACTION_HISTORY', '인사발령 도입 전 이력'
    UNION ALL SELECT 6, 'EMPLOYEE_ASSIGNMENT_SOURCE', '직원배치등록출처', 'DIRECT_PROJECT_ASSIGNMENT', '비주요 프로젝트 배치'
    UNION ALL SELECT 7, 'EMPLOYEE_ASSIGNMENT_SOURCE', '직원배치등록출처', 'TEMPORARY_PROJECT_ASSIGNMENT', '단기 프로젝트 배치'
    UNION ALL SELECT 8, 'EMPLOYEE_ASSIGNMENT_SOURCE', '직원배치등록출처', 'CONCURRENT_PROJECT_ASSIGNMENT', '겸임 프로젝트 배치'
    UNION ALL SELECT 9, 'EMPLOYEE_ASSIGNMENT_SOURCE', '직원배치등록출처', 'ADMIN_CORRECTION', '관리자 정정'
    UNION ALL SELECT 10, 'EMPLOYEE_ASSIGNMENT_SOURCE', '직원배치등록출처', 'DATA_CONSISTENCY_REPAIR', '데이터 정합성 보정'
) seed
WHERE NOT EXISTS (
    SELECT 1 FROM system_codes existing
    WHERE existing.code_group = seed.code_group AND existing.code = seed.code
);

SET @permission_sort_base := (SELECT COALESCE(MAX(sort_no), 0) FROM auth_permissions);

INSERT INTO auth_permissions
    (id, sort_no, page, permission_source, category, permission_key, permission_name, description, page_key, is_active, created_at, created_by)
SELECT seed.id, @permission_sort_base + seed.ordinal_no, '직무·배치관리', 'api', '대외기관업무',
       seed.permission_key, seed.permission_name, seed.description,
       'institution.human_resources.job_assignments', 1, CURRENT_TIMESTAMP, 'SYSTEM:EMPLOYEE_ASSIGNMENT'
FROM (
    SELECT 1 ordinal_no, UUID() id, 'api.institution.human_resources.job_assignment.history_save' permission_key, '과거직무이력등록' permission_name, '종료된 과거 직무 이력 등록' description
    UNION ALL SELECT 2, UUID(), 'api.institution.human_resources.job_assignment.project_save', '프로젝트배치등록', '비주요 프로젝트 배치 등록'
    UNION ALL SELECT 3, UUID(), 'api.institution.human_resources.job_assignment.end', '프로젝트배치종료', '직접 등록 프로젝트 배치 종료'
    UNION ALL SELECT 4, UUID(), 'api.institution.human_resources.job_assignment.correct', '관리자정정', '직접 등록 직무·프로젝트 배치 관리자 정정'
) seed
WHERE NOT EXISTS (SELECT 1 FROM auth_permissions existing WHERE existing.permission_key = seed.permission_key);

INSERT INTO auth_role_permissions (id, role_id, permission_id, created_at, created_by)
SELECT UUID(), source.role_id, target.id, CURRENT_TIMESTAMP, 'SYSTEM:EMPLOYEE_ASSIGNMENT'
FROM auth_permissions target
JOIN auth_permissions source_permission
  ON source_permission.permission_key = CASE
      WHEN target.permission_key = 'api.institution.human_resources.job_assignment.correct'
      THEN 'api.institution.human_resources.personnel_action.apply'
      ELSE 'api.institution.human_resources.personnel_action.save'
  END
JOIN auth_role_permissions source ON source.permission_id = source_permission.id
WHERE target.permission_key IN (
    'api.institution.human_resources.job_assignment.history_save',
    'api.institution.human_resources.job_assignment.project_save',
    'api.institution.human_resources.job_assignment.end',
    'api.institution.human_resources.job_assignment.correct'
)
AND NOT EXISTS (
    SELECT 1 FROM auth_role_permissions existing
    WHERE existing.role_id = source.role_id AND existing.permission_id = target.id
);
