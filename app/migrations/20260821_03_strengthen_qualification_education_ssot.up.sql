-- 자격·교육 SSOT 보강. 운영 적용은 forward-only를 원칙으로 한다.
CREATE TABLE `institution_qualifications_types` (
  `id` char(36) NOT NULL COMMENT '자격 기준 식별자',
  `sort_no` int unsigned NOT NULL COMMENT '표시순서',
  `qualification_code` varchar(50) NOT NULL COMMENT '자격 코드',
  `qualification_name` varchar(150) NOT NULL COMMENT '자격명',
  `category_code` varchar(50) NOT NULL COMMENT '자격 분류 코드',
  `default_issuer_name` varchar(150) DEFAULT NULL COMMENT '기본 발급기관명',
  `validity_policy_code` varchar(30) NOT NULL DEFAULT 'RECORD_SPECIFIC' COMMENT '유효기간 정책',
  `validity_value` int unsigned DEFAULT NULL COMMENT '기본 유효기간 값',
  `validity_unit_code` varchar(20) DEFAULT NULL COMMENT '기본 유효기간 단위',
  `renewal_policy_code` varchar(30) NOT NULL DEFAULT 'NONE' COMMENT '갱신 정책',
  `is_active` tinyint(1) NOT NULL DEFAULT 1 COMMENT '사용 여부',
  `note` varchar(1000) DEFAULT NULL COMMENT '비고',
  `request_key` varchar(191) NOT NULL COMMENT '요청 식별키',
  `created_at` datetime NOT NULL COMMENT '생성일시', `created_by` varchar(100) NOT NULL COMMENT '생성자',
  `updated_at` datetime NOT NULL COMMENT '수정일시', `updated_by` varchar(100) NOT NULL COMMENT '수정자',
  `deleted_at` datetime DEFAULT NULL COMMENT '삭제일시', `deleted_by` varchar(100) DEFAULT NULL COMMENT '삭제자',
  PRIMARY KEY (`id`), UNIQUE KEY `uk_qualification_type_code` (`qualification_code`),
  UNIQUE KEY `uk_qualification_type_sort` (`sort_no`), UNIQUE KEY `uk_qualification_type_request` (`request_key`),
  KEY `idx_qualification_type_category_active` (`category_code`,`is_active`),
  CONSTRAINT `chk_qualification_type_active` CHECK (`is_active` IN (0,1)),
  CONSTRAINT `chk_qualification_type_validity` CHECK (
    (`validity_policy_code`='FIXED_PERIOD' AND `validity_value`>0 AND `validity_unit_code` IN ('DAY','MONTH','YEAR')) OR
    (`validity_policy_code` IN ('PERMANENT','RECORD_SPECIFIC') AND `validity_value` IS NULL AND `validity_unit_code` IS NULL)
  ),
  CONSTRAINT `chk_qualification_type_renewal` CHECK (`renewal_policy_code` IN ('NONE','RENEWAL','CONTINUING_EDUCATION'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='회사 자격 기준정보 SSOT';

INSERT INTO `institution_qualifications_types` (`id`,`sort_no`,`qualification_code`,`qualification_name`,`category_code`,`default_issuer_name`,`validity_policy_code`,`validity_value`,`validity_unit_code`,`renewal_policy_code`,`is_active`,`note`,`request_key`,`created_at`,`created_by`,`updated_at`,`updated_by`)
VALUES (UUID(),1,'UNCLASSIFIED','미분류 자격','OTHER',NULL,'RECORD_SPECIFIC',NULL,NULL,'NONE',1,'공식 자격 기준을 확정할 수 없는 기존 기록의 임시 분류','MIGRATION-QUALIFICATION-TYPE-UNCLASSIFIED',NOW(),'SYSTEM:MIGRATION',NOW(),'SYSTEM:MIGRATION');

ALTER TABLE `institution_qualifications_employee_records`
  ADD COLUMN `qualification_type_id` char(36) DEFAULT NULL COMMENT '자격 기준 식별자' AFTER `employee_id`,
  ADD KEY `idx_qualification_record_type_employee_validity` (`qualification_type_id`,`employee_id`,`valid_to`),
  ADD CONSTRAINT `fk_qualification_record_type` FOREIGN KEY (`qualification_type_id`) REFERENCES `institution_qualifications_types` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE;
UPDATE `institution_qualifications_employee_records` SET `qualification_type_id`=(SELECT `id` FROM `institution_qualifications_types` WHERE `qualification_code`='UNCLASSIFIED') WHERE `qualification_type_id` IS NULL;

ALTER TABLE `institution_educations_courses`
  ADD COLUMN `recurrence_policy_code` varchar(30) NOT NULL DEFAULT 'NONE' COMMENT '재교육 정책' AFTER `validity_months`,
  ADD COLUMN `recurrence_interval_value` int unsigned DEFAULT NULL COMMENT '재교육 주기 값' AFTER `recurrence_policy_code`,
  ADD COLUMN `recurrence_interval_unit_code` varchar(20) DEFAULT NULL COMMENT '재교육 주기 단위' AFTER `recurrence_interval_value`,
  ADD COLUMN `recurrence_event_code` varchar(50) DEFAULT NULL COMMENT '재교육 발생 이벤트' AFTER `recurrence_interval_unit_code`,
  ADD COLUMN `statutory_standard_type_code` varchar(80) DEFAULT NULL COMMENT '법정기준 종류 코드' AFTER `recurrence_event_code`,
  ADD KEY `idx_education_course_recurrence_active` (`recurrence_policy_code`,`is_active`),
  ADD KEY `idx_education_course_statutory_active` (`statutory_standard_type_code`,`is_active`),
  ADD CONSTRAINT `chk_education_course_recurrence` CHECK (
    (`recurrence_policy_code`='NONE' AND `recurrence_interval_value` IS NULL AND `recurrence_interval_unit_code` IS NULL AND `recurrence_event_code` IS NULL AND `statutory_standard_type_code` IS NULL) OR
    (`recurrence_policy_code`='PERIODIC' AND `recurrence_interval_value`>0 AND `recurrence_interval_unit_code` IN ('DAY','MONTH','YEAR') AND `recurrence_event_code` IS NULL AND `statutory_standard_type_code` IS NULL) OR
    (`recurrence_policy_code`='EVENT' AND `recurrence_interval_value` IS NULL AND `recurrence_interval_unit_code` IS NULL AND `recurrence_event_code` IN ('HIRE','JOB_ASSIGNMENT','SITE_ASSIGNMENT','WORK_TYPE_CHANGE') AND `statutory_standard_type_code` IS NULL) OR
    (`recurrence_policy_code`='STATUTORY' AND `recurrence_interval_value` IS NULL AND `recurrence_interval_unit_code` IS NULL AND `recurrence_event_code` IS NULL AND `statutory_standard_type_code` IS NOT NULL)
  );
UPDATE `institution_educations_courses` SET
  `recurrence_policy_code`=IF(COALESCE(`validity_months`,0)>0,'PERIODIC','NONE'),
  `recurrence_interval_value`=IF(COALESCE(`validity_months`,0)>0,`validity_months`,NULL),
  `recurrence_interval_unit_code`=IF(COALESCE(`validity_months`,0)>0,'MONTH',NULL),
  `recurrence_event_code`=NULL, `statutory_standard_type_code`=NULL;

ALTER TABLE `institution_educations_employee_records`
  ADD KEY `idx_education_record_course_completion_date` (`course_id`,`completion_status_code`,`education_end_at`),
  ADD KEY `idx_education_record_employee_course_validity` (`employee_id`,`course_id`,`completion_status_code`,`valid_to`);

CREATE TABLE `institution_qualifications_job_requirements` (
  `id` char(36) NOT NULL, `job_id` varchar(36) NOT NULL, `qualification_type_id` char(36) NOT NULL,
  `requirement_level_code` varchar(30) NOT NULL DEFAULT 'REQUIRED', `effective_from` date NOT NULL, `effective_to` date DEFAULT NULL,
  `note` varchar(1000) DEFAULT NULL, `request_key` varchar(191) NOT NULL,
  `created_at` datetime NOT NULL, `created_by` varchar(100) NOT NULL, `updated_at` datetime NOT NULL, `updated_by` varchar(100) NOT NULL,
  `deleted_at` datetime DEFAULT NULL, `deleted_by` varchar(100) DEFAULT NULL,
  PRIMARY KEY (`id`), UNIQUE KEY `uk_job_qualification_requirement_period` (`job_id`,`qualification_type_id`,`effective_from`),
  UNIQUE KEY `uk_job_qualification_requirement_request` (`request_key`),
  KEY `idx_job_qualification_requirement_job_period` (`job_id`,`effective_from`,`effective_to`),
  KEY `idx_job_qualification_requirement_type_period` (`qualification_type_id`,`effective_from`,`effective_to`),
  CONSTRAINT `fk_job_qualification_requirement_job` FOREIGN KEY (`job_id`) REFERENCES `institution_job_assignments_jobs` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_job_qualification_requirement_type` FOREIGN KEY (`qualification_type_id`) REFERENCES `institution_qualifications_types` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `chk_job_qualification_requirement_level` CHECK (`requirement_level_code` IN ('REQUIRED','PREFERRED')),
  CONSTRAINT `chk_job_qualification_requirement_period` CHECK (`effective_to` IS NULL OR `effective_to`>=`effective_from`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='직무별 자격 요구조건 SSOT';

CREATE TABLE `institution_educations_job_requirements` (
  `id` char(36) NOT NULL, `job_id` varchar(36) NOT NULL, `course_id` char(36) NOT NULL,
  `requirement_level_code` varchar(30) NOT NULL DEFAULT 'REQUIRED', `effective_from` date NOT NULL, `effective_to` date DEFAULT NULL,
  `note` varchar(1000) DEFAULT NULL, `request_key` varchar(191) NOT NULL,
  `created_at` datetime NOT NULL, `created_by` varchar(100) NOT NULL, `updated_at` datetime NOT NULL, `updated_by` varchar(100) NOT NULL,
  `deleted_at` datetime DEFAULT NULL, `deleted_by` varchar(100) DEFAULT NULL,
  PRIMARY KEY (`id`), UNIQUE KEY `uk_job_education_requirement_period` (`job_id`,`course_id`,`effective_from`),
  UNIQUE KEY `uk_job_education_requirement_request` (`request_key`),
  KEY `idx_job_education_requirement_job_period` (`job_id`,`effective_from`,`effective_to`),
  KEY `idx_job_education_requirement_course_period` (`course_id`,`effective_from`,`effective_to`),
  CONSTRAINT `fk_job_education_requirement_job` FOREIGN KEY (`job_id`) REFERENCES `institution_job_assignments_jobs` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_job_education_requirement_course` FOREIGN KEY (`course_id`) REFERENCES `institution_educations_courses` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `chk_job_education_requirement_level` CHECK (`requirement_level_code` IN ('REQUIRED','PREFERRED')),
  CONSTRAINT `chk_job_education_requirement_period` CHECK (`effective_to` IS NULL OR `effective_to`>=`effective_from`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='직무별 교육 요구조건 SSOT';

CREATE TABLE `institution_qualification_education_policy_audits` (
  `id` char(36) NOT NULL, `policy_domain_code` varchar(50) NOT NULL, `target_id` char(36) NOT NULL,
  `action_type_code` varchar(30) NOT NULL, `source_type_code` varchar(30) NOT NULL, `reason` varchar(500) NOT NULL,
  `request_key` varchar(191) NOT NULL, `before_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL,
  `after_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL, `processed_by` varchar(100) NOT NULL, `processed_at` datetime NOT NULL,
  PRIMARY KEY (`id`), UNIQUE KEY `uk_qualification_education_policy_audit_request` (`request_key`),
  KEY `idx_qualification_education_policy_audit_target` (`policy_domain_code`,`target_id`,`processed_at`),
  CONSTRAINT `chk_qualification_education_policy_domain` CHECK (`policy_domain_code` IN ('QUALIFICATION_TYPE','EDUCATION_COURSE','JOB_QUALIFICATION_REQUIREMENT','JOB_EDUCATION_REQUIREMENT')),
  CONSTRAINT `chk_qualification_education_policy_before_json` CHECK (`before_data` IS NULL OR JSON_VALID(`before_data`)),
  CONSTRAINT `chk_qualification_education_policy_after_json` CHECK (`after_data` IS NULL OR JSON_VALID(`after_data`))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='자격·교육 기준 및 직무 요구조건 변경 감사';

INSERT INTO `system_codes` (`id`,`code_group`,`group_name`,`code`,`code_name`,`sort_no`,`is_active`,`created_at`,`created_by`,`updated_at`,`updated_by`)
SELECT UUID(),v.g,v.gn,v.c,v.cn,v.s,1,NOW(),'SYSTEM:MIGRATION',NOW(),'SYSTEM:MIGRATION' FROM (
  SELECT 'QUALIFICATION_STATUS' g,'자격 상태' gn,'INVALIDATED' c,'무효' cn,5 s UNION ALL
  SELECT 'EDUCATION_COMPLETION_STATUS','교육 이수상태','INVALIDATED','무효',4
) v WHERE NOT EXISTS (SELECT 1 FROM `system_codes` c WHERE c.code_group=v.g AND c.code=v.c);
