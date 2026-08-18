DROP TABLE `institution_employment_contract_work_schedule_policies`;
DROP TABLE `institution_employment_contract_weekly_schedules`;
SET @projection_null_count := (SELECT COUNT(*) FROM `institution_employment_contracts` WHERE `weekly_work_days` IS NULL OR `weekly_work_hours` IS NULL OR `daily_work_hours` IS NULL OR `break_minutes` IS NULL);
SET @projection_rollback_sql := IF(@projection_null_count = 0,
  'ALTER TABLE institution_employment_contracts MODIFY weekly_work_days decimal(4,2) NOT NULL, MODIFY weekly_work_hours decimal(6,2) NOT NULL, MODIFY daily_work_hours decimal(5,2) NOT NULL, MODIFY standard_start_time time NULL, MODIFY standard_end_time time NULL, MODIFY break_minutes int(11) NOT NULL DEFAULT 0',
  'SIGNAL SQLSTATE ''45000'' SET MESSAGE_TEXT = ''헤더 projection NULL 데이터가 있어 NOT NULL 롤백을 중단합니다.''');
PREPARE projection_rollback_statement FROM @projection_rollback_sql;
EXECUTE projection_rollback_statement;
DEALLOCATE PREPARE projection_rollback_statement;
