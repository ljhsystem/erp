-- 개발환경 전용 구조 Down. 운영 데이터 생성 후에는 forward-only 보정 Migration을 사용한다.
DELETE FROM `system_codes` WHERE (`code_group`,`code`) IN (('QUALIFICATION_STATUS','INVALIDATED'),('EDUCATION_COMPLETION_STATUS','INVALIDATED'));
DROP TABLE IF EXISTS `institution_qualification_education_policy_audits`;
DROP TABLE IF EXISTS `institution_educations_job_requirements`;
DROP TABLE IF EXISTS `institution_qualifications_job_requirements`;
ALTER TABLE `institution_educations_employee_records` DROP INDEX `idx_education_record_employee_course_validity`, DROP INDEX `idx_education_record_course_completion_date`;
ALTER TABLE `institution_educations_courses` DROP CONSTRAINT `chk_education_course_recurrence`, DROP INDEX `idx_education_course_statutory_active`, DROP INDEX `idx_education_course_recurrence_active`, DROP COLUMN `statutory_standard_type_code`, DROP COLUMN `recurrence_event_code`, DROP COLUMN `recurrence_interval_unit_code`, DROP COLUMN `recurrence_interval_value`, DROP COLUMN `recurrence_policy_code`;
ALTER TABLE `institution_qualifications_employee_records` DROP FOREIGN KEY `fk_qualification_record_type`, DROP INDEX `idx_qualification_record_type_employee_validity`, DROP COLUMN `qualification_type_id`;
DROP TABLE IF EXISTS `institution_qualifications_types`;
