SET @weekly_holiday_rollback_invalid_count := (
  SELECT COUNT(*) FROM (
    SELECT c.`id`
    FROM `institution_employment_contracts` c
    LEFT JOIN `institution_employment_contract_weekly_schedules` s
      ON s.`contract_id` = c.`id` AND s.`day_type` = 'WEEKLY_HOLIDAY'
    WHERE c.`work_schedule_type` IN ('NORMAL','NIGHT')
    GROUP BY c.`id`
    HAVING COUNT(s.`id`) <> 1
  ) invalid_contracts
);
SET @weekly_holiday_rollback_invalid_sql := IF(@weekly_holiday_rollback_invalid_count = 0,
  'SELECT 1',
  'SIGNAL SQLSTATE ''45000'' SET MESSAGE_TEXT = ''WEEKLY_HOLIDAY 복구 대상이 계약당 1건이 아니어서 롤백을 중단합니다.''');
PREPARE weekly_holiday_rollback_invalid_statement FROM @weekly_holiday_rollback_invalid_sql;
EXECUTE weekly_holiday_rollback_invalid_statement;
DEALLOCATE PREPARE weekly_holiday_rollback_invalid_statement;

UPDATE `institution_employment_contracts` c
JOIN `institution_employment_contract_weekly_schedules` s
  ON s.`contract_id` = c.`id` AND s.`day_type` = 'WEEKLY_HOLIDAY'
SET c.`weekly_holiday_day` = s.`day_of_week`
WHERE c.`work_schedule_type` IN ('NORMAL','NIGHT');

DELETE s
FROM `institution_employment_contract_weekly_schedules` s
JOIN `institution_employment_contracts` c ON c.`id` = s.`contract_id`
WHERE s.`day_type` = 'WEEKLY_HOLIDAY'
  AND s.`id` = LOWER(CONCAT(
    SUBSTR(MD5(CONCAT('weekly-holiday-backfill:', c.`id`)), 1, 8), '-',
    SUBSTR(MD5(CONCAT('weekly-holiday-backfill:', c.`id`)), 9, 4), '-',
    SUBSTR(MD5(CONCAT('weekly-holiday-backfill:', c.`id`)), 13, 4), '-',
    SUBSTR(MD5(CONCAT('weekly-holiday-backfill:', c.`id`)), 17, 4), '-',
    SUBSTR(MD5(CONCAT('weekly-holiday-backfill:', c.`id`)), 21, 12)
  ));

-- Up Migration은 기존 UNPAID_DAY_OFF만 WEEKLY_HOLIDAY로 바꾸므로 원래 상태로 복원한다.
UPDATE `institution_employment_contract_weekly_schedules`
SET `day_type` = 'UNPAID_DAY_OFF'
WHERE `day_type` = 'WEEKLY_HOLIDAY';

ALTER TABLE `institution_employment_contract_weekly_schedules`
  DROP CONSTRAINT IF EXISTS `chk_contract_weekly_schedule_type`,
  ADD CONSTRAINT `chk_contract_weekly_schedule_type`
    CHECK (`day_type` IN ('WORKDAY','UNPAID_DAY_OFF','COMPANY_PAID_HOLIDAY'));

ALTER TABLE `institution_employment_contract_weekly_schedules`
  MODIFY `id` varchar(36) NOT NULL COMMENT '',
  MODIFY `contract_id` varchar(36) NOT NULL COMMENT '',
  MODIFY `day_of_week` tinyint unsigned NOT NULL COMMENT '',
  MODIFY `day_type` varchar(30) NOT NULL COMMENT '',
  MODIFY `start_time` time NULL COMMENT '',
  MODIFY `end_time` time NULL COMMENT '',
  MODIFY `end_day_offset` tinyint unsigned NULL COMMENT '',
  MODIFY `break_minutes` int NULL COMMENT '',
  MODIFY `created_at` datetime NOT NULL COMMENT '',
  MODIFY `created_by` varchar(100) NOT NULL COMMENT '',
  MODIFY `updated_at` datetime NULL COMMENT '',
  MODIFY `updated_by` varchar(100) NULL COMMENT '';