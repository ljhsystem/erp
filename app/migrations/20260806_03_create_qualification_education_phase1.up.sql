-- 자격·교육관리 1차 SSOT
CREATE TABLE `institution_qualifications_employee_records` (
 `id` char(36) NOT NULL, `employee_id` varchar(36) NOT NULL, `qualification_type_code` varchar(50) NOT NULL, `qualification_name` varchar(150) NOT NULL,
 `credential_number` varchar(100) DEFAULT NULL, `issuer_name` varchar(150) DEFAULT NULL, `grade_code` varchar(50) DEFAULT NULL, `technical_field_code` varchar(50) DEFAULT NULL, `specialty_code` varchar(50) DEFAULT NULL,
 `acquired_date` date DEFAULT NULL, `valid_from` date DEFAULT NULL, `valid_to` date DEFAULT NULL, `renewal_due_date` date DEFAULT NULL, `status_code` varchar(30) NOT NULL DEFAULT 'PENDING_VERIFICATION',
 `status_reason` varchar(500) DEFAULT NULL, `attachment_path` varchar(500) DEFAULT NULL, `attachment_name` varchar(255) DEFAULT NULL, `note` varchar(1000) DEFAULT NULL,
 `verified_at` datetime DEFAULT NULL, `verified_by` varchar(100) DEFAULT NULL, `supersedes_record_id` char(36) DEFAULT NULL, `request_key` varchar(191) NOT NULL,
 `created_at` datetime NOT NULL, `created_by` varchar(100) NOT NULL, `updated_at` datetime NOT NULL, `updated_by` varchar(100) NOT NULL, `deleted_at` datetime DEFAULT NULL, `deleted_by` varchar(100) DEFAULT NULL,
 PRIMARY KEY (`id`), UNIQUE KEY `uk_qualification_record_request` (`request_key`), UNIQUE KEY `uk_qualification_type_number` (`qualification_type_code`,`credential_number`),
 KEY `idx_qualification_employee_validity` (`employee_id`,`valid_from`,`valid_to`), KEY `idx_qualification_expiry` (`valid_to`,`renewal_due_date`,`status_code`),
 CONSTRAINT `fk_qualification_employee` FOREIGN KEY (`employee_id`) REFERENCES `user_employees` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
 CONSTRAINT `fk_qualification_supersedes` FOREIGN KEY (`supersedes_record_id`) REFERENCES `institution_qualifications_employee_records` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
 CONSTRAINT `chk_qualification_validity` CHECK (`valid_to` IS NULL OR `valid_from` IS NULL OR `valid_to`>=`valid_from`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='직원 보유 자격·면허·기술등급 SSOT';

CREATE TABLE `institution_qualifications_audits` (
 `id` char(36) NOT NULL, `target_id` char(36) NOT NULL, `employee_id` varchar(36) NOT NULL, `action_type_code` varchar(30) NOT NULL, `source_type_code` varchar(30) NOT NULL,
 `reason` varchar(500) NOT NULL, `request_key` varchar(191) NOT NULL, `before_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`before_data`)),
 `after_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`after_data`)), `processed_by` varchar(100) NOT NULL, `processed_at` datetime NOT NULL,
 PRIMARY KEY (`id`), UNIQUE KEY `uk_qualification_audit_request` (`request_key`), KEY `idx_qualification_audit_target` (`target_id`,`processed_at`), KEY `idx_qualification_audit_employee` (`employee_id`,`processed_at`),
 CONSTRAINT `fk_qualification_audit_employee` FOREIGN KEY (`employee_id`) REFERENCES `user_employees` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='자격 변경 감사 증적';

CREATE TABLE `institution_educations_courses` (
 `id` char(36) NOT NULL, `course_code` varchar(50) NOT NULL, `course_name` varchar(150) NOT NULL, `education_type_code` varchar(50) NOT NULL, `default_institution_name` varchar(150) DEFAULT NULL,
 `default_minutes` int unsigned NOT NULL DEFAULT 0, `validity_months` int unsigned DEFAULT NULL, `is_statutory` tinyint(1) NOT NULL DEFAULT 0, `is_mandatory` tinyint(1) NOT NULL DEFAULT 0,
 `requires_certificate` tinyint(1) NOT NULL DEFAULT 0, `description` varchar(1000) DEFAULT NULL, `sort_no` int unsigned NOT NULL, `is_active` tinyint(1) NOT NULL DEFAULT 1, `request_key` varchar(191) NOT NULL,
 `created_at` datetime NOT NULL, `created_by` varchar(100) NOT NULL, `updated_at` datetime NOT NULL, `updated_by` varchar(100) NOT NULL, `deleted_at` datetime DEFAULT NULL, `deleted_by` varchar(100) DEFAULT NULL,
 PRIMARY KEY (`id`), UNIQUE KEY `uk_education_course_code` (`course_code`), UNIQUE KEY `uk_education_course_sort` (`sort_no`), UNIQUE KEY `uk_education_course_request` (`request_key`), KEY `idx_education_course_type_active` (`education_type_code`,`is_active`),
 CONSTRAINT `chk_education_course_flags` CHECK (`is_statutory` IN (0,1) AND `is_mandatory` IN (0,1) AND `requires_certificate` IN (0,1) AND `is_active` IN (0,1))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='교육과정 기준정보 SSOT';

CREATE TABLE `institution_educations_employee_records` (
 `id` char(36) NOT NULL, `employee_id` varchar(36) NOT NULL, `course_id` char(36) NOT NULL, `education_name` varchar(200) NOT NULL, `institution_name` varchar(150) DEFAULT NULL,
 `education_start_at` datetime NOT NULL, `education_end_at` datetime NOT NULL, `education_minutes` int unsigned NOT NULL, `attendance_status_code` varchar(30) NOT NULL DEFAULT 'ATTENDED',
 `completion_status_code` varchar(30) NOT NULL DEFAULT 'COMPLETED', `completion_number` varchar(100) DEFAULT NULL, `valid_from` date DEFAULT NULL, `valid_to` date DEFAULT NULL, `renewal_due_date` date DEFAULT NULL,
 `attachment_path` varchar(500) DEFAULT NULL, `attachment_name` varchar(255) DEFAULT NULL, `note` varchar(1000) DEFAULT NULL, `request_key` varchar(191) NOT NULL,
 `created_at` datetime NOT NULL, `created_by` varchar(100) NOT NULL, `updated_at` datetime NOT NULL, `updated_by` varchar(100) NOT NULL, `deleted_at` datetime DEFAULT NULL, `deleted_by` varchar(100) DEFAULT NULL,
 PRIMARY KEY (`id`), UNIQUE KEY `uk_education_record_request` (`request_key`), KEY `idx_education_employee_period` (`employee_id`,`education_start_at`), KEY `idx_education_expiry` (`valid_to`,`renewal_due_date`,`completion_status_code`),
 CONSTRAINT `fk_education_record_employee` FOREIGN KEY (`employee_id`) REFERENCES `user_employees` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
 CONSTRAINT `fk_education_record_course` FOREIGN KEY (`course_id`) REFERENCES `institution_educations_courses` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
 CONSTRAINT `chk_education_record_time` CHECK (`education_end_at`>`education_start_at` AND `education_minutes`>0), CONSTRAINT `chk_education_record_validity` CHECK (`valid_to` IS NULL OR `valid_from` IS NULL OR `valid_to`>=`valid_from`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='직원별 교육 참석·이수 SSOT';

CREATE TABLE `institution_educations_audits` (
 `id` char(36) NOT NULL, `target_id` char(36) NOT NULL, `employee_id` varchar(36) DEFAULT NULL, `action_type_code` varchar(30) NOT NULL, `source_type_code` varchar(30) NOT NULL,
 `reason` varchar(500) NOT NULL, `request_key` varchar(191) NOT NULL, `before_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`before_data`)),
 `after_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`after_data`)), `processed_by` varchar(100) NOT NULL, `processed_at` datetime NOT NULL,
 PRIMARY KEY (`id`), UNIQUE KEY `uk_education_audit_request` (`request_key`), KEY `idx_education_audit_target` (`target_id`,`processed_at`), KEY `idx_education_audit_employee` (`employee_id`,`processed_at`),
 CONSTRAINT `fk_education_audit_employee` FOREIGN KEY (`employee_id`) REFERENCES `user_employees` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='교육 변경 감사 증적';

INSERT INTO `system_codes` (`id`,`code_group`,`group_name`,`code`,`code_name`,`sort_no`,`is_active`,`created_at`,`created_by`,`updated_at`,`updated_by`) SELECT UUID(),v.g,v.gn,v.c,v.cn,v.s,1,NOW(),'SYSTEM:MIGRATION',NOW(),'SYSTEM:MIGRATION' FROM (
 SELECT 'QUALIFICATION_TYPE' g,'자격 종류' gn,'NATIONAL_TECHNICAL' c,'국가기술자격' cn,1 s UNION ALL SELECT 'QUALIFICATION_TYPE','자격 종류','NATIONAL_PROFESSIONAL','국가전문자격',2 UNION ALL SELECT 'QUALIFICATION_TYPE','자격 종류','PRIVATE','민간자격',3 UNION ALL SELECT 'QUALIFICATION_TYPE','자격 종류','CONSTRUCTION_ENGINEER','건설기술인',4 UNION ALL SELECT 'QUALIFICATION_TYPE','자격 종류','SAFETY','안전자격',5 UNION ALL SELECT 'QUALIFICATION_TYPE','자격 종류','QUALITY','품질자격',6 UNION ALL SELECT 'QUALIFICATION_TYPE','자격 종류','EQUIPMENT','장비·운전자격',7 UNION ALL SELECT 'QUALIFICATION_TYPE','자격 종류','INTERNAL','회사 내부자격',8 UNION ALL SELECT 'QUALIFICATION_TYPE','자격 종류','OTHER','기타',9 UNION ALL
 SELECT 'QUALIFICATION_STATUS','자격 상태','PENDING_VERIFICATION','확인대기',1 UNION ALL SELECT 'QUALIFICATION_STATUS','자격 상태','ACTIVE','유효',2 UNION ALL SELECT 'QUALIFICATION_STATUS','자격 상태','SUSPENDED','정지',3 UNION ALL SELECT 'QUALIFICATION_STATUS','자격 상태','REVOKED','말소',4 UNION ALL
 SELECT 'EDUCATION_TYPE','교육 종류','CONSTRUCTION_ENGINEER','건설기술인 교육',1 UNION ALL SELECT 'EDUCATION_TYPE','교육 종류','QUALITY','품질교육',2 UNION ALL SELECT 'EDUCATION_TYPE','교육 종류','SAFETY','안전교육',3 UNION ALL SELECT 'EDUCATION_TYPE','교육 종류','OCCUPATIONAL_SAFETY_HEALTH','산업안전보건교육',4 UNION ALL SELECT 'EDUCATION_TYPE','교육 종류','SUPERVISOR','관리감독자교육',5 UNION ALL SELECT 'EDUCATION_TYPE','교육 종류','RISK_ASSESSMENT','위험성평가교육',6 UNION ALL SELECT 'EDUCATION_TYPE','교육 종류','STATUTORY','법정교육',7 UNION ALL SELECT 'EDUCATION_TYPE','교육 종류','INTERNAL','회사 자체교육',8 UNION ALL SELECT 'EDUCATION_TYPE','교육 종류','OTHER','기타교육',9 UNION ALL
 SELECT 'EDUCATION_ATTENDANCE_STATUS','교육 참석상태','ATTENDED','참석',1 UNION ALL SELECT 'EDUCATION_ATTENDANCE_STATUS','교육 참석상태','ABSENT','결석',2 UNION ALL
 SELECT 'EDUCATION_COMPLETION_STATUS','교육 이수상태','PENDING','확인대기',1 UNION ALL SELECT 'EDUCATION_COMPLETION_STATUS','교육 이수상태','COMPLETED','이수',2 UNION ALL SELECT 'EDUCATION_COMPLETION_STATUS','교육 이수상태','NOT_COMPLETED','미이수',3
) v WHERE NOT EXISTS(SELECT 1 FROM system_codes c WHERE c.code_group=v.g AND c.code=v.c);

INSERT INTO `institution_educations_courses` (`id`,`course_code`,`course_name`,`education_type_code`,`default_minutes`,`is_statutory`,`is_mandatory`,`requires_certificate`,`sort_no`,`is_active`,`request_key`,`created_at`,`created_by`,`updated_at`,`updated_by`) VALUES
(UUID(),'CONSTRUCTION_ENGINEER','건설기술인 교육','CONSTRUCTION_ENGINEER',0,1,1,1,1,1,'SEED-EDU-COURSE-CONSTRUCTION',NOW(),'SYSTEM:MIGRATION',NOW(),'SYSTEM:MIGRATION'),
(UUID(),'QUALITY','품질교육','QUALITY',0,0,1,1,2,1,'SEED-EDU-COURSE-QUALITY',NOW(),'SYSTEM:MIGRATION',NOW(),'SYSTEM:MIGRATION'),
(UUID(),'SAFETY','안전교육','SAFETY',0,1,1,1,3,1,'SEED-EDU-COURSE-SAFETY',NOW(),'SYSTEM:MIGRATION',NOW(),'SYSTEM:MIGRATION'),
(UUID(),'OCCUPATIONAL_SAFETY_HEALTH','산업안전보건교육','OCCUPATIONAL_SAFETY_HEALTH',0,1,1,1,4,1,'SEED-EDU-COURSE-OCCUPATIONAL',NOW(),'SYSTEM:MIGRATION',NOW(),'SYSTEM:MIGRATION'),
(UUID(),'SUPERVISOR','관리감독자교육','SUPERVISOR',0,1,1,1,5,1,'SEED-EDU-COURSE-SUPERVISOR',NOW(),'SYSTEM:MIGRATION',NOW(),'SYSTEM:MIGRATION'),
(UUID(),'RISK_ASSESSMENT','위험성평가교육','RISK_ASSESSMENT',0,1,1,1,6,1,'SEED-EDU-COURSE-RISK',NOW(),'SYSTEM:MIGRATION',NOW(),'SYSTEM:MIGRATION'),
(UUID(),'STATUTORY','법정교육','STATUTORY',0,1,1,1,7,1,'SEED-EDU-COURSE-STATUTORY',NOW(),'SYSTEM:MIGRATION',NOW(),'SYSTEM:MIGRATION'),
(UUID(),'INTERNAL','회사 자체교육','INTERNAL',0,0,0,0,8,1,'SEED-EDU-COURSE-INTERNAL',NOW(),'SYSTEM:MIGRATION',NOW(),'SYSTEM:MIGRATION'),
(UUID(),'OTHER','기타교육','OTHER',0,0,0,0,9,1,'SEED-EDU-COURSE-OTHER',NOW(),'SYSTEM:MIGRATION',NOW(),'SYSTEM:MIGRATION');

INSERT INTO `institution_qualifications_employee_records` (`id`,`employee_id`,`qualification_type_code`,`qualification_name`,`status_code`,`attachment_path`,`attachment_name`,`note`,`request_key`,`created_at`,`created_by`,`updated_at`,`updated_by`)
SELECT UUID(),e.id,'OTHER',COALESCE(NULLIF(e.certificate_name,''),'기존 대표 자격증'),'PENDING_VERIFICATION',e.certificate_file,SUBSTRING_INDEX(e.certificate_file,'/',-1),'직원 마스터 대표 자격증에서 이관',CONCAT('LEGACY-EMPLOYEE-CERTIFICATE-',e.id),NOW(),'SYSTEM:MIGRATION',NOW(),'SYSTEM:MIGRATION' FROM user_employees e WHERE COALESCE(e.certificate_name,'')<>'' OR COALESCE(e.certificate_file,'')<>'';

INSERT INTO `auth_permissions` (`id`,`sort_no`,`page`,`permission_source`,`category`,`permission_key`,`permission_name`,`description`,`page_key`,`is_active`,`created_at`,`created_by`,`updated_at`,`updated_by`) SELECT UUID(),(SELECT COALESCE(MAX(p.sort_no),0)+v.s FROM auth_permissions p),'자격·교육관리','ROUTE','대외기관업무',v.k,v.n,CONCAT('자격·교육관리 ',v.n),'web.institution.human_resources.qualification_education',1,NOW(),'SYSTEM:MIGRATION',NOW(),'SYSTEM:MIGRATION' FROM (
 SELECT 1 s,'web.institution.human_resources.qualification_education' k,'화면조회' n UNION ALL SELECT 2,'api.institution.human_resources.qualification_education.view_self','본인조회' UNION ALL SELECT 3,'api.institution.human_resources.qualification_education.view_all','전체조회' UNION ALL SELECT 4,'api.institution.human_resources.qualification_education.options','선택옵션조회' UNION ALL SELECT 5,'api.institution.human_resources.qualification_education.qualification_list','본인자격조회' UNION ALL SELECT 6,'api.institution.human_resources.qualification_education.qualification_all_list','전체자격조회' UNION ALL SELECT 7,'api.institution.human_resources.qualification_education.qualification_detail','자격상세조회' UNION ALL SELECT 8,'api.institution.human_resources.qualification_education.education_list','본인교육조회' UNION ALL SELECT 9,'api.institution.human_resources.qualification_education.education_all_list','전체교육조회' UNION ALL SELECT 10,'api.institution.human_resources.qualification_education.education_detail','교육상세조회' UNION ALL SELECT 11,'api.institution.human_resources.qualification_education.save','등록·수정' UNION ALL SELECT 12,'api.institution.human_resources.qualification_education.delete','삭제' UNION ALL SELECT 13,'api.institution.human_resources.qualification_education.verify','자격검증' UNION ALL SELECT 14,'api.institution.human_resources.qualification_education.renew','자격갱신' UNION ALL SELECT 15,'api.institution.human_resources.qualification_education.course_manage','교육과정관리' UNION ALL SELECT 16,'api.institution.human_resources.qualification_education.education_manage','교육이수관리' UNION ALL SELECT 17,'api.institution.human_resources.qualification_education.excel','Excel다운로드'
) v WHERE NOT EXISTS(SELECT 1 FROM auth_permissions x WHERE x.permission_key=v.k);
INSERT INTO `auth_role_permissions` (`id`,`role_id`,`permission_id`,`created_at`,`created_by`) SELECT UUID(),r.id,p.id,NOW(),'SYSTEM:MIGRATION' FROM auth_roles r JOIN auth_permissions p ON p.permission_key='web.institution.human_resources.qualification_education' OR p.permission_key LIKE 'api.institution.human_resources.qualification_education.%' LEFT JOIN auth_role_permissions rp ON rp.role_id=r.id AND rp.permission_id=p.id WHERE r.role_key='super_admin' AND rp.id IS NULL;
UPDATE `system_page_registry` SET `page_description`='직원 자격·교육·만료·갱신 관리',`source_description`='자격·교육관리 1차 SSOT',`updated_at`=NOW() WHERE `page_key`='web.institution.human_resources.qualification_education';
ALTER TABLE `user_employees` DROP COLUMN `certificate_name`, DROP COLUMN `certificate_file`;
