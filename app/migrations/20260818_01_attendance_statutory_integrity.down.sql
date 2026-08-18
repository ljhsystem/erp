SET @attendance_daily_count := (SELECT COUNT(*) FROM `institution_attendance_daily_records`);
SET @attendance_down_sql := IF(@attendance_daily_count=0,'SELECT 1','SIGNAL SQLSTATE ''45000'' SET MESSAGE_TEXT=''근태 일별 데이터가 있어 법정기준 추적 컬럼을 제거할 수 없습니다.''');
PREPARE attendance_down_statement FROM @attendance_down_sql;
EXECUTE attendance_down_statement;
DEALLOCATE PREPARE attendance_down_statement;

ALTER TABLE `institution_attendance_daily_exceptions`
  DROP CONSTRAINT `chk_attendance_exception_type`,
  ADD CONSTRAINT `chk_attendance_exception_type` CHECK (`exception_type_code` in ('LATE','EARLY_LEAVE','ABSENT','MISSING_CLOCK_IN','MISSING_CLOCK_OUT','NO_SCHEDULE','CONTRACT_CONFLICT','LEAVE_PERIOD_CONFLICT'));

ALTER TABLE `institution_attendance_daily_records`
  DROP FOREIGN KEY `fk_attendance_daily_working_standard`,
  DROP FOREIGN KEY `fk_attendance_daily_holiday_standard`,
  DROP INDEX `idx_attendance_daily_working_standard`,
  DROP INDEX `idx_attendance_daily_holiday_standard`,
  DROP COLUMN `working_time_standard_id`,
  DROP COLUMN `public_holiday_standard_id`,
  DROP COLUMN `contract_excess_seconds`;

DELETE c FROM `system_codes` c
WHERE c.`code_group`='ATTENDANCE_EXCEPTION_TYPE' AND c.`code` IN ('DUPLICATE_CLOCK_IN','DUPLICATE_CLOCK_OUT');
DELETE c FROM `system_codes` c
WHERE c.`code_group`='STATUTORY_STANDARD_TYPE' AND c.`code` IN ('WORKING_TIME_STANDARD','PUBLIC_HOLIDAY_CALENDAR')
AND NOT EXISTS (SELECT 1 FROM `system_statutory_standards` s WHERE s.`standard_type_code`=c.`code`);
