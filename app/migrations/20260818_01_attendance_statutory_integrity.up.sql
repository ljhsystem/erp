INSERT INTO `system_codes` (`id`,`sort_no`,`code_group`,`group_name`,`code`,`code_name`,`extra_data`,`note`,`is_active`,`created_at`,`created_by`,`updated_at`,`updated_by`)
SELECT 'a7d18001-0000-4000-8000-000000000001',420,'STATUTORY_STANDARD_TYPE','법정기준 종류','WORKING_TIME_STANDARD','근로시간 법정기준',
'{"fields":[{"code":"daily_legal_work_seconds","name":"일 법정근로시간","type":"number","required":true,"min":1,"unit_label":"초"},{"code":"weekly_legal_work_seconds","name":"주 법정근로시간","type":"number","required":true,"min":1,"unit_label":"초"},{"code":"week_start_day","name":"주 시작요일","type":"select","required":true,"options":[{"value":"1","label":"월요일"},{"value":"2","label":"화요일"},{"value":"3","label":"수요일"},{"value":"4","label":"목요일"},{"value":"5","label":"금요일"},{"value":"6","label":"토요일"},{"value":"7","label":"일요일"}]},{"code":"night_start_time","name":"야간근로 시작시각","type":"text","required":true},{"code":"night_end_time","name":"야간근로 종료시각","type":"text","required":true}]}' ,
'실제 기준값은 적용기간과 공식 근거자료를 포함하여 법정기준관리에서 등록',1,NOW(),'SYSTEM:MIGRATION',NOW(),'SYSTEM:MIGRATION'
WHERE NOT EXISTS (SELECT 1 FROM `system_codes` WHERE `code_group`='STATUTORY_STANDARD_TYPE' AND `code`='WORKING_TIME_STANDARD');

INSERT INTO `system_codes` (`id`,`sort_no`,`code_group`,`group_name`,`code`,`code_name`,`extra_data`,`note`,`is_active`,`created_at`,`created_by`,`updated_at`,`updated_by`)
SELECT 'a7d18001-0000-4000-8000-000000000002',421,'STATUTORY_STANDARD_TYPE','법정기준 종류','PUBLIC_HOLIDAY_CALENDAR','법정공휴일 캘린더',
'{"fields":[{"code":"holidays","name":"공식 법정공휴일 목록","type":"matrix","required":true,"columns":[{"code":"date","name":"휴일 날짜","type":"text","required":true,"key_part":true},{"code":"holiday_type","name":"휴일 유형","type":"select","required":true,"options":[{"value":"PUBLIC_HOLIDAY","label":"법정공휴일"},{"value":"SUBSTITUTE_PUBLIC_HOLIDAY","label":"대체공휴일"}]},{"code":"holiday_name","name":"휴일명","type":"text","required":true}],"ui":{"collapsible":false,"allow_paste":true,"title":"공식 법정공휴일 날짜 원장"}}]}' ,
'회사 지정휴일을 포함하지 않는 공식 법정공휴일 날짜 원장',1,NOW(),'SYSTEM:MIGRATION',NOW(),'SYSTEM:MIGRATION'
WHERE NOT EXISTS (SELECT 1 FROM `system_codes` WHERE `code_group`='STATUTORY_STANDARD_TYPE' AND `code`='PUBLIC_HOLIDAY_CALENDAR');

INSERT INTO `system_codes` (`id`,`sort_no`,`code_group`,`group_name`,`code`,`code_name`,`note`,`is_active`,`created_at`,`created_by`,`updated_at`,`updated_by`)
SELECT 'a7d18001-0000-4000-8000-000000000003',76,'ATTENDANCE_EXCEPTION_TYPE','근태 예외 유형','DUPLICATE_CLOCK_IN','연속 출근 중복','서로 다른 시각의 연속 출근 원본',1,NOW(),'SYSTEM:MIGRATION',NOW(),'SYSTEM:MIGRATION'
WHERE NOT EXISTS (SELECT 1 FROM `system_codes` WHERE `code_group`='ATTENDANCE_EXCEPTION_TYPE' AND `code`='DUPLICATE_CLOCK_IN');

INSERT INTO `system_codes` (`id`,`sort_no`,`code_group`,`group_name`,`code`,`code_name`,`note`,`is_active`,`created_at`,`created_by`,`updated_at`,`updated_by`)
SELECT 'a7d18001-0000-4000-8000-000000000004',77,'ATTENDANCE_EXCEPTION_TYPE','근태 예외 유형','DUPLICATE_CLOCK_OUT','연속 퇴근 중복','서로 다른 시각의 연속 퇴근 원본',1,NOW(),'SYSTEM:MIGRATION',NOW(),'SYSTEM:MIGRATION'
WHERE NOT EXISTS (SELECT 1 FROM `system_codes` WHERE `code_group`='ATTENDANCE_EXCEPTION_TYPE' AND `code`='DUPLICATE_CLOCK_OUT');

ALTER TABLE `institution_attendance_daily_records`
  ADD COLUMN `working_time_standard_id` char(36) NULL AFTER `employment_contract_id`,
  ADD COLUMN `public_holiday_standard_id` char(36) NULL AFTER `working_time_standard_id`,
  ADD COLUMN `contract_excess_seconds` int unsigned NOT NULL DEFAULT 0 AFTER `scheduled_work_seconds`,
  ADD KEY `idx_attendance_daily_working_standard` (`working_time_standard_id`),
  ADD KEY `idx_attendance_daily_holiday_standard` (`public_holiday_standard_id`),
  ADD CONSTRAINT `fk_attendance_daily_working_standard` FOREIGN KEY (`working_time_standard_id`) REFERENCES `system_statutory_standards` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_attendance_daily_holiday_standard` FOREIGN KEY (`public_holiday_standard_id`) REFERENCES `system_statutory_standards` (`id`) ON UPDATE CASCADE;

ALTER TABLE `institution_attendance_daily_exceptions`
  DROP CONSTRAINT `chk_attendance_exception_type`,
  ADD CONSTRAINT `chk_attendance_exception_type` CHECK (`exception_type_code` in ('LATE','EARLY_LEAVE','ABSENT','MISSING_CLOCK_IN','MISSING_CLOCK_OUT','NO_SCHEDULE','CONTRACT_CONFLICT','LEAVE_PERIOD_CONFLICT','DUPLICATE_CLOCK_IN','DUPLICATE_CLOCK_OUT'));
