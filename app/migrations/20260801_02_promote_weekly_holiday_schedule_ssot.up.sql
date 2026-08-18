SET @weekly_holiday_missing_header_count := (
  SELECT COUNT(*) FROM `institution_employment_contracts`
  WHERE `work_schedule_type` IN ('NORMAL','NIGHT') AND `weekly_holiday_day` IS NULL
);
SET @weekly_holiday_missing_header_sql := IF(@weekly_holiday_missing_header_count = 0,
  'SELECT 1',
  'SIGNAL SQLSTATE ''45000'' SET MESSAGE_TEXT = ''NORMAL/NIGHT 계약에 주휴일 projection이 없어 백필을 중단합니다.''');
PREPARE weekly_holiday_missing_header_statement FROM @weekly_holiday_missing_header_sql;
EXECUTE weekly_holiday_missing_header_statement;
DEALLOCATE PREPARE weekly_holiday_missing_header_statement;

SET @weekly_holiday_conflict_count := (
  SELECT COUNT(*)
  FROM `institution_employment_contracts` c
  JOIN `institution_employment_contract_weekly_schedules` s
    ON s.`contract_id` = c.`id` AND s.`day_of_week` = c.`weekly_holiday_day`
  WHERE c.`work_schedule_type` IN ('NORMAL','NIGHT')
    AND s.`day_type` IN ('WORKDAY','COMPANY_PAID_HOLIDAY')
);
SET @weekly_holiday_conflict_sql := IF(@weekly_holiday_conflict_count = 0,
  'SELECT 1',
  'SIGNAL SQLSTATE ''45000'' SET MESSAGE_TEXT = ''주휴일이 근무일 또는 회사 약정 유급휴일과 충돌하여 백필을 중단합니다.''');
PREPARE weekly_holiday_conflict_statement FROM @weekly_holiday_conflict_sql;
EXECUTE weekly_holiday_conflict_statement;
DEALLOCATE PREPARE weekly_holiday_conflict_statement;

ALTER TABLE `institution_employment_contract_weekly_schedules`
  DROP CONSTRAINT IF EXISTS `chk_contract_weekly_schedule_type`,
  ADD CONSTRAINT `chk_contract_weekly_schedule_type`
    CHECK (`day_type` IN ('WORKDAY','UNPAID_DAY_OFF','WEEKLY_HOLIDAY','COMPANY_PAID_HOLIDAY'));

ALTER TABLE `institution_employment_contract_weekly_schedules`
  MODIFY `id` varchar(36) NOT NULL COMMENT 'ID',
  MODIFY `contract_id` varchar(36) NOT NULL COMMENT '근로계약',
  MODIFY `day_of_week` tinyint unsigned NOT NULL COMMENT '요일',
  MODIFY `day_type` varchar(30) NOT NULL COMMENT '근무구분',
  MODIFY `start_time` time NULL COMMENT '출근시간',
  MODIFY `end_time` time NULL COMMENT '퇴근시간',
  MODIFY `end_day_offset` tinyint unsigned NULL COMMENT '퇴근일구분',
  MODIFY `break_minutes` int NULL COMMENT '휴게시간(분)',
  MODIFY `created_at` datetime NOT NULL COMMENT '생성일시',
  MODIFY `created_by` varchar(100) NOT NULL COMMENT '생성자',
  MODIFY `updated_at` datetime NULL COMMENT '수정일시',
  MODIFY `updated_by` varchar(100) NULL COMMENT '수정자';

INSERT INTO `institution_employment_contract_weekly_schedules`
  (`id`,`contract_id`,`day_of_week`,`day_type`,`start_time`,`end_time`,`end_day_offset`,`break_minutes`,`created_at`,`created_by`,`updated_at`,`updated_by`)
SELECT LOWER(CONCAT(
         SUBSTR(MD5(CONCAT('weekly-holiday-backfill:', c.`id`)), 1, 8), '-',
         SUBSTR(MD5(CONCAT('weekly-holiday-backfill:', c.`id`)), 9, 4), '-',
         SUBSTR(MD5(CONCAT('weekly-holiday-backfill:', c.`id`)), 13, 4), '-',
         SUBSTR(MD5(CONCAT('weekly-holiday-backfill:', c.`id`)), 17, 4), '-',
         SUBSTR(MD5(CONCAT('weekly-holiday-backfill:', c.`id`)), 21, 12)
       )), c.`id`, c.`weekly_holiday_day`, 'WEEKLY_HOLIDAY', NULL, NULL, NULL, NULL,
       CURRENT_TIMESTAMP, c.`created_by`, NULL, NULL
FROM `institution_employment_contracts` c
LEFT JOIN `institution_employment_contract_weekly_schedules` s
  ON s.`contract_id` = c.`id` AND s.`day_of_week` = c.`weekly_holiday_day`
WHERE c.`work_schedule_type` IN ('NORMAL','NIGHT')
  AND c.`weekly_holiday_day` BETWEEN 1 AND 7
  AND s.`id` IS NULL;

UPDATE `institution_employment_contract_weekly_schedules` s
JOIN `institution_employment_contracts` c
  ON c.`id` = s.`contract_id` AND c.`weekly_holiday_day` = s.`day_of_week`
SET s.`day_type` = 'WEEKLY_HOLIDAY',
    s.`start_time` = NULL, s.`end_time` = NULL,
    s.`end_day_offset` = NULL, s.`break_minutes` = NULL
WHERE c.`work_schedule_type` IN ('NORMAL','NIGHT')
  AND s.`day_type` = 'UNPAID_DAY_OFF';

SET @weekly_holiday_invalid_count := (
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
SET @weekly_holiday_invalid_sql := IF(@weekly_holiday_invalid_count = 0,
  'SELECT 1',
  'SIGNAL SQLSTATE ''45000'' SET MESSAGE_TEXT = ''계약당 WEEKLY_HOLIDAY가 정확히 1건이 아니어서 백필을 중단합니다.''');
PREPARE weekly_holiday_invalid_statement FROM @weekly_holiday_invalid_sql;
EXECUTE weekly_holiday_invalid_statement;
DEALLOCATE PREPARE weekly_holiday_invalid_statement;

UPDATE `institution_employment_contracts` c
JOIN `institution_employment_contract_weekly_schedules` s
  ON s.`contract_id` = c.`id` AND s.`day_type` = 'WEEKLY_HOLIDAY'
SET c.`weekly_holiday_day` = s.`day_of_week`
WHERE c.`work_schedule_type` IN ('NORMAL','NIGHT');