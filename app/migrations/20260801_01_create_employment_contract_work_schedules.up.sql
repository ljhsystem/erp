ALTER TABLE `institution_employment_contracts`
  MODIFY `weekly_work_days` decimal(4,2) NULL,
  MODIFY `weekly_work_hours` decimal(6,2) NULL,
  MODIFY `daily_work_hours` decimal(5,2) NULL,
  MODIFY `standard_start_time` time NULL,
  MODIFY `standard_end_time` time NULL,
  MODIFY `break_minutes` int(11) NULL;

CREATE TABLE `institution_employment_contract_weekly_schedules` (
  `id` varchar(36) NOT NULL, `contract_id` varchar(36) NOT NULL,
  `day_of_week` tinyint unsigned NOT NULL, `day_type` varchar(30) NOT NULL,
  `start_time` time NULL, `end_time` time NULL, `end_day_offset` tinyint unsigned NULL,
  `break_minutes` int NULL, `created_at` datetime NOT NULL, `created_by` varchar(100) NOT NULL,
  `updated_at` datetime NULL, `updated_by` varchar(100) NULL,
  PRIMARY KEY (`id`), UNIQUE KEY `uk_contract_weekly_schedule_day` (`contract_id`,`day_of_week`),
  KEY `idx_contract_weekly_schedule_contract` (`contract_id`), KEY `idx_contract_weekly_schedule_type` (`day_type`),
  CONSTRAINT `fk_contract_weekly_schedule_contract` FOREIGN KEY (`contract_id`) REFERENCES `institution_employment_contracts` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `chk_contract_weekly_schedule_day` CHECK (`day_of_week` between 1 and 7),
  CONSTRAINT `chk_contract_weekly_schedule_type` CHECK (`day_type` in ('WORKDAY','UNPAID_DAY_OFF','COMPANY_PAID_HOLIDAY')),
  CONSTRAINT `chk_contract_weekly_schedule_offset` CHECK (`end_day_offset` is null or `end_day_offset` in (0,1)),
  CONSTRAINT `chk_contract_weekly_schedule_break` CHECK (`break_minutes` is null or `break_minutes` between 0 and 1440),
  CONSTRAINT `chk_contract_weekly_schedule_state` CHECK ((`day_type`='WORKDAY' and `start_time` is not null and `end_time` is not null and `end_day_offset` is not null and `break_minutes` is not null) or (`day_type`<>'WORKDAY' and `start_time` is null and `end_time` is null and `end_day_offset` is null and `break_minutes` is null))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='근로계약 일반·야간 주간반복일정';

CREATE TABLE `institution_employment_contract_work_schedule_policies` (
  `id` varchar(36) NOT NULL, `contract_id` varchar(36) NOT NULL, `schedule_type` varchar(30) NOT NULL,
  `settlement_period_days` smallint unsigned NULL, `reference_weekly_hours` decimal(6,2) NULL,
  `selectable_start_time` time NULL, `selectable_end_time` time NULL,
  `core_start_time` time NULL, `core_end_time` time NULL, `policy_detail` text NOT NULL,
  `created_at` datetime NOT NULL, `created_by` varchar(100) NOT NULL, `updated_at` datetime NULL, `updated_by` varchar(100) NULL,
  PRIMARY KEY (`id`), UNIQUE KEY `uk_contract_work_schedule_policy` (`contract_id`),
  KEY `idx_contract_work_schedule_policy_type` (`schedule_type`),
  CONSTRAINT `fk_contract_work_schedule_policy_contract` FOREIGN KEY (`contract_id`) REFERENCES `institution_employment_contracts` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `chk_contract_work_schedule_policy_type` CHECK (`schedule_type` in ('SELECTIVE','SHIFT','FLEXIBLE','OTHER')),
  CONSTRAINT `chk_contract_work_schedule_policy_period` CHECK (`settlement_period_days` is null or `settlement_period_days` > 0),
  CONSTRAINT `chk_contract_work_schedule_policy_hours` CHECK (`reference_weekly_hours` is null or (`reference_weekly_hours` > 0 and `reference_weekly_hours` <= 168)),
  CONSTRAINT `chk_contract_work_schedule_policy_core` CHECK ((`core_start_time` is null and `core_end_time` is null) or (`core_start_time` is not null and `core_end_time` is not null and `core_end_time` > `core_start_time`))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='근로계약 비고정 근무형태 정책';
