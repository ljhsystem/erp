CREATE TABLE `user_employee_attendance_clock_events` (
  `id` char(36) NOT NULL, `employee_id` char(36) NOT NULL,
  `event_type_code` varchar(30) NOT NULL, `occurred_at` datetime NOT NULL, `collected_at` datetime NOT NULL,
  `source_type_code` varchar(30) NOT NULL, `external_key` varchar(191) DEFAULT NULL,
  `request_key` varchar(191) NOT NULL, `device_identifier` varchar(100) DEFAULT NULL,
  `source_payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`source_payload`)),
  `is_valid` tinyint(1) NOT NULL DEFAULT 1, `invalid_reason` varchar(500) DEFAULT NULL,
  `created_at` datetime NOT NULL, `created_by` varchar(100) NOT NULL,
  PRIMARY KEY (`id`), UNIQUE KEY `uk_attendance_clock_request` (`request_key`),
  UNIQUE KEY `uk_attendance_clock_external` (`source_type_code`,`external_key`),
  KEY `idx_attendance_clock_employee_time` (`employee_id`,`occurred_at`),
  CONSTRAINT `fk_attendance_clock_employee` FOREIGN KEY (`employee_id`) REFERENCES `user_employees` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `chk_attendance_clock_event` CHECK (`event_type_code` IN ('CLOCK_IN','CLOCK_OUT')),
  CONSTRAINT `chk_attendance_clock_source` CHECK (`source_type_code` IN ('ADMIN','EMPLOYEE_WEB','SYSTEM')),
  CONSTRAINT `chk_attendance_clock_valid` CHECK (`is_valid` IN (0,1)),
  CONSTRAINT `chk_attendance_clock_invalid_reason` CHECK ((`is_valid`=1 AND `invalid_reason` IS NULL) OR (`is_valid`=0 AND `invalid_reason` IS NOT NULL))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='불변 출퇴근 원본 SSOT';

CREATE TABLE `user_employee_attendance_daily_records` (
  `id` char(36) NOT NULL, `employee_id` char(36) NOT NULL, `work_date` date NOT NULL,
  `employment_contract_id` char(36) DEFAULT NULL,
  `scheduled_start_at` datetime DEFAULT NULL, `scheduled_end_at` datetime DEFAULT NULL,
  `scheduled_break_seconds` int unsigned NOT NULL DEFAULT 0,
  `first_clock_in_at` datetime DEFAULT NULL, `last_clock_out_at` datetime DEFAULT NULL,
  `actual_work_seconds` int unsigned NOT NULL DEFAULT 0, `actual_break_seconds` int unsigned NOT NULL DEFAULT 0,
  `scheduled_work_seconds` int unsigned NOT NULL DEFAULT 0, `calculated_overtime_seconds` int unsigned NOT NULL DEFAULT 0,
  `night_work_seconds` int unsigned NOT NULL DEFAULT 0, `holiday_work_seconds` int unsigned NOT NULL DEFAULT 0,
  `late_candidate_seconds` int unsigned NOT NULL DEFAULT 0, `early_leave_candidate_seconds` int unsigned NOT NULL DEFAULT 0,
  `process_status_code` varchar(30) NOT NULL DEFAULT 'CALCULATED', `calculation_status_code` varchar(30) NOT NULL DEFAULT 'CALCULATED',
  `calculation_version` int unsigned NOT NULL DEFAULT 1, `is_corrected` tinyint(1) NOT NULL DEFAULT 0,
  `department_id_snapshot` char(36) DEFAULT NULL, `department_name_snapshot` varchar(100) DEFAULT NULL,
  `job_id_snapshot` char(36) DEFAULT NULL, `job_name_snapshot` varchar(100) DEFAULT NULL,
  `primary_project_id_snapshot` char(36) DEFAULT NULL, `primary_project_name_snapshot` varchar(150) DEFAULT NULL,
  `workplace_assignment_id_snapshot` char(36) DEFAULT NULL, `workplace_name_snapshot` varchar(200) DEFAULT NULL,
  `created_at` datetime NOT NULL, `created_by` varchar(100) NOT NULL, `updated_at` datetime NOT NULL, `updated_by` varchar(100) NOT NULL,
  PRIMARY KEY (`id`), UNIQUE KEY `uk_attendance_daily_employee_date` (`employee_id`,`work_date`),
  KEY `idx_attendance_daily_date_employee` (`work_date`,`employee_id`),
  KEY `idx_attendance_daily_status_date` (`process_status_code`,`work_date`),
  KEY `idx_attendance_daily_department_date` (`department_id_snapshot`,`work_date`),
  KEY `idx_attendance_daily_project_date` (`primary_project_id_snapshot`,`work_date`),
  CONSTRAINT `fk_attendance_daily_employee` FOREIGN KEY (`employee_id`) REFERENCES `user_employees` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_attendance_daily_contract` FOREIGN KEY (`employment_contract_id`) REFERENCES `institution_employment_contracts` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `chk_attendance_daily_corrected` CHECK (`is_corrected` IN (0,1)),
  CONSTRAINT `chk_attendance_daily_process` CHECK (`process_status_code` IN ('CALCULATED','CONFIRMED','CLOSED')),
  CONSTRAINT `chk_attendance_daily_calculation` CHECK (`calculation_status_code` IN ('CALCULATED','NEEDS_CONFIRMATION','ERROR'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='직원 일별 근태 실적 SSOT';

CREATE TABLE `user_employee_attendance_work_segments` (
  `id` char(36) NOT NULL, `daily_record_id` char(36) NOT NULL, `segment_type_code` varchar(30) NOT NULL,
  `started_at` datetime NOT NULL, `ended_at` datetime NOT NULL, `duration_seconds` int unsigned NOT NULL,
  `project_id` char(36) DEFAULT NULL, `project_name_snapshot` varchar(150) DEFAULT NULL,
  `workplace_assignment_id` char(36) DEFAULT NULL, `workplace_name_snapshot` varchar(200) DEFAULT NULL,
  `source_type_code` varchar(30) NOT NULL, `is_manual` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL, `created_by` varchar(100) NOT NULL, `updated_at` datetime NOT NULL, `updated_by` varchar(100) NOT NULL,
  PRIMARY KEY (`id`), KEY `idx_attendance_segment_daily_start` (`daily_record_id`,`started_at`),
  KEY `idx_attendance_segment_project_start` (`project_id`,`started_at`),
  CONSTRAINT `fk_attendance_segment_daily` FOREIGN KEY (`daily_record_id`) REFERENCES `user_employee_attendance_daily_records` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_attendance_segment_project` FOREIGN KEY (`project_id`) REFERENCES `system_projects` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_attendance_segment_workplace` FOREIGN KEY (`workplace_assignment_id`) REFERENCES `user_employee_workplace_assignments` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `chk_attendance_segment_type` CHECK (`segment_type_code` IN ('WORK','BREAK','OUTSIDE')),
  CONSTRAINT `chk_attendance_segment_time` CHECK (`ended_at`>`started_at`),
  CONSTRAINT `chk_attendance_segment_manual` CHECK (`is_manual` IN (0,1))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='일별 실제 근무·휴게·외출 구간';

CREATE TABLE `user_employee_attendance_daily_exceptions` (
  `id` char(36) NOT NULL, `daily_record_id` char(36) NOT NULL, `exception_type_code` varchar(30) NOT NULL,
  `candidate_seconds` int unsigned NOT NULL DEFAULT 0, `source_type_code` varchar(30) NOT NULL,
  `resolution_status_code` varchar(30) NOT NULL DEFAULT 'OPEN', `resolution_reason` varchar(500) DEFAULT NULL,
  `resolved_at` datetime DEFAULT NULL, `resolved_by` varchar(100) DEFAULT NULL,
  `created_at` datetime NOT NULL, `created_by` varchar(100) NOT NULL, `updated_at` datetime NOT NULL, `updated_by` varchar(100) NOT NULL,
  PRIMARY KEY (`id`), UNIQUE KEY `uk_attendance_exception_daily_type_source` (`daily_record_id`,`exception_type_code`,`source_type_code`),
  KEY `idx_attendance_exception_type_status` (`exception_type_code`,`resolution_status_code`),
  CONSTRAINT `fk_attendance_exception_daily` FOREIGN KEY (`daily_record_id`) REFERENCES `user_employee_attendance_daily_records` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `chk_attendance_exception_type` CHECK (`exception_type_code` IN ('LATE','EARLY_LEAVE','ABSENT','MISSING_CLOCK_IN','MISSING_CLOCK_OUT','NO_SCHEDULE','CONTRACT_CONFLICT','LEAVE_PERIOD_CONFLICT')),
  CONSTRAINT `chk_attendance_exception_source` CHECK (`source_type_code` IN ('CALCULATION','ADMIN')),
  CONSTRAINT `chk_attendance_exception_resolution` CHECK (`resolution_status_code` IN ('OPEN','CONFIRMED','RESOLVED'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='일별 복수 근태 예외 SSOT';

CREATE TABLE `user_employee_attendance_monthly_closures` (
  `id` char(36) NOT NULL, `employee_id` char(36) NOT NULL, `closing_month` char(7) NOT NULL,
  `close_status_code` varchar(30) NOT NULL DEFAULT 'OPEN', `current_revision` int unsigned NOT NULL DEFAULT 0,
  `current_history_id` char(36) DEFAULT NULL, `closed_by` varchar(100) DEFAULT NULL, `closed_at` datetime DEFAULT NULL,
  `reopened_by` varchar(100) DEFAULT NULL, `reopened_at` datetime DEFAULT NULL, `reopen_reason` varchar(500) DEFAULT NULL,
  `created_at` datetime NOT NULL, `created_by` varchar(100) NOT NULL, `updated_at` datetime NOT NULL, `updated_by` varchar(100) NOT NULL,
  PRIMARY KEY (`id`), UNIQUE KEY `uk_attendance_closure_employee_month` (`employee_id`,`closing_month`),
  KEY `idx_attendance_closure_month_status` (`closing_month`,`close_status_code`),
  CONSTRAINT `fk_attendance_closure_employee` FOREIGN KEY (`employee_id`) REFERENCES `user_employees` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `chk_attendance_closure_month` CHECK (`closing_month` REGEXP '^[0-9]{4}-(0[1-9]|1[0-2])$'),
  CONSTRAINT `chk_attendance_closure_status` CHECK (`close_status_code` IN ('OPEN','CLOSED','REOPENED'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='직원·월별 현재 근태 마감 상태';

CREATE TABLE `user_employee_attendance_monthly_closure_histories` (
  `id` char(36) NOT NULL, `monthly_closure_id` char(36) NOT NULL, `revision` int unsigned NOT NULL,
  `employee_id` char(36) NOT NULL, `closing_month` char(7) NOT NULL, `workday_count` int unsigned NOT NULL,
  `scheduled_work_seconds` bigint unsigned NOT NULL, `actual_work_seconds` bigint unsigned NOT NULL,
  `calculated_overtime_seconds` bigint unsigned NOT NULL, `night_work_seconds` bigint unsigned NOT NULL,
  `holiday_work_seconds` bigint unsigned NOT NULL, `late_candidate_count` int unsigned NOT NULL,
  `late_candidate_seconds` bigint unsigned NOT NULL, `early_leave_candidate_count` int unsigned NOT NULL,
  `early_leave_candidate_seconds` bigint unsigned NOT NULL, `absence_candidate_days` int unsigned NOT NULL,
  `missing_clock_count` int unsigned NOT NULL, `ledger_hash` char(64) NOT NULL, `calculation_version` int unsigned NOT NULL,
  `close_reason` varchar(500) NOT NULL, `closed_by` varchar(100) NOT NULL, `closed_at` datetime NOT NULL,
  `source_request_key` varchar(191) NOT NULL, `created_at` datetime NOT NULL,
  PRIMARY KEY (`id`), UNIQUE KEY `uk_attendance_closure_history_revision` (`monthly_closure_id`,`revision`),
  UNIQUE KEY `uk_attendance_closure_history_request` (`source_request_key`), KEY `idx_attendance_closure_history_employee_month` (`employee_id`,`closing_month`),
  CONSTRAINT `fk_attendance_closure_history_header` FOREIGN KEY (`monthly_closure_id`) REFERENCES `user_employee_attendance_monthly_closures` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_attendance_closure_history_employee` FOREIGN KEY (`employee_id`) REFERENCES `user_employees` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `chk_attendance_closure_history_revision` CHECK (`revision`>0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='월 마감 revision별 확정 스냅샷 SSOT';

ALTER TABLE `user_employee_attendance_monthly_closures` ADD CONSTRAINT `fk_attendance_closure_current_history` FOREIGN KEY (`current_history_id`) REFERENCES `user_employee_attendance_monthly_closure_histories` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE;

CREATE TABLE `user_employee_attendance_audits` (
  `id` char(36) NOT NULL, `attendance_domain` varchar(30) NOT NULL, `target_id` char(36) NOT NULL,
  `employee_id` char(36) NOT NULL, `action_type_code` varchar(30) NOT NULL, `source_type_code` varchar(30) NOT NULL,
  `reason` varchar(500) NOT NULL, `request_key` varchar(191) NOT NULL,
  `before_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`before_data`)),
  `after_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`after_data`)),
  `processed_by` varchar(100) NOT NULL, `processed_at` datetime NOT NULL,
  PRIMARY KEY (`id`), UNIQUE KEY `uk_attendance_audit_request` (`request_key`),
  KEY `idx_attendance_audit_target` (`attendance_domain`,`target_id`,`processed_at`), KEY `idx_attendance_audit_employee` (`employee_id`,`processed_at`),
  CONSTRAINT `fk_attendance_audit_employee` FOREIGN KEY (`employee_id`) REFERENCES `user_employees` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `chk_attendance_audit_domain` CHECK (`attendance_domain` IN ('CLOCK_EVENT','DAILY_RECORD','MONTHLY_CLOSURE')),
  CONSTRAINT `chk_attendance_audit_action` CHECK (`action_type_code` IN ('REGISTER','INVALIDATE','RECALCULATE','ADMIN_CORRECT','CLOSE','REOPEN')),
  CONSTRAINT `chk_attendance_audit_source` CHECK (`source_type_code` IN ('ADMIN','EMPLOYEE_WEB','SYSTEM'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='근태 변경 감사 증적';

INSERT INTO `system_codes` (`id`,`code_group`,`group_name`,`code`,`code_name`,`sort_no`,`is_active`,`created_at`,`created_by`,`updated_at`,`updated_by`)
SELECT UUID(),v.code_group,v.group_name,v.code,v.code_name,v.sort_no,1,NOW(),'SYSTEM:MIGRATION',NOW(),'SYSTEM:MIGRATION'
FROM (
 SELECT 'ATTENDANCE_CLOCK_EVENT_TYPE' code_group,'근태 출퇴근 이벤트' group_name,'CLOCK_IN' code,'출근' code_name,1 sort_no UNION ALL SELECT 'ATTENDANCE_CLOCK_EVENT_TYPE','근태 출퇴근 이벤트','CLOCK_OUT','퇴근',2
 UNION ALL SELECT 'ATTENDANCE_SOURCE_TYPE','근태 입력 출처','ADMIN','관리자',1 UNION ALL SELECT 'ATTENDANCE_SOURCE_TYPE','근태 입력 출처','EMPLOYEE_WEB','직원 웹',2 UNION ALL SELECT 'ATTENDANCE_SOURCE_TYPE','근태 입력 출처','SYSTEM','시스템',3 UNION ALL SELECT 'ATTENDANCE_SOURCE_TYPE','근태 입력 출처','CALCULATION','자동 계산',4
 UNION ALL SELECT 'ATTENDANCE_PROCESS_STATUS','근태 진행상태','CALCULATED','계산완료',1 UNION ALL SELECT 'ATTENDANCE_PROCESS_STATUS','근태 진행상태','CONFIRMED','확인완료',2 UNION ALL SELECT 'ATTENDANCE_PROCESS_STATUS','근태 진행상태','CLOSED','마감',3
 UNION ALL SELECT 'ATTENDANCE_EXCEPTION_TYPE','근태 예외','LATE','지각 후보',1 UNION ALL SELECT 'ATTENDANCE_EXCEPTION_TYPE','근태 예외','EARLY_LEAVE','조퇴 후보',2 UNION ALL SELECT 'ATTENDANCE_EXCEPTION_TYPE','근태 예외','ABSENT','결근 후보',3 UNION ALL SELECT 'ATTENDANCE_EXCEPTION_TYPE','근태 예외','MISSING_CLOCK_IN','출근 누락',4 UNION ALL SELECT 'ATTENDANCE_EXCEPTION_TYPE','근태 예외','MISSING_CLOCK_OUT','퇴근 누락',5 UNION ALL SELECT 'ATTENDANCE_EXCEPTION_TYPE','근태 예외','NO_SCHEDULE','예정 일정 없음',6 UNION ALL SELECT 'ATTENDANCE_EXCEPTION_TYPE','근태 예외','CONTRACT_CONFLICT','근로계약 충돌',7 UNION ALL SELECT 'ATTENDANCE_EXCEPTION_TYPE','근태 예외','LEAVE_PERIOD_CONFLICT','휴직기간 충돌',8
 UNION ALL SELECT 'ATTENDANCE_SEGMENT_TYPE','근태 구간','WORK','근무',1 UNION ALL SELECT 'ATTENDANCE_SEGMENT_TYPE','근태 구간','BREAK','휴게',2 UNION ALL SELECT 'ATTENDANCE_SEGMENT_TYPE','근태 구간','OUTSIDE','외출',3
 UNION ALL SELECT 'ATTENDANCE_CLOSE_STATUS','근태 마감상태','OPEN','진행중',1 UNION ALL SELECT 'ATTENDANCE_CLOSE_STATUS','근태 마감상태','CLOSED','마감',2 UNION ALL SELECT 'ATTENDANCE_CLOSE_STATUS','근태 마감상태','REOPENED','재오픈',3
 UNION ALL SELECT 'ATTENDANCE_AUDIT_ACTION','근태 감사작업','REGISTER','등록',1 UNION ALL SELECT 'ATTENDANCE_AUDIT_ACTION','근태 감사작업','INVALIDATE','원본무효화',2 UNION ALL SELECT 'ATTENDANCE_AUDIT_ACTION','근태 감사작업','RECALCULATE','재계산',3 UNION ALL SELECT 'ATTENDANCE_AUDIT_ACTION','근태 감사작업','ADMIN_CORRECT','관리자정정',4 UNION ALL SELECT 'ATTENDANCE_AUDIT_ACTION','근태 감사작업','CLOSE','월마감',5 UNION ALL SELECT 'ATTENDANCE_AUDIT_ACTION','근태 감사작업','REOPEN','마감해제',6
) v WHERE NOT EXISTS (SELECT 1 FROM system_codes c WHERE c.code_group=v.code_group AND c.code=v.code);
