CREATE TABLE `institution_educations_sessions` (
  `id` char(36) NOT NULL COMMENT '교육회차 식별자',
  `course_id` char(36) NOT NULL COMMENT '교육과정 식별자',
  `title` varchar(200) NOT NULL COMMENT '교육회차명',
  `starts_at` datetime NOT NULL COMMENT '교육 시작일시',
  `ends_at` datetime NOT NULL COMMENT '교육 종료일시',
  `location_name` varchar(200) DEFAULT NULL COMMENT '교육 장소명',
  `organizer_employee_id` varchar(36) DEFAULT NULL COMMENT '교육 주관 직원 식별자',
  `instructor_name` varchar(150) DEFAULT NULL COMMENT '강사명',
  `status_code` varchar(30) NOT NULL DEFAULT 'DRAFT' COMMENT '교육회차 상태 코드',
  `note` varchar(1000) DEFAULT NULL COMMENT '비고',
  `request_key` varchar(191) NOT NULL COMMENT '요청 식별키',
  `created_at` datetime NOT NULL COMMENT '생성일시',
  `created_by` varchar(100) NOT NULL COMMENT '생성자',
  `updated_at` datetime NOT NULL COMMENT '수정일시',
  `updated_by` varchar(100) NOT NULL COMMENT '수정자',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_education_session_request` (`request_key`),
  KEY `idx_education_session_period` (`starts_at`,`ends_at`),
  KEY `idx_education_session_course_period` (`course_id`,`starts_at`),
  KEY `idx_education_session_status_period` (`status_code`,`starts_at`),
  KEY `idx_education_session_organizer` (`organizer_employee_id`,`starts_at`),
  CONSTRAINT `fk_education_session_course` FOREIGN KEY (`course_id`) REFERENCES `institution_educations_courses` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_education_session_organizer` FOREIGN KEY (`organizer_employee_id`) REFERENCES `user_employees` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `chk_education_session_time` CHECK (`ends_at`>`starts_at`),
  CONSTRAINT `chk_education_session_status` CHECK (`status_code` IN ('DRAFT','SCHEDULED','CANCELLED','COMPLETED'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='실제 예정·실시 교육회차 SSOT';

CREATE TABLE `institution_educations_session_targets` (
  `id` char(36) NOT NULL COMMENT '교육대상 식별자',
  `session_id` char(36) NOT NULL COMMENT '교육회차 식별자',
  `employee_id` varchar(36) NOT NULL COMMENT '대상 직원 식별자',
  `assignment_source_code` varchar(30) NOT NULL COMMENT '대상 선정 원천 코드',
  `acknowledged_at` datetime DEFAULT NULL COMMENT '직원 교육내용 확인일시',
  `attendance_status_code` varchar(30) NOT NULL DEFAULT 'NOT_RECORDED' COMMENT '참석 상태 코드',
  `completion_status_code` varchar(30) NOT NULL DEFAULT 'PENDING' COMMENT '이수 상태 코드',
  `removed_at` datetime DEFAULT NULL COMMENT '대상 제외일시',
  `removed_by` varchar(100) DEFAULT NULL COMMENT '대상 제외자',
  `request_key` varchar(191) NOT NULL COMMENT '요청 식별키',
  `created_at` datetime NOT NULL COMMENT '생성일시',
  `created_by` varchar(100) NOT NULL COMMENT '생성자',
  `updated_at` datetime NOT NULL COMMENT '수정일시',
  `updated_by` varchar(100) NOT NULL COMMENT '수정자',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_education_session_target` (`session_id`,`employee_id`),
  UNIQUE KEY `uk_education_session_target_request` (`request_key`),
  KEY `idx_education_target_employee_session` (`employee_id`,`session_id`),
  KEY `idx_education_target_session_outcome` (`session_id`,`removed_at`,`attendance_status_code`,`completion_status_code`),
  CONSTRAINT `fk_education_target_session` FOREIGN KEY (`session_id`) REFERENCES `institution_educations_sessions` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_education_target_employee` FOREIGN KEY (`employee_id`) REFERENCES `user_employees` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `chk_education_target_attendance` CHECK (`attendance_status_code` IN ('NOT_RECORDED','ATTENDED','ABSENT')),
  CONSTRAINT `chk_education_target_completion` CHECK (`completion_status_code` IN ('PENDING','COMPLETED','NOT_COMPLETED'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='교육회차 확정 대상 직원 Snapshot SSOT';

ALTER TABLE `institution_educations_employee_records`
  ADD COLUMN `session_id` char(36) DEFAULT NULL COMMENT '회사 운영 교육회차 식별자' AFTER `course_id`,
  ADD UNIQUE KEY `uk_education_record_session_employee` (`session_id`,`employee_id`),
  ADD KEY `idx_education_record_session` (`session_id`),
  ADD CONSTRAINT `fk_education_record_session` FOREIGN KEY (`session_id`) REFERENCES `institution_educations_sessions` (`id`) ON UPDATE CASCADE;

INSERT INTO `system_codes` (`id`,`code_group`,`group_name`,`code`,`code_name`,`sort_no`,`is_active`,`created_at`,`created_by`,`updated_at`,`updated_by`)
SELECT UUID(),v.code_group,v.group_name,v.code,v.code_name,v.sort_no,1,NOW(),'SYSTEM:MIGRATION',NOW(),'SYSTEM:MIGRATION'
FROM (
  SELECT 'EDUCATION_SESSION_STATUS' code_group,'교육회차 상태' group_name,'DRAFT' code,'작성중' code_name,1 sort_no UNION ALL
  SELECT 'EDUCATION_SESSION_STATUS','교육회차 상태','SCHEDULED','일정확정',2 UNION ALL
  SELECT 'EDUCATION_SESSION_STATUS','교육회차 상태','CANCELLED','취소',3 UNION ALL
  SELECT 'EDUCATION_SESSION_STATUS','교육회차 상태','COMPLETED','완료',4 UNION ALL
  SELECT 'EDUCATION_TARGET_SOURCE','교육대상 선정 원천','INDIVIDUAL','개별직원',1 UNION ALL
  SELECT 'EDUCATION_TARGET_SOURCE','교육대상 선정 원천','DEPARTMENT','부서',2 UNION ALL
  SELECT 'EDUCATION_TARGET_SOURCE','교육대상 선정 원천','JOB','직무',3 UNION ALL
  SELECT 'EDUCATION_TARGET_SOURCE','교육대상 선정 원천','ALL_EMPLOYEES','전체직원',4 UNION ALL
  SELECT 'EDUCATION_TARGET_SOURCE','교육대상 선정 원천','PROJECT','프로젝트·현장',5 UNION ALL
  SELECT 'EDUCATION_TARGET_SOURCE','교육대상 선정 원천','STATUTORY_RULE','법정기준 자동대상',6 UNION ALL
  SELECT 'EDUCATION_ATTENDANCE_STATUS','교육 참석상태','NOT_RECORDED','미처리',0
) v
WHERE NOT EXISTS (
  SELECT 1 FROM `system_codes` c WHERE c.code_group=v.code_group AND c.code=v.code
);
