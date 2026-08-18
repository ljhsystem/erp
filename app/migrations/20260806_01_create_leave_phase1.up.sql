CREATE TABLE `institution_employment_contracts_break_schedules` (
  `id` char(36) NOT NULL, `weekly_schedule_id` char(36) NOT NULL, `sort_no` int unsigned NOT NULL,
  `start_time` time NOT NULL, `end_time` time NOT NULL, `end_day_offset` tinyint unsigned NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL, `created_by` varchar(100) NOT NULL, `updated_at` datetime NOT NULL, `updated_by` varchar(100) NOT NULL,
  PRIMARY KEY (`id`), UNIQUE KEY `uk_contract_break_schedule_sort` (`weekly_schedule_id`,`sort_no`),
  KEY `idx_contract_break_schedule_time` (`weekly_schedule_id`,`start_time`,`end_time`),
  CONSTRAINT `fk_contract_break_schedule_weekly` FOREIGN KEY (`weekly_schedule_id`) REFERENCES `institution_employment_contracts_weekly_schedules` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `chk_contract_break_schedule_offset` CHECK (`end_day_offset` IN (0,1)),
  CONSTRAINT `chk_contract_break_schedule_time` CHECK (`end_day_offset`=1 OR `end_time`>`start_time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='근로계약 주간 일정별 실제 예정 휴게구간';

CREATE TABLE `institution_leave_types` (
  `id` char(36) NOT NULL, `type_code` varchar(40) NOT NULL, `type_name` varchar(100) NOT NULL,
  `is_paid` tinyint(1) NOT NULL DEFAULT 0, `deducts_balance` tinyint(1) NOT NULL DEFAULT 0,
  `requires_approval` tinyint(1) NOT NULL DEFAULT 1, `evidence_policy_code` varchar(20) NOT NULL DEFAULT 'OPTIONAL',
  `allowed_units_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`allowed_units_json`)),
  `is_active` tinyint(1) NOT NULL DEFAULT 1, `sort_no` int unsigned NOT NULL,
  `created_at` datetime NOT NULL, `created_by` varchar(100) NOT NULL, `updated_at` datetime NOT NULL, `updated_by` varchar(100) NOT NULL,
  PRIMARY KEY (`id`), UNIQUE KEY `uk_leave_type_code` (`type_code`), KEY `idx_leave_type_active_sort` (`is_active`,`sort_no`),
  CONSTRAINT `chk_leave_type_flags` CHECK (`is_paid` IN (0,1) AND `deducts_balance` IN (0,1) AND `requires_approval` IN (0,1) AND `is_active` IN (0,1)),
  CONSTRAINT `chk_leave_type_evidence` CHECK (`evidence_policy_code` IN ('NONE','OPTIONAL','REQUIRED'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='휴가관리 휴가 종류 및 정책 SSOT';

CREATE TABLE `institution_leave_grants` (
  `id` char(36) NOT NULL, `employee_id` char(36) NOT NULL, `leave_type_id` char(36) NOT NULL, `base_year` smallint unsigned NOT NULL,
  `granted_minutes` int unsigned NOT NULL, `usable_from` date NOT NULL, `usable_to` date NOT NULL, `expires_on` date DEFAULT NULL,
  `reason` varchar(500) NOT NULL, `request_key` varchar(191) NOT NULL,
  `created_at` datetime NOT NULL, `created_by` varchar(100) NOT NULL,
  PRIMARY KEY (`id`), UNIQUE KEY `uk_leave_grant_request` (`request_key`),
  KEY `idx_leave_grant_employee_type_year` (`employee_id`,`leave_type_id`,`base_year`), KEY `idx_leave_grant_period` (`usable_from`,`usable_to`),
  CONSTRAINT `fk_leave_grant_employee` FOREIGN KEY (`employee_id`) REFERENCES `user_employees` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_leave_grant_type` FOREIGN KEY (`leave_type_id`) REFERENCES `institution_leave_types` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `chk_leave_grant_minutes` CHECK (`granted_minutes`>0), CONSTRAINT `chk_leave_grant_period` CHECK (`usable_to`>=`usable_from` AND (`expires_on` IS NULL OR `expires_on`>=`usable_from`))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='직원별 휴가 수동 부여 업무행';

CREATE TABLE `institution_leave_requests` (
  `id` char(36) NOT NULL, `request_no` varchar(40) NOT NULL, `employee_id` char(36) NOT NULL,
  `request_kind_code` varchar(20) NOT NULL DEFAULT 'LEAVE', `original_request_id` char(36) DEFAULT NULL,
  `business_status_code` varchar(30) NOT NULL DEFAULT 'DRAFT', `current_approval_request_id` char(36) DEFAULT NULL,
  `reason` varchar(1000) NOT NULL, `requested_total_minutes` int unsigned NOT NULL DEFAULT 0,
  `request_key` varchar(191) NOT NULL, `created_at` datetime NOT NULL, `created_by` varchar(100) NOT NULL,
  `updated_at` datetime NOT NULL, `updated_by` varchar(100) NOT NULL,
  PRIMARY KEY (`id`), UNIQUE KEY `uk_leave_request_no` (`request_no`), UNIQUE KEY `uk_leave_request_key` (`request_key`),
  KEY `idx_leave_request_employee_status` (`employee_id`,`business_status_code`,`created_at`), KEY `idx_leave_request_approval` (`current_approval_request_id`),
  CONSTRAINT `fk_leave_request_employee` FOREIGN KEY (`employee_id`) REFERENCES `user_employees` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_leave_request_original` FOREIGN KEY (`original_request_id`) REFERENCES `institution_leave_requests` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_leave_request_approval` FOREIGN KEY (`current_approval_request_id`) REFERENCES `user_approval_requests` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `chk_leave_request_kind` CHECK (`request_kind_code` IN ('LEAVE','CANCELLATION')),
  CONSTRAINT `chk_leave_request_status` CHECK (`business_status_code` IN ('DRAFT','APPROVAL_PENDING','REJECTED','WITHDRAWN','APPROVED','CANCEL_PENDING','CANCELLED'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='휴가 신청 및 승인 후 취소 문서 헤더';

CREATE TABLE `institution_leave_request_items` (
  `id` char(36) NOT NULL, `leave_request_id` char(36) NOT NULL, `leave_type_id` char(36) NOT NULL, `sort_no` int unsigned NOT NULL,
  `leave_date` date NOT NULL, `request_unit_code` varchar(20) NOT NULL, `requested_start_at` datetime DEFAULT NULL, `requested_end_at` datetime DEFAULT NULL,
  `requested_minutes` int unsigned NOT NULL, `deductible_minutes` int unsigned NOT NULL,
  `employment_contract_id` char(36) NOT NULL, `scheduled_start_at_snapshot` datetime NOT NULL, `scheduled_end_at_snapshot` datetime NOT NULL,
  `scheduled_minutes_snapshot` int unsigned NOT NULL, `breaks_json_snapshot` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`breaks_json_snapshot`)),
  `created_at` datetime NOT NULL, `created_by` varchar(100) NOT NULL,
  PRIMARY KEY (`id`), UNIQUE KEY `uk_leave_request_item_sort` (`leave_request_id`,`sort_no`),
  KEY `idx_leave_request_item_date_employee` (`leave_date`,`leave_request_id`), KEY `idx_leave_request_item_type_date` (`leave_type_id`,`leave_date`),
  CONSTRAINT `fk_leave_request_item_request` FOREIGN KEY (`leave_request_id`) REFERENCES `institution_leave_requests` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_leave_request_item_type` FOREIGN KEY (`leave_type_id`) REFERENCES `institution_leave_types` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_leave_request_item_contract` FOREIGN KEY (`employment_contract_id`) REFERENCES `institution_employment_contracts` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `chk_leave_request_item_unit` CHECK (`request_unit_code` IN ('FULL_DAY','AM_HALF','PM_HALF','HOURLY')),
  CONSTRAINT `chk_leave_request_item_minutes` CHECK (`requested_minutes`>0 AND `deductible_minutes`>=0),
  CONSTRAINT `chk_leave_request_item_hourly` CHECK ((`request_unit_code`='HOURLY' AND `requested_start_at` IS NOT NULL AND `requested_end_at`>`requested_start_at`) OR (`request_unit_code`<>'HOURLY' AND `requested_start_at` IS NULL AND `requested_end_at` IS NULL))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='휴가 신청 날짜별 사용구간 및 계산 스냅샷';

CREATE TABLE `institution_leave_usages` (
  `id` char(36) NOT NULL, `request_item_id` char(36) NOT NULL, `employee_id` char(36) NOT NULL, `leave_type_id` char(36) NOT NULL,
  `leave_date` date NOT NULL, `request_unit_code` varchar(20) NOT NULL, `leave_start_at` datetime NOT NULL, `leave_end_at` datetime NOT NULL,
  `used_minutes` int unsigned NOT NULL, `deductible_minutes` int unsigned NOT NULL, `usage_status_code` varchar(20) NOT NULL DEFAULT 'ACTIVE',
  `cancel_request_id` char(36) DEFAULT NULL, `approved_at` datetime NOT NULL, `approval_request_id` char(36) NOT NULL,
  `created_at` datetime NOT NULL, `created_by` varchar(100) NOT NULL, `updated_at` datetime NOT NULL, `updated_by` varchar(100) NOT NULL,
  PRIMARY KEY (`id`), UNIQUE KEY `uk_leave_usage_request_item` (`request_item_id`), KEY `idx_leave_usage_employee_date` (`employee_id`,`leave_date`,`usage_status_code`),
  CONSTRAINT `fk_leave_usage_item` FOREIGN KEY (`request_item_id`) REFERENCES `institution_leave_request_items` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_leave_usage_employee` FOREIGN KEY (`employee_id`) REFERENCES `user_employees` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_leave_usage_type` FOREIGN KEY (`leave_type_id`) REFERENCES `institution_leave_types` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_leave_usage_cancel_request` FOREIGN KEY (`cancel_request_id`) REFERENCES `institution_leave_requests` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_leave_usage_approval` FOREIGN KEY (`approval_request_id`) REFERENCES `user_approval_requests` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `chk_leave_usage_status` CHECK (`usage_status_code` IN ('ACTIVE','CANCELLED')), CONSTRAINT `chk_leave_usage_minutes` CHECK (`used_minutes`>0 AND `deductible_minutes`>=0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='최종 승인된 휴가 사용 SSOT';

CREATE TABLE `institution_leave_ledger_entries` (
  `id` char(36) NOT NULL, `employee_id` char(36) NOT NULL, `leave_type_id` char(36) NOT NULL, `base_year` smallint unsigned NOT NULL,
  `entry_type_code` varchar(20) NOT NULL, `amount_minutes` int NOT NULL, `source_domain_code` varchar(20) NOT NULL, `source_id` char(36) NOT NULL,
  `source_sequence` int unsigned NOT NULL DEFAULT 1, `occurred_on` date NOT NULL, `reason` varchar(500) NOT NULL, `request_key` varchar(191) NOT NULL,
  `created_at` datetime NOT NULL, `created_by` varchar(100) NOT NULL,
  PRIMARY KEY (`id`), UNIQUE KEY `uk_leave_ledger_source` (`source_domain_code`,`source_id`,`source_sequence`), UNIQUE KEY `uk_leave_ledger_request` (`request_key`),
  KEY `idx_leave_ledger_balance` (`employee_id`,`leave_type_id`,`base_year`,`occurred_on`),
  CONSTRAINT `fk_leave_ledger_employee` FOREIGN KEY (`employee_id`) REFERENCES `user_employees` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_leave_ledger_type` FOREIGN KEY (`leave_type_id`) REFERENCES `institution_leave_types` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `chk_leave_ledger_type` CHECK (`entry_type_code` IN ('GRANT','USAGE','RESTORE','ADJUSTMENT','CARRYOVER','EXPIRATION')),
  CONSTRAINT `chk_leave_ledger_source` CHECK (`source_domain_code` IN ('GRANT','USAGE','CANCELLATION','ADJUSTMENT')),
  CONSTRAINT `chk_leave_ledger_amount` CHECK (`amount_minutes`<>0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='휴가 잔액 증감 불변 원장 SSOT';

CREATE TABLE `institution_leave_audits` (
  `id` char(36) NOT NULL, `leave_domain` varchar(30) NOT NULL, `target_id` char(36) NOT NULL, `employee_id` char(36) NOT NULL,
  `action_type_code` varchar(30) NOT NULL, `source_type_code` varchar(30) NOT NULL, `reason` varchar(500) NOT NULL,
  `approval_request_id` char(36) DEFAULT NULL, `request_key` varchar(191) NOT NULL,
  `before_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`before_data`)),
  `after_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`after_data`)),
  `processed_by` varchar(100) NOT NULL, `processed_at` datetime NOT NULL,
  PRIMARY KEY (`id`), UNIQUE KEY `uk_leave_audit_request` (`request_key`), KEY `idx_leave_audit_target` (`leave_domain`,`target_id`,`processed_at`), KEY `idx_leave_audit_employee` (`employee_id`,`processed_at`),
  CONSTRAINT `fk_leave_audit_employee` FOREIGN KEY (`employee_id`) REFERENCES `user_employees` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_leave_audit_approval` FOREIGN KEY (`approval_request_id`) REFERENCES `user_approval_requests` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='휴가 업무 변경 전용 감사 증적';

INSERT INTO `institution_leave_types` (`id`,`type_code`,`type_name`,`is_paid`,`deducts_balance`,`requires_approval`,`evidence_policy_code`,`allowed_units_json`,`is_active`,`sort_no`,`created_at`,`created_by`,`updated_at`,`updated_by`) VALUES
(UUID(),'ANNUAL_PAID','연차유급휴가',1,1,1,'OPTIONAL','["FULL_DAY","AM_HALF","PM_HALF","HOURLY"]',1,1,NOW(),'SYSTEM:MIGRATION',NOW(),'SYSTEM:MIGRATION'),
(UUID(),'SUMMER','여름휴가',1,1,1,'OPTIONAL','["FULL_DAY"]',1,2,NOW(),'SYSTEM:MIGRATION',NOW(),'SYSTEM:MIGRATION'),
(UUID(),'FAMILY_EVENT','경조휴가',1,1,1,'OPTIONAL','["FULL_DAY"]',1,3,NOW(),'SYSTEM:MIGRATION',NOW(),'SYSTEM:MIGRATION'),
(UUID(),'SICK','병가',0,0,1,'OPTIONAL','["FULL_DAY","AM_HALF","PM_HALF","HOURLY"]',1,4,NOW(),'SYSTEM:MIGRATION',NOW(),'SYSTEM:MIGRATION'),
(UUID(),'UNPAID','무급휴가',0,0,1,'NONE','["FULL_DAY","AM_HALF","PM_HALF","HOURLY"]',1,5,NOW(),'SYSTEM:MIGRATION',NOW(),'SYSTEM:MIGRATION'),
(UUID(),'PUBLIC_DUTY','공가',1,0,1,'OPTIONAL','["FULL_DAY","AM_HALF","PM_HALF","HOURLY"]',1,6,NOW(),'SYSTEM:MIGRATION',NOW(),'SYSTEM:MIGRATION'),
(UUID(),'COMPENSATORY','보상휴가',1,1,1,'OPTIONAL','["FULL_DAY","AM_HALF","PM_HALF","HOURLY"]',1,7,NOW(),'SYSTEM:MIGRATION',NOW(),'SYSTEM:MIGRATION'),
(UUID(),'OTHER','기타 회사약정휴가',0,0,1,'OPTIONAL','["FULL_DAY","AM_HALF","PM_HALF","HOURLY"]',1,8,NOW(),'SYSTEM:MIGRATION',NOW(),'SYSTEM:MIGRATION');

INSERT INTO `system_codes` (`id`,`code_group`,`group_name`,`code`,`code_name`,`sort_no`,`is_active`,`created_at`,`created_by`,`updated_at`,`updated_by`) SELECT UUID(),v.g,v.gn,v.c,v.cn,v.s,1,NOW(),'SYSTEM:MIGRATION',NOW(),'SYSTEM:MIGRATION' FROM (
 SELECT 'LEAVE_REQUEST_UNIT' g,'휴가 신청단위' gn,'FULL_DAY' c,'전일' cn,1 s UNION ALL SELECT 'LEAVE_REQUEST_UNIT','휴가 신청단위','AM_HALF','오전 반차',2 UNION ALL SELECT 'LEAVE_REQUEST_UNIT','휴가 신청단위','PM_HALF','오후 반차',3 UNION ALL SELECT 'LEAVE_REQUEST_UNIT','휴가 신청단위','HOURLY','시간차',4 UNION ALL
 SELECT 'LEAVE_REQUEST_STATUS','휴가 신청상태','DRAFT','임시저장',1 UNION ALL SELECT 'LEAVE_REQUEST_STATUS','휴가 신청상태','APPROVAL_PENDING','결재대기',2 UNION ALL SELECT 'LEAVE_REQUEST_STATUS','휴가 신청상태','REJECTED','반려',3 UNION ALL SELECT 'LEAVE_REQUEST_STATUS','휴가 신청상태','WITHDRAWN','회수',4 UNION ALL SELECT 'LEAVE_REQUEST_STATUS','휴가 신청상태','APPROVED','승인',5 UNION ALL SELECT 'LEAVE_REQUEST_STATUS','휴가 신청상태','CANCEL_PENDING','취소결재대기',6 UNION ALL SELECT 'LEAVE_REQUEST_STATUS','휴가 신청상태','CANCELLED','취소',7
) v WHERE NOT EXISTS(SELECT 1 FROM system_codes c WHERE c.code_group=v.g AND c.code=v.c);
