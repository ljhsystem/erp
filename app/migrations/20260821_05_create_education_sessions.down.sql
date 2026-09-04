DELETE FROM `system_codes`
WHERE (`code_group`='EDUCATION_SESSION_STATUS' AND `code` IN ('DRAFT','SCHEDULED','CANCELLED','COMPLETED'))
   OR (`code_group`='EDUCATION_TARGET_SOURCE' AND `code` IN ('INDIVIDUAL','DEPARTMENT','JOB','ALL_EMPLOYEES','PROJECT','STATUTORY_RULE'))
   OR (`code_group`='EDUCATION_ATTENDANCE_STATUS' AND `code`='NOT_RECORDED');

ALTER TABLE `institution_educations_employee_records`
  DROP FOREIGN KEY `fk_education_record_session`,
  DROP INDEX `idx_education_record_session`,
  DROP INDEX `uk_education_record_session_employee`,
  DROP COLUMN `session_id`;

DROP TABLE `institution_educations_session_targets`;
DROP TABLE `institution_educations_sessions`;
