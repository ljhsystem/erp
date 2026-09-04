CREATE TABLE `institution_payroll_policies` (
  `id` char(36) NOT NULL COMMENT '식별자',
  `company_id` varchar(50) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL COMMENT '회사 식별자',
  `effective_from` date NOT NULL COMMENT '적용 시작일',
  `effective_to` date DEFAULT NULL COMMENT '적용 종료일',
  `period_basis_code` varchar(30) NOT NULL COMMENT '급여 산정기간 기준',
  `period_start_policy_code` varchar(30) NOT NULL COMMENT '산정기간 시작정책',
  `period_end_policy_code` varchar(30) NOT NULL COMMENT '산정기간 종료정책',
  `payment_timing` varchar(20) NOT NULL COMMENT '지급월 기준',
  `payment_day` tinyint unsigned NOT NULL COMMENT '약정 지급일',
  `missing_day_policy_code` varchar(30) NOT NULL COMMENT '없는 날짜 처리정책',
  `non_business_day_policy_code` varchar(30) NOT NULL COMMENT '비지급가능일 보정정책',
  `business_calendar_policy_code` varchar(40) NOT NULL COMMENT '지급가능일 캘린더 정책',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '생성일시',
  `created_by` varchar(100) NOT NULL COMMENT '생성자',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_payroll_policy_company_effective` (`company_id`,`effective_from`),
  KEY `idx_payroll_policy_effective` (`company_id`,`effective_from`,`effective_to`),
  CONSTRAINT `fk_payroll_policy_company` FOREIGN KEY (`company_id`) REFERENCES `system_company` (`id`),
  CONSTRAINT `chk_payroll_policy_period` CHECK (`effective_to` IS NULL OR `effective_to` >= `effective_from`),
  CONSTRAINT `chk_payroll_policy_payment_day` CHECK (`payment_day` BETWEEN 1 AND 31),
  CONSTRAINT `chk_payroll_policy_monthly_timing` CHECK (`payment_timing` IN ('CURRENT_MONTH','NEXT_MONTH'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='회사 기본 급여 산정·지급정책';

ALTER TABLE `institution_employment_contracts`
  ADD COLUMN `payroll_period_basis_code` varchar(30) NULL COMMENT '계약 당시 급여 산정기간 기준' AFTER `salary_type`,
  ADD COLUMN `payroll_period_start_policy_code` varchar(30) NULL COMMENT '계약 당시 산정기간 시작정책' AFTER `payroll_period_basis_code`,
  ADD COLUMN `payroll_period_end_policy_code` varchar(30) NULL COMMENT '계약 당시 산정기간 종료정책' AFTER `payroll_period_start_policy_code`,
  ADD COLUMN `payment_missing_day_policy_code` varchar(30) NULL COMMENT '계약 당시 없는 지급일 처리정책' AFTER `payment_timing`,
  ADD COLUMN `payment_non_business_day_policy_code` varchar(30) NULL COMMENT '계약 당시 비지급가능일 보정정책' AFTER `payment_missing_day_policy_code`,
  ADD COLUMN `payment_calendar_policy_code` varchar(40) NULL COMMENT '계약 당시 지급가능일 캘린더 정책' AFTER `payment_non_business_day_policy_code`,
  ADD KEY `idx_employment_contract_payroll_policy` (`payroll_period_basis_code`,`payment_timing`,`payment_day`),
  ADD CONSTRAINT `chk_employment_contract_payroll_period_basis` CHECK (`payroll_period_basis_code` IS NULL OR `payroll_period_basis_code`='CALENDAR_MONTH'),
  ADD CONSTRAINT `chk_employment_contract_payroll_period_start` CHECK (`payroll_period_start_policy_code` IS NULL OR `payroll_period_start_policy_code`='FIRST_DAY'),
  ADD CONSTRAINT `chk_employment_contract_payroll_period_end` CHECK (`payroll_period_end_policy_code` IS NULL OR `payroll_period_end_policy_code`='MONTH_END'),
  ADD CONSTRAINT `chk_employment_contract_payment_missing_day` CHECK (`payment_missing_day_policy_code` IS NULL OR `payment_missing_day_policy_code` IN ('LAST_DAY','BLOCKED')),
  ADD CONSTRAINT `chk_employment_contract_payment_non_business` CHECK (`payment_non_business_day_policy_code` IS NULL OR `payment_non_business_day_policy_code` IN ('NONE','PREVIOUS_BUSINESS_DAY','BLOCKED')),
  ADD CONSTRAINT `chk_employment_contract_payment_calendar` CHECK (`payment_calendar_policy_code` IS NULL OR `payment_calendar_policy_code`='WEEKEND_AND_PUBLIC_HOLIDAY');

ALTER TABLE `institution_regular_employment_incomes`
  ADD COLUMN `payroll_period_start_date` date NULL COMMENT '계약정책 산정기간 시작일' AFTER `income_year_month`,
  ADD COLUMN `payroll_period_end_date` date NULL COMMENT '계약정책 산정기간 종료일' AFTER `payroll_period_start_date`,
  ADD COLUMN `nominal_payment_date` date NULL COMMENT '휴일보정 전 명목 지급일' AFTER `payroll_period_end_date`,
  ADD COLUMN `proposed_payment_date` date NULL COMMENT '계약정책 자동제안 지급일' AFTER `nominal_payment_date`,
  ADD COLUMN `payment_date_override_reason` varchar(500) NULL COMMENT '자동제안 지급일 변경사유' AFTER `payment_date`,
  ADD CONSTRAINT `chk_regular_income_payment_override` CHECK (`proposed_payment_date` IS NULL OR `payment_date`=`proposed_payment_date` OR CHAR_LENGTH(TRIM(`payment_date_override_reason`))>0);

ALTER TABLE `institution_regular_employment_income_audits`
  DROP CONSTRAINT `chk_regular_income_audit_action`,
  ADD CONSTRAINT `chk_regular_income_audit_action` CHECK (`action_code` IN ('CREATE','CALCULATE','RECALCULATE','ADJUST','SUBMIT','WITHDRAW','APPROVE','ACCOUNTING_MATERIALIZE','CORRECT','CANCEL','PAYMENT_DATE_OVERRIDE'));

INSERT INTO `system_codes` (`id`,`sort_no`,`code_group`,`group_name`,`code`,`code_name`,`extra_data`,`is_active`,`created_at`,`created_by`,`updated_at`,`updated_by`)
SELECT UUID(), b.base_sort + ROW_NUMBER() OVER (ORDER BY v.code_group,v.code), v.code_group, v.group_name, v.code, v.code_name, NULL, 1, NOW(), 'SYSTEM:MIGRATION', NOW(), 'SYSTEM:MIGRATION'
FROM (
  SELECT 'PAYROLL_PERIOD_BASIS' code_group,'급여 산정기간 기준' group_name,'CALENDAR_MONTH' code,'매월' code_name
  UNION ALL SELECT 'PAYROLL_PERIOD_START_POLICY','급여 산정기간 시작정책','FIRST_DAY','1일'
  UNION ALL SELECT 'PAYROLL_PERIOD_END_POLICY','급여 산정기간 종료정책','MONTH_END','말일'
  UNION ALL SELECT 'PAYMENT_MISSING_DAY_POLICY','없는 지급일 처리정책','LAST_DAY','해당 월 말일'
  UNION ALL SELECT 'PAYMENT_MISSING_DAY_POLICY','없는 지급일 처리정책','BLOCKED','자동계산 차단'
  UNION ALL SELECT 'PAYMENT_NON_BUSINESS_DAY_POLICY','비지급가능일 보정정책','NONE','보정하지 않음'
  UNION ALL SELECT 'PAYMENT_NON_BUSINESS_DAY_POLICY','비지급가능일 보정정책','PREVIOUS_BUSINESS_DAY','직전 지급가능일'
  UNION ALL SELECT 'PAYMENT_NON_BUSINESS_DAY_POLICY','비지급가능일 보정정책','BLOCKED','자동계산 차단'
  UNION ALL SELECT 'PAYMENT_BUSINESS_CALENDAR_POLICY','지급가능일 캘린더 정책','WEEKEND_AND_PUBLIC_HOLIDAY','주말·법정공휴일 제외'
) v
CROSS JOIN (SELECT COALESCE(MAX(`sort_no`),0) base_sort FROM `system_codes`) b
WHERE NOT EXISTS (SELECT 1 FROM `system_codes` c WHERE c.`code_group`=v.code_group AND c.`code`=v.code)
ORDER BY v.code_group,v.code;

INSERT INTO `institution_payroll_policies` (`id`,`company_id`,`effective_from`,`effective_to`,`period_basis_code`,`period_start_policy_code`,`period_end_policy_code`,`payment_timing`,`payment_day`,`missing_day_policy_code`,`non_business_day_policy_code`,`business_calendar_policy_code`,`created_by`)
SELECT UUID(), c.`id`, '2026-01-01', NULL, 'CALENDAR_MONTH', 'FIRST_DAY', 'MONTH_END', 'NEXT_MONTH', 11, 'LAST_DAY', 'PREVIOUS_BUSINESS_DAY', 'WEEKEND_AND_PUBLIC_HOLIDAY', 'SYSTEM:MIGRATION'
FROM `system_company` c
WHERE NOT EXISTS (SELECT 1 FROM `institution_payroll_policies` p WHERE p.`company_id`=c.`id` AND p.`effective_from`='2026-01-01');
