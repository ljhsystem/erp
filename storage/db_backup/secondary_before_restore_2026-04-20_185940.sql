-- Sukhyang ERP Database Backup
-- Database: sukhyang
-- Date: 2026-04-20 18:59:40

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ----------------------------
-- Table structure for `system_file_upload_policies`
-- ----------------------------
DROP TABLE IF EXISTS `system_file_upload_policies`;
CREATE TABLE `system_file_upload_policies` (
  `id` char(36) NOT NULL COMMENT '고유 ID (UUID)',
  `policy_key` varchar(50) NOT NULL COMMENT '정책 고유 키 (코드)',
  `policy_name` varchar(100) NOT NULL COMMENT '정책 이름',
  `bucket` varchar(100) NOT NULL COMMENT '저장 bucket (public://profile 등)',
  `allowed_ext` text NOT NULL COMMENT '허용 확장자 (csv: jpg,png,pdf)',
  `allowed_mime` text DEFAULT NULL COMMENT '허용 MIME (csv)',
  `max_size_mb` int(10) unsigned NOT NULL COMMENT '최대 업로드 용량(MB)',
  `is_active` tinyint(1) NOT NULL DEFAULT 1 COMMENT '활성 여부',
  `description` varchar(255) DEFAULT NULL COMMENT '설명',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `created_by` char(36) NOT NULL COMMENT '생성자 사용자 ID (employees.id)',
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  `updated_by` char(36) DEFAULT NULL COMMENT '수정자 사용자 ID (employees.id)',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_policy_key` (`policy_key`),
  KEY `idx_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='파일 업로드 정책 정의';

-- Data for `system_file_upload_policies`
INSERT INTO `system_file_upload_policies` (`id`,`policy_key`,`policy_name`,`bucket`,`allowed_ext`,`allowed_mime`,`max_size_mb`,`is_active`,`description`,`created_at`,`created_by`,`updated_at`,`updated_by`) VALUES ('15c65f18-cde1-444f-ab1a-51d2b0dbe731','profile_image','프로필 이미지','public://profile','jpg,jpeg,png','image/jpeg,image/png','6','1','사용자 프로필 사진 업로드용 이미지 파일','2025-12-17 12:06:05','f113b666-ff40-4f93-a7e7-8cea4cdc9c28','2025-12-18 19:40:38','f113b666-ff40-4f93-a7e7-8cea4cdc9c28');
INSERT INTO `system_file_upload_policies` (`id`,`policy_key`,`policy_name`,`bucket`,`allowed_ext`,`allowed_mime`,`max_size_mb`,`is_active`,`description`,`created_at`,`created_by`,`updated_at`,`updated_by`) VALUES ('15c65f18-cde1-444f-ab1a-51d2b0dbe732','id_document','신분증','private://id_doc','jpg,png,pdf','image/jpeg,image/png,application/pdf','10','1','신분증 및 본인 확인용 문서 (비공개 저장)','2025-12-17 12:07:14','f113b666-ff40-4f93-a7e7-8cea4cdc9c28','2025-12-18 19:40:53','f113b666-ff40-4f93-a7e7-8cea4cdc9c28');
INSERT INTO `system_file_upload_policies` (`id`,`policy_key`,`policy_name`,`bucket`,`allowed_ext`,`allowed_mime`,`max_size_mb`,`is_active`,`description`,`created_at`,`created_by`,`updated_at`,`updated_by`) VALUES ('15c65f18-cde1-444f-ab1a-51d2b0dbe733','certificate','자격증','public://profile','jpg,png,pdf','image/jpeg,image/png,application/pdf','10','1','자격증 및 면허증 파일 업로드','2025-12-17 13:51:56','f113b666-ff40-4f93-a7e7-8cea4cdc9c28','2025-12-18 19:41:07','f113b666-ff40-4f93-a7e7-8cea4cdc9c28');
INSERT INTO `system_file_upload_policies` (`id`,`policy_key`,`policy_name`,`bucket`,`allowed_ext`,`allowed_mime`,`max_size_mb`,`is_active`,`description`,`created_at`,`created_by`,`updated_at`,`updated_by`) VALUES ('15c65f18-cde1-444f-ab1a-51d2b0dbe734','business_cert','사업자등록증','public://business_cert','jpg,png,pdf','image/jpeg,image/png,application/pdf','10','1','사업자등록증 및 사업 관련 증빙 문서','2025-12-17 12:06:41','f113b666-ff40-4f93-a7e7-8cea4cdc9c28','2025-12-18 19:41:20','f113b666-ff40-4f93-a7e7-8cea4cdc9c28');
INSERT INTO `system_file_upload_policies` (`id`,`policy_key`,`policy_name`,`bucket`,`allowed_ext`,`allowed_mime`,`max_size_mb`,`is_active`,`description`,`created_at`,`created_by`,`updated_at`,`updated_by`) VALUES ('15c65f18-cde1-444f-ab1a-51d2b0dbe735','pdf,docx,xlsx','내부 문서','private://raw','pdf,docx,xlsx,jpg,png,zip','application/pdf,application/zip,text/plain,text/csv,image/jpeg,image/png','20','1','내부 업무 자료 및 원본 문서 (관리자 전용)','2025-12-17 15:17:09','f113b666-ff40-4f93-a7e7-8cea4cdc9c28','2025-12-18 19:41:34','f113b666-ff40-4f93-a7e7-8cea4cdc9c28');
INSERT INTO `system_file_upload_policies` (`id`,`policy_key`,`policy_name`,`bucket`,`allowed_ext`,`allowed_mime`,`max_size_mb`,`is_active`,`description`,`created_at`,`created_by`,`updated_at`,`updated_by`) VALUES ('15c65f18-cde1-444f-ab1a-51d2b0dbe736','cover_image','커버 이미지','public://covers','jpg,jpeg,png,webp','image/jpeg,image/png,image/webp','5','1','페이지 상단 커버 및 배너 이미지','2025-12-17 15:14:54','f113b666-ff40-4f93-a7e7-8cea4cdc9c28','2025-12-18 19:41:58','f113b666-ff40-4f93-a7e7-8cea4cdc9c28');
INSERT INTO `system_file_upload_policies` (`id`,`policy_key`,`policy_name`,`bucket`,`allowed_ext`,`allowed_mime`,`max_size_mb`,`is_active`,`description`,`created_at`,`created_by`,`updated_at`,`updated_by`) VALUES ('15c65f18-cde1-444f-ab1a-51d2b0dbe737','brand_asset','브랜드 이미지(로고/아이콘)','public://brand','png,svg,jpg,webp','image/jpeg,image/png,image/webp,image/svg+xml,image/x-icon','3','1','브랜드 로고, 아이콘 및 UI 자산 이미지','2025-12-17 15:15:39','f113b666-ff40-4f93-a7e7-8cea4cdc9c28','2025-12-18 19:42:08','f113b666-ff40-4f93-a7e7-8cea4cdc9c28');
INSERT INTO `system_file_upload_policies` (`id`,`policy_key`,`policy_name`,`bucket`,`allowed_ext`,`allowed_mime`,`max_size_mb`,`is_active`,`description`,`created_at`,`created_by`,`updated_at`,`updated_by`) VALUES ('15c65f18-cde1-444f-ab1a-51d2b0dbe738','public_document','공개 문서','public://documents','pdf,docx,xlsx','application/pdf,application/vnd.ms-excel,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet','10','1','외부 공개용 문서 (다운로드 가능)','2025-12-17 15:16:21','f113b666-ff40-4f93-a7e7-8cea4cdc9c28','2025-12-18 19:42:21','f113b666-ff40-4f93-a7e7-8cea4cdc9c28');
INSERT INTO `system_file_upload_policies` (`id`,`policy_key`,`policy_name`,`bucket`,`allowed_ext`,`allowed_mime`,`max_size_mb`,`is_active`,`description`,`created_at`,`created_by`,`updated_at`,`updated_by`) VALUES ('47a37435-08bf-4bc8-94f3-c5906d4057b4','bank_copy','통장사본','private://bank_copy','jpg,jpeg,png,pdf','image/jpeg,image/png,application/pdf','10','1','거래처 통장사본 업로드 파일','2026-03-12 10:50:35','f113b666-ff40-4f93-a7e7-8cea4cdc9c28','2026-03-12 10:58:16','f113b666-ff40-4f93-a7e7-8cea4cdc9c28');

-- ----------------------------
-- Table structure for `system_projects`
-- ----------------------------
DROP TABLE IF EXISTS `system_projects`;
CREATE TABLE `system_projects` (
  `id` varchar(36) NOT NULL COMMENT '프로젝트 고유 ID (UUID)',
  `code` int(11) NOT NULL,
  `project_name` varchar(100) DEFAULT NULL COMMENT '프로젝트명 (내부 식별용)',
  `client_id` varchar(36) DEFAULT NULL COMMENT '거래처 ID (ledger_clients.id FK)',
  `employee_id` varchar(36) DEFAULT NULL COMMENT '담당 직원 ID (user_profiles.id FK)',
  `site_agent` varchar(100) DEFAULT NULL COMMENT '현장대리인',
  `contract_type` varchar(50) DEFAULT NULL COMMENT '계약형태 (예: 도급, 하도급 등)',
  `director` varchar(50) DEFAULT NULL COMMENT '소장',
  `manager` varchar(50) DEFAULT NULL COMMENT '실장',
  `business_type` varchar(50) DEFAULT NULL COMMENT '업종',
  `housing_type` varchar(50) DEFAULT NULL COMMENT '주력분야',
  `construction_name` varchar(100) DEFAULT NULL COMMENT '공사명 (프로젝트 정식 명칭)',
  `site_region_city` varchar(50) DEFAULT NULL COMMENT '공사지역 (시도)',
  `site_region_district` varchar(50) DEFAULT NULL COMMENT '공사지역 (시군구)',
  `site_region_address` varchar(255) DEFAULT NULL COMMENT '공사지역 주소',
  `site_region_address_detail` varchar(255) DEFAULT NULL COMMENT '공사지역 주소',
  `work_type` varchar(50) DEFAULT NULL COMMENT '공종 (예: 석공사, 토목공사 등)',
  `work_subtype` varchar(50) DEFAULT NULL COMMENT '공종 세분류',
  `work_detail_type` varchar(100) DEFAULT NULL COMMENT '세부 공사종류',
  `contract_work_type` varchar(50) DEFAULT NULL COMMENT '도급 종류 (직영, 하도 등)',
  `bid_type` varchar(50) DEFAULT NULL COMMENT '입찰형태 (지명, 경쟁, 수의 등)',
  `client_name` varchar(100) DEFAULT NULL COMMENT '발주자명',
  `client_type` varchar(50) DEFAULT NULL COMMENT '발주자 분류 (공공, 민간 등)',
  `permit_agency` varchar(100) DEFAULT NULL COMMENT '인허가기관',
  `permit_date` date DEFAULT NULL COMMENT '인허가일자',
  `contract_date` date DEFAULT NULL COMMENT '계약일자',
  `start_date` date DEFAULT NULL COMMENT '착공일자',
  `completion_date` date DEFAULT NULL COMMENT '준공일자',
  `bid_notice_date` date DEFAULT NULL COMMENT '입찰공고일',
  `initial_contract_amount` decimal(18,2) DEFAULT NULL COMMENT '최초 계약금액(공급가액)',
  `authorized_company_seal` varchar(100) DEFAULT NULL COMMENT '사용인감명',
  `note` varchar(255) DEFAULT NULL COMMENT '비고',
  `memo` text DEFAULT NULL COMMENT '상세 메모',
  `is_active` tinyint(1) DEFAULT 1 COMMENT '진행상태 (1=진행중, 0=완료/종료)',
  `created_at` datetime DEFAULT current_timestamp() COMMENT '등록일시',
  `created_by` varchar(100) DEFAULT NULL COMMENT '등록자ID',
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp() COMMENT '수정일시',
  `updated_by` varchar(100) DEFAULT NULL COMMENT '수정자ID',
  `deleted_at` datetime DEFAULT NULL COMMENT '삭제일시',
  `deleted_by` varchar(100) DEFAULT NULL COMMENT '삭제자ID',
  PRIMARY KEY (`id`) USING BTREE,
  KEY `idx_client_id` (`client_id`) USING BTREE,
  KEY `idx_employee_id` (`employee_id`) USING BTREE,
  KEY `idx_is_active` (`is_active`) USING BTREE,
  KEY `idx_manage_name` (`project_name`) USING BTREE,
  KEY `idx_code` (`code`),
  CONSTRAINT `fk_projects_client` FOREIGN KEY (`client_id`) REFERENCES `system_clients` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_projects_employee` FOREIGN KEY (`employee_id`) REFERENCES `user_profiles` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='프로젝트(현장/공사) 관리 테이블 (UUID + 순번 구조)';

-- Data for `system_projects`
INSERT INTO `system_projects` (`id`,`code`,`project_name`,`client_id`,`employee_id`,`site_agent`,`contract_type`,`director`,`manager`,`business_type`,`housing_type`,`construction_name`,`site_region_city`,`site_region_district`,`site_region_address`,`site_region_address_detail`,`work_type`,`work_subtype`,`work_detail_type`,`contract_work_type`,`bid_type`,`client_name`,`client_type`,`permit_agency`,`permit_date`,`contract_date`,`start_date`,`completion_date`,`bid_notice_date`,`initial_contract_amount`,`authorized_company_seal`,`note`,`memo`,`is_active`,`created_at`,`created_by`,`updated_at`,`updated_by`,`deleted_at`,`deleted_by`) VALUES ('0c62e3b2-4110-4357-8921-e94c2b309c9f','8','인천 물류센터',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'인천 물류센터 증축공사 중 석공사',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-02-25','2026-03-10','2026-07-31',NULL,'87000000.00',NULL,'증축 현장','소규모 공사','1','2026-03-27 15:27:41','SYSTEM:EXCEL_UPLOAD','2026-03-27 15:27:41','SYSTEM:EXCEL_UPLOAD',NULL,NULL);
INSERT INTO `system_projects` (`id`,`code`,`project_name`,`client_id`,`employee_id`,`site_agent`,`contract_type`,`director`,`manager`,`business_type`,`housing_type`,`construction_name`,`site_region_city`,`site_region_district`,`site_region_address`,`site_region_address_detail`,`work_type`,`work_subtype`,`work_detail_type`,`contract_work_type`,`bid_type`,`client_name`,`client_type`,`permit_agency`,`permit_date`,`contract_date`,`start_date`,`completion_date`,`bid_notice_date`,`initial_contract_amount`,`authorized_company_seal`,`note`,`memo`,`is_active`,`created_at`,`created_by`,`updated_at`,`updated_by`,`deleted_at`,`deleted_by`) VALUES ('0de85371-0304-42fe-95d9-cc6bae767bd9','2','청주 커뮤니티센터',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'산성유원지 숲속 커뮤니티센터 건립공사 중 석공사',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-01-12','2026-01-18','2026-08-31',NULL,'180000000.00',NULL,'커뮤니티시설','실내 마감 포함','1','2026-03-27 15:27:41','SYSTEM:EXCEL_UPLOAD','2026-03-27 15:27:41','SYSTEM:EXCEL_UPLOAD',NULL,NULL);
INSERT INTO `system_projects` (`id`,`code`,`project_name`,`client_id`,`employee_id`,`site_agent`,`contract_type`,`director`,`manager`,`business_type`,`housing_type`,`construction_name`,`site_region_city`,`site_region_district`,`site_region_address`,`site_region_address_detail`,`work_type`,`work_subtype`,`work_detail_type`,`contract_work_type`,`bid_type`,`client_name`,`client_type`,`permit_agency`,`permit_date`,`contract_date`,`start_date`,`completion_date`,`bid_notice_date`,`initial_contract_amount`,`authorized_company_seal`,`note`,`memo`,`is_active`,`created_at`,`created_by`,`updated_at`,`updated_by`,`deleted_at`,`deleted_by`) VALUES ('0f33b827-41fa-4ce2-810b-46ebb19a36e6','5','세종 업무시설',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'세종 업무시설 신축공사 중 석공사',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-02-14','2026-02-20','2026-10-31',NULL,'210000000.00',NULL,'공공업무시설 인접','로비/벽체','1','2026-03-27 15:27:41','SYSTEM:EXCEL_UPLOAD','2026-03-27 15:27:41','SYSTEM:EXCEL_UPLOAD',NULL,NULL);
INSERT INTO `system_projects` (`id`,`code`,`project_name`,`client_id`,`employee_id`,`site_agent`,`contract_type`,`director`,`manager`,`business_type`,`housing_type`,`construction_name`,`site_region_city`,`site_region_district`,`site_region_address`,`site_region_address_detail`,`work_type`,`work_subtype`,`work_detail_type`,`contract_work_type`,`bid_type`,`client_name`,`client_type`,`permit_agency`,`permit_date`,`contract_date`,`start_date`,`completion_date`,`bid_notice_date`,`initial_contract_amount`,`authorized_company_seal`,`note`,`memo`,`is_active`,`created_at`,`created_by`,`updated_at`,`updated_by`,`deleted_at`,`deleted_by`) VALUES ('1f59433d-e626-40ec-90d2-60c98c59f8c6','1','대치동 석향빌딩 신축','433bdb5a-da9f-4ea3-a732-273f6a911b3a','6e8fb7ef-ea70-4d37-9aed-74f33b355127','','','','','','','강남구 대치동 석향빌딩 신축공사 중 석공사','','','','','석공사','','','','','석향','','','0000-00-00','2026-01-10','2026-01-15','2026-06-30','0000-00-00','250000000.00','','주요 현장','외벽 중심','1','2026-03-27 15:27:41','SYSTEM:EXCEL_UPLOAD','2026-03-27 15:37:42','USER:f113b666-ff40-4f93-a7e7-8cea4cdc9c28','2026-03-27 15:37:42','USER:f113b666-ff40-4f93-a7e7-8cea4cdc9c28');
INSERT INTO `system_projects` (`id`,`code`,`project_name`,`client_id`,`employee_id`,`site_agent`,`contract_type`,`director`,`manager`,`business_type`,`housing_type`,`construction_name`,`site_region_city`,`site_region_district`,`site_region_address`,`site_region_address_detail`,`work_type`,`work_subtype`,`work_detail_type`,`contract_work_type`,`bid_type`,`client_name`,`client_type`,`permit_agency`,`permit_date`,`contract_date`,`start_date`,`completion_date`,`bid_notice_date`,`initial_contract_amount`,`authorized_company_seal`,`note`,`memo`,`is_active`,`created_at`,`created_by`,`updated_at`,`updated_by`,`deleted_at`,`deleted_by`) VALUES ('2462ccb4-a335-4231-88bf-ed4327f047a1','11','평택 공장 증설',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'평택 공장 증설공사 중 석공사',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-03-10','2026-03-25','2026-09-15',NULL,'165000000.00',NULL,'공장현장','관리동 포함','1','2026-03-27 15:27:42','SYSTEM:EXCEL_UPLOAD','2026-03-27 15:27:42','SYSTEM:EXCEL_UPLOAD',NULL,NULL);
INSERT INTO `system_projects` (`id`,`code`,`project_name`,`client_id`,`employee_id`,`site_agent`,`contract_type`,`director`,`manager`,`business_type`,`housing_type`,`construction_name`,`site_region_city`,`site_region_district`,`site_region_address`,`site_region_address_detail`,`work_type`,`work_subtype`,`work_detail_type`,`contract_work_type`,`bid_type`,`client_name`,`client_type`,`permit_agency`,`permit_date`,`contract_date`,`start_date`,`completion_date`,`bid_notice_date`,`initial_contract_amount`,`authorized_company_seal`,`note`,`memo`,`is_active`,`created_at`,`created_by`,`updated_at`,`updated_by`,`deleted_at`,`deleted_by`) VALUES ('25227ea1-8faa-438e-bed0-37e1e23e81fb','3','남해 호텔 보수',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'남해 라피스호텔 보수공사 중 석공사',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-01-20','2026-02-01','2026-05-31',NULL,'95000000.00',NULL,'호텔 공사','기존 외벽 보수','1','2026-03-27 15:27:41','SYSTEM:EXCEL_UPLOAD','2026-03-27 15:27:41','SYSTEM:EXCEL_UPLOAD',NULL,NULL);
INSERT INTO `system_projects` (`id`,`code`,`project_name`,`client_id`,`employee_id`,`site_agent`,`contract_type`,`director`,`manager`,`business_type`,`housing_type`,`construction_name`,`site_region_city`,`site_region_district`,`site_region_address`,`site_region_address_detail`,`work_type`,`work_subtype`,`work_detail_type`,`contract_work_type`,`bid_type`,`client_name`,`client_type`,`permit_agency`,`permit_date`,`contract_date`,`start_date`,`completion_date`,`bid_notice_date`,`initial_contract_amount`,`authorized_company_seal`,`note`,`memo`,`is_active`,`created_at`,`created_by`,`updated_at`,`updated_by`,`deleted_at`,`deleted_by`) VALUES ('34d7da5e-7fb8-439c-944e-88f075843c75','4','판교 오피스텔',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'판교 오피스텔 신축공사 중 석공사',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-02-10','2026-02-15','2026-09-30',NULL,'320000000.00',NULL,'분당권역','오피스텔 신축','1','2026-03-27 15:27:41','SYSTEM:EXCEL_UPLOAD','2026-03-27 15:27:41','SYSTEM:EXCEL_UPLOAD',NULL,NULL);
INSERT INTO `system_projects` (`id`,`code`,`project_name`,`client_id`,`employee_id`,`site_agent`,`contract_type`,`director`,`manager`,`business_type`,`housing_type`,`construction_name`,`site_region_city`,`site_region_district`,`site_region_address`,`site_region_address_detail`,`work_type`,`work_subtype`,`work_detail_type`,`contract_work_type`,`bid_type`,`client_name`,`client_type`,`permit_agency`,`permit_date`,`contract_date`,`start_date`,`completion_date`,`bid_notice_date`,`initial_contract_amount`,`authorized_company_seal`,`note`,`memo`,`is_active`,`created_at`,`created_by`,`updated_at`,`updated_by`,`deleted_at`,`deleted_by`) VALUES ('51b1486e-9bcd-473f-90bc-497b9ddca4a0','10','광주 오피스',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'광주 오피스 리모델링 공사 중 석공사',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-03-05','2026-03-20','2026-06-30',NULL,'76000000.00',NULL,'리모델링 현장','실내 중심','1','2026-03-27 15:27:42','SYSTEM:EXCEL_UPLOAD','2026-03-27 15:27:42','SYSTEM:EXCEL_UPLOAD',NULL,NULL);
INSERT INTO `system_projects` (`id`,`code`,`project_name`,`client_id`,`employee_id`,`site_agent`,`contract_type`,`director`,`manager`,`business_type`,`housing_type`,`construction_name`,`site_region_city`,`site_region_district`,`site_region_address`,`site_region_address_detail`,`work_type`,`work_subtype`,`work_detail_type`,`contract_work_type`,`bid_type`,`client_name`,`client_type`,`permit_agency`,`permit_date`,`contract_date`,`start_date`,`completion_date`,`bid_notice_date`,`initial_contract_amount`,`authorized_company_seal`,`note`,`memo`,`is_active`,`created_at`,`created_by`,`updated_at`,`updated_by`,`deleted_at`,`deleted_by`) VALUES ('6fcf95c8-dbdd-41ea-903f-48e96e943646','12','송도 복합시설',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'송도 복합시설 신축공사 중 석공사',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-03-15','2026-04-01','2027-02-28',NULL,'620000000.00',NULL,'대형 복합시설','내외장 포함','1','2026-03-27 15:27:42','SYSTEM:EXCEL_UPLOAD','2026-03-27 15:27:42','SYSTEM:EXCEL_UPLOAD',NULL,NULL);
INSERT INTO `system_projects` (`id`,`code`,`project_name`,`client_id`,`employee_id`,`site_agent`,`contract_type`,`director`,`manager`,`business_type`,`housing_type`,`construction_name`,`site_region_city`,`site_region_district`,`site_region_address`,`site_region_address_detail`,`work_type`,`work_subtype`,`work_detail_type`,`contract_work_type`,`bid_type`,`client_name`,`client_type`,`permit_agency`,`permit_date`,`contract_date`,`start_date`,`completion_date`,`bid_notice_date`,`initial_contract_amount`,`authorized_company_seal`,`note`,`memo`,`is_active`,`created_at`,`created_by`,`updated_at`,`updated_by`,`deleted_at`,`deleted_by`) VALUES ('73a6dca5-296f-470c-9e1a-829b368bb3d7','9','제주 리조트',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'제주 리조트 신축공사 중 석공사',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-03-01','2026-03-15','2027-01-31',NULL,'530000000.00',NULL,'리조트 현장','대형 프로젝트','1','2026-03-27 15:27:42','SYSTEM:EXCEL_UPLOAD','2026-03-27 15:27:42','SYSTEM:EXCEL_UPLOAD',NULL,NULL);
INSERT INTO `system_projects` (`id`,`code`,`project_name`,`client_id`,`employee_id`,`site_agent`,`contract_type`,`director`,`manager`,`business_type`,`housing_type`,`construction_name`,`site_region_city`,`site_region_district`,`site_region_address`,`site_region_address_detail`,`work_type`,`work_subtype`,`work_detail_type`,`contract_work_type`,`bid_type`,`client_name`,`client_type`,`permit_agency`,`permit_date`,`contract_date`,`start_date`,`completion_date`,`bid_notice_date`,`initial_contract_amount`,`authorized_company_seal`,`note`,`memo`,`is_active`,`created_at`,`created_by`,`updated_at`,`updated_by`,`deleted_at`,`deleted_by`) VALUES ('94850e11-fd80-43d9-9f0d-955f661ea8d9','6','부산 상가 신축',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'부산 상가 신축공사 중 석공사',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-02-18','2026-03-01','2026-11-15',NULL,'275000000.00',NULL,'상가시설','외장 위주','1','2026-03-27 15:27:41','SYSTEM:EXCEL_UPLOAD','2026-03-27 15:27:41','SYSTEM:EXCEL_UPLOAD',NULL,NULL);
INSERT INTO `system_projects` (`id`,`code`,`project_name`,`client_id`,`employee_id`,`site_agent`,`contract_type`,`director`,`manager`,`business_type`,`housing_type`,`construction_name`,`site_region_city`,`site_region_district`,`site_region_address`,`site_region_address_detail`,`work_type`,`work_subtype`,`work_detail_type`,`contract_work_type`,`bid_type`,`client_name`,`client_type`,`permit_agency`,`permit_date`,`contract_date`,`start_date`,`completion_date`,`bid_notice_date`,`initial_contract_amount`,`authorized_company_seal`,`note`,`memo`,`is_active`,`created_at`,`created_by`,`updated_at`,`updated_by`,`deleted_at`,`deleted_by`) VALUES ('f8a6ff01-afb7-4174-aeb1-193d3fa31e2c','7','대전 주상복합',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'대전 주상복합 신축공사 중 석공사',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-02-22','2026-03-05','2026-12-20',NULL,'410000000.00',NULL,'주상복합','고급 마감','1','2026-03-27 15:27:41','SYSTEM:EXCEL_UPLOAD','2026-03-27 15:27:41','SYSTEM:EXCEL_UPLOAD',NULL,NULL);

-- ----------------------------
-- Table structure for `system_settings_config`
-- ----------------------------
DROP TABLE IF EXISTS `system_settings_config`;
CREATE TABLE `system_settings_config` (
  `config_key` varchar(100) NOT NULL COMMENT '환경설정 키(PK).\r\n네이밍 규칙: {category_prefix}_{name}\r\n예: session_timeout, site_slogan, api_enabled, security_password_min',
  `config_value` text DEFAULT NULL COMMENT '환경설정 값.\r\n모든 값은 문자열(TEXT)로 저장.\r\n숫자/Boolean/JSON 타입 변환은 Service 계층에서 처리.\r\n예: "3600", "1", "Noto Sans KR", "1.1.1.1,2.2.2.2", JSON 문자열',
  `category` varchar(50) NOT NULL COMMENT '설정 분류(필수).\r\n화면 탭 / 기능 그룹 기준.\r\n권장값:\r\nSITE | SESSION | API | SECURITY | UPLOAD | BACKUP | LOG | SYSTEM\r\nENUM 미사용 (확장성 우선)',
  `description` varchar(255) DEFAULT NULL COMMENT '관리자 화면 표시용 설명.\r\n예: "세션 유지 시간(분)", "API 요청 제한(분당)"',
  `is_editable` tinyint(1) DEFAULT 1 COMMENT '수정 가능 여부.\r\n1 = 관리자 화면/API에서 수정 가능\r\n0 = 시스템 고정값 (UI/REST에서 수정 금지 권장)',
  `created_at` datetime DEFAULT current_timestamp() COMMENT '등록 일시(최초 생성 시각)',
  `created_by` varchar(36) DEFAULT NULL COMMENT '등록자 ID(Users UUID 등). 자동기록 권장',
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp() COMMENT '수정 일시(값 변경 시 자동 갱신)',
  `updated_by` varchar(36) DEFAULT NULL COMMENT '수정자 ID(Users UUID 등). 자동기록 권장',
  PRIMARY KEY (`config_key`) USING BTREE,
  KEY `idx_category` (`category`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='시스템 전역 설정(Key-Value) 테이블.\r\n단일 테이블로 SITE / SESSION / API / SECURITY / UPLOAD / BACKUP / LOG 등\r\n모든 시스템 설정을 통합 관리.\r\n타입 캐스팅은 Service 계층 책임으로 설계';

-- Data for `system_settings_config`
INSERT INTO `system_settings_config` (`config_key`,`config_value`,`category`,`description`,`is_editable`,`created_at`,`created_by`,`updated_at`,`updated_by`) VALUES ('alert_style','soft','SITE','알림 스타일','1','2025-12-13 18:58:55','f113b666-ff40-4f93-a7e7-8cea4cdc9c28','2026-03-18 21:59:05','f113b666-ff40-4f93-a7e7-8cea4cdc9c28');
INSERT INTO `system_settings_config` (`config_key`,`config_value`,`category`,`description`,`is_editable`,`created_at`,`created_by`,`updated_at`,`updated_by`) VALUES ('api_callback_url','','API','API Callback URL','1','2025-12-16 13:16:40','f113b666-ff40-4f93-a7e7-8cea4cdc9c28','2025-12-16 19:53:08','f113b666-ff40-4f93-a7e7-8cea4cdc9c28');
INSERT INTO `system_settings_config` (`config_key`,`config_value`,`category`,`description`,`is_editable`,`created_at`,`created_by`,`updated_at`,`updated_by`) VALUES ('api_enabled','1','API','외부 API 사용 여부','1','2025-12-16 13:16:40','f113b666-ff40-4f93-a7e7-8cea4cdc9c28','2025-12-16 19:53:08','f113b666-ff40-4f93-a7e7-8cea4cdc9c28');
INSERT INTO `system_settings_config` (`config_key`,`config_value`,`category`,`description`,`is_editable`,`created_at`,`created_by`,`updated_at`,`updated_by`) VALUES ('api_ip_whitelist','','API','외부 API 호출 허용 IP 화이트리스트','1','2025-12-16 13:16:40','f113b666-ff40-4f93-a7e7-8cea4cdc9c28','2025-12-16 19:53:08','f113b666-ff40-4f93-a7e7-8cea4cdc9c28');
INSERT INTO `system_settings_config` (`config_key`,`config_value`,`category`,`description`,`is_editable`,`created_at`,`created_by`,`updated_at`,`updated_by`) VALUES ('api_key','VLrMnFPk0L7ENpfeIJkHgpc9Mmyg41SA','API','API Key','1','2025-12-16 13:16:40','f113b666-ff40-4f93-a7e7-8cea4cdc9c28','2025-12-16 19:53:08','f113b666-ff40-4f93-a7e7-8cea4cdc9c28');
INSERT INTO `system_settings_config` (`config_key`,`config_value`,`category`,`description`,`is_editable`,`created_at`,`created_by`,`updated_at`,`updated_by`) VALUES ('api_ratelimit','60','API','API 요청 제한(분당)','1','2025-12-16 13:16:40','f113b666-ff40-4f93-a7e7-8cea4cdc9c28','2025-12-16 19:53:08','f113b666-ff40-4f93-a7e7-8cea4cdc9c28');
INSERT INTO `system_settings_config` (`config_key`,`config_value`,`category`,`description`,`is_editable`,`created_at`,`created_by`,`updated_at`,`updated_by`) VALUES ('api_secret','2YhYO4g5CKHepShnu3TnZSKiaufkkLsU3hdxlDC0esdBaAWknObQJqmzuUqQUSxs','API','API Secret','1','2025-12-16 13:16:40','f113b666-ff40-4f93-a7e7-8cea4cdc9c28','2025-12-16 19:53:08','f113b666-ff40-4f93-a7e7-8cea4cdc9c28');
INSERT INTO `system_settings_config` (`config_key`,`config_value`,`category`,`description`,`is_editable`,`created_at`,`created_by`,`updated_at`,`updated_by`) VALUES ('api_token_ttl','3600','API','Access Token 만료 시간(초)','1','2025-12-16 13:16:40','f113b666-ff40-4f93-a7e7-8cea4cdc9c28','2025-12-16 19:53:08','f113b666-ff40-4f93-a7e7-8cea4cdc9c28');
INSERT INTO `system_settings_config` (`config_key`,`config_value`,`category`,`description`,`is_editable`,`created_at`,`created_by`,`updated_at`,`updated_by`) VALUES ('backup_auto_enabled','1','BACKUP','자동 백업 사용 여부','1','2025-12-18 14:53:29','f113b666-ff40-4f93-a7e7-8cea4cdc9c28','2025-12-21 17:24:27','f113b666-ff40-4f93-a7e7-8cea4cdc9c28');
INSERT INTO `system_settings_config` (`config_key`,`config_value`,`category`,`description`,`is_editable`,`created_at`,`created_by`,`updated_at`,`updated_by`) VALUES ('backup_cleanup_enabled','1','BACKUP','오래된 백업 자동 정리','1','2025-12-18 14:53:29','f113b666-ff40-4f93-a7e7-8cea4cdc9c28','2025-12-21 17:24:28','f113b666-ff40-4f93-a7e7-8cea4cdc9c28');
INSERT INTO `system_settings_config` (`config_key`,`config_value`,`category`,`description`,`is_editable`,`created_at`,`created_by`,`updated_at`,`updated_by`) VALUES ('backup_restore_secondary_enabled','1','BACKUP','Secondary DB 자동 복원 사용 여부','1','2025-12-18 17:27:12','f113b666-ff40-4f93-a7e7-8cea4cdc9c28','2025-12-21 17:24:28','f113b666-ff40-4f93-a7e7-8cea4cdc9c28');
INSERT INTO `system_settings_config` (`config_key`,`config_value`,`category`,`description`,`is_editable`,`created_at`,`created_by`,`updated_at`,`updated_by`) VALUES ('backup_retention_days','30','BACKUP','백업 보관 기간(일)','1','2025-12-18 14:53:29','f113b666-ff40-4f93-a7e7-8cea4cdc9c28','2025-12-21 17:24:28','f113b666-ff40-4f93-a7e7-8cea4cdc9c28');
INSERT INTO `system_settings_config` (`config_key`,`config_value`,`category`,`description`,`is_editable`,`created_at`,`created_by`,`updated_at`,`updated_by`) VALUES ('backup_schedule','daily','BACKUP','백업 실행 주기 (daily/weekly/monthly)','1','2025-12-18 14:53:29','f113b666-ff40-4f93-a7e7-8cea4cdc9c28','2025-12-21 17:24:28','f113b666-ff40-4f93-a7e7-8cea4cdc9c28');
INSERT INTO `system_settings_config` (`config_key`,`config_value`,`category`,`description`,`is_editable`,`created_at`,`created_by`,`updated_at`,`updated_by`) VALUES ('button_style','solid','SITE','버튼 스타일','1','2025-12-13 18:58:55','f113b666-ff40-4f93-a7e7-8cea4cdc9c28','2026-03-18 21:59:05','f113b666-ff40-4f93-a7e7-8cea4cdc9c28');
INSERT INTO `system_settings_config` (`config_key`,`config_value`,`category`,`description`,`is_editable`,`created_at`,`created_by`,`updated_at`,`updated_by`) VALUES ('card_density','compact','SITE','카드 밀도','1','2025-12-13 18:58:55','f113b666-ff40-4f93-a7e7-8cea4cdc9c28','2026-03-18 21:59:05','f113b666-ff40-4f93-a7e7-8cea4cdc9c28');
INSERT INTO `system_settings_config` (`config_key`,`config_value`,`category`,`description`,`is_editable`,`created_at`,`created_by`,`updated_at`,`updated_by`) VALUES ('font_scale','small','SITE','글꼴 크기','1','2025-12-13 18:58:55','f113b666-ff40-4f93-a7e7-8cea4cdc9c28','2026-03-18 21:59:04','f113b666-ff40-4f93-a7e7-8cea4cdc9c28');
INSERT INTO `system_settings_config` (`config_key`,`config_value`,`category`,`description`,`is_editable`,`created_at`,`created_by`,`updated_at`,`updated_by`) VALUES ('footer_text','ⓒ 2025 SUKHYANG ERP. All rights reserved. by jh.Lee.','SITE','푸터 문구','1','2025-12-13 18:58:54','f113b666-ff40-4f93-a7e7-8cea4cdc9c28','2026-03-18 21:59:04','f113b666-ff40-4f93-a7e7-8cea4cdc9c28');
INSERT INTO `system_settings_config` (`config_key`,`config_value`,`category`,`description`,`is_editable`,`created_at`,`created_by`,`updated_at`,`updated_by`) VALUES ('home_intro_description','당신이 배를 만들고 싶다면, 사람들에게\n목재를 가져오게 하고 일을 지시하고 \n일감을 나눠주는 일을 하지 말라.\n대신 그들에게 더 넓고 끝없는 바다에 \n대한동경심을 키워줘라.','SITE','홈 소개 설명','1','2025-12-13 18:58:54','f113b666-ff40-4f93-a7e7-8cea4cdc9c28','2026-03-18 21:59:04','f113b666-ff40-4f93-a7e7-8cea4cdc9c28');
INSERT INTO `system_settings_config` (`config_key`,`config_value`,`category`,`description`,`is_editable`,`created_at`,`created_by`,`updated_at`,`updated_by`) VALUES ('home_intro_title','앙투안 드 생텍쥐페리','SITE','홈 소개 제목','1','2025-12-13 18:58:54','f113b666-ff40-4f93-a7e7-8cea4cdc9c28','2026-03-18 21:59:04','f113b666-ff40-4f93-a7e7-8cea4cdc9c28');
INSERT INTO `system_settings_config` (`config_key`,`config_value`,`category`,`description`,`is_editable`,`created_at`,`created_by`,`updated_at`,`updated_by`) VALUES ('home_intro_url','https://ko.wikipedia.org/wiki/%EC%95%99%ED%88%AC%EC%95%88_%EB%93%9C_%EC%83%9D%ED%85%8D%EC%A5%90%ED%8E%98%EB%A6%AC','SITE','홈 소개 링크','1','2025-12-13 18:58:54','f113b666-ff40-4f93-a7e7-8cea4cdc9c28','2026-03-18 21:59:04','f113b666-ff40-4f93-a7e7-8cea4cdc9c28');
INSERT INTO `system_settings_config` (`config_key`,`config_value`,`category`,`description`,`is_editable`,`created_at`,`created_by`,`updated_at`,`updated_by`) VALUES ('icon_scale','normal','SITE','아이콘 크기','1','2025-12-13 18:58:55','f113b666-ff40-4f93-a7e7-8cea4cdc9c28','2026-03-18 21:59:05','f113b666-ff40-4f93-a7e7-8cea4cdc9c28');
INSERT INTO `system_settings_config` (`config_key`,`config_value`,`category`,`description`,`is_editable`,`created_at`,`created_by`,`updated_at`,`updated_by`) VALUES ('link_underline','off','SITE','링크 밑줄','1','2025-12-13 18:58:55','f113b666-ff40-4f93-a7e7-8cea4cdc9c28','2026-03-18 21:59:05','f113b666-ff40-4f93-a7e7-8cea4cdc9c28');
INSERT INTO `system_settings_config` (`config_key`,`config_value`,`category`,`description`,`is_editable`,`created_at`,`created_by`,`updated_at`,`updated_by`) VALUES ('motion_mode','on','SITE','모션 효과','1','2025-12-13 18:58:55','f113b666-ff40-4f93-a7e7-8cea4cdc9c28','2026-03-18 21:59:05','f113b666-ff40-4f93-a7e7-8cea4cdc9c28');
INSERT INTO `system_settings_config` (`config_key`,`config_value`,`category`,`description`,`is_editable`,`created_at`,`created_by`,`updated_at`,`updated_by`) VALUES ('page_title','(주)석향 :: 통합관리시스템','SITE','브라우저 페이지 제목','1','2025-12-13 18:58:54','f113b666-ff40-4f93-a7e7-8cea4cdc9c28','2026-03-18 21:59:04','f113b666-ff40-4f93-a7e7-8cea4cdc9c28');
INSERT INTO `system_settings_config` (`config_key`,`config_value`,`category`,`description`,`is_editable`,`created_at`,`created_by`,`updated_at`,`updated_by`) VALUES ('radius_style','rounded','SITE','모서리 스타일','1','2025-12-13 18:58:55','f113b666-ff40-4f93-a7e7-8cea4cdc9c28','2026-03-18 21:59:05','f113b666-ff40-4f93-a7e7-8cea4cdc9c28');
INSERT INTO `system_settings_config` (`config_key`,`config_value`,`category`,`description`,`is_editable`,`created_at`,`created_by`,`updated_at`,`updated_by`) VALUES ('row_focus','soft','SITE','행 포커스','1','2025-12-13 18:58:55','f113b666-ff40-4f93-a7e7-8cea4cdc9c28','2026-03-18 21:59:05','f113b666-ff40-4f93-a7e7-8cea4cdc9c28');
INSERT INTO `system_settings_config` (`config_key`,`config_value`,`category`,`description`,`is_editable`,`created_at`,`created_by`,`updated_at`,`updated_by`) VALUES ('security_access_policy_enabled','1','SECURITY','접근 보안 정책 사용 여부','1','2025-12-15 19:13:49','f113b666-ff40-4f93-a7e7-8cea4cdc9c28','2025-12-19 17:32:36','f113b666-ff40-4f93-a7e7-8cea4cdc9c28');
INSERT INTO `system_settings_config` (`config_key`,`config_value`,`category`,`description`,`is_editable`,`created_at`,`created_by`,`updated_at`,`updated_by`) VALUES ('security_force_2fa','0','SECURITY','전 직원 2차 인증 강제','1','2025-12-15 19:13:49','f113b666-ff40-4f93-a7e7-8cea4cdc9c28','2025-12-19 17:32:36','f113b666-ff40-4f93-a7e7-8cea4cdc9c28');
INSERT INTO `system_settings_config` (`config_key`,`config_value`,`category`,`description`,`is_editable`,`created_at`,`created_by`,`updated_at`,`updated_by`) VALUES ('security_inactive_lock_days','31','SECURITY','미접속 계정 잠금 일수','1','2025-12-15 19:13:49','f113b666-ff40-4f93-a7e7-8cea4cdc9c28','2025-12-19 17:32:36','f113b666-ff40-4f93-a7e7-8cea4cdc9c28');
INSERT INTO `system_settings_config` (`config_key`,`config_value`,`category`,`description`,`is_editable`,`created_at`,`created_by`,`updated_at`,`updated_by`) VALUES ('security_inactive_warn_days','20','SECURITY','미접속 경고 후 추가 인증 일수','1','2025-12-15 19:13:49','f113b666-ff40-4f93-a7e7-8cea4cdc9c28','2025-12-19 17:32:36','f113b666-ff40-4f93-a7e7-8cea4cdc9c28');
INSERT INTO `system_settings_config` (`config_key`,`config_value`,`category`,`description`,`is_editable`,`created_at`,`created_by`,`updated_at`,`updated_by`) VALUES ('security_login_fail_max','5','SECURITY','로그인 실패 허용 횟수','1','2025-12-15 19:13:48','f113b666-ff40-4f93-a7e7-8cea4cdc9c28','2025-12-19 17:32:36','f113b666-ff40-4f93-a7e7-8cea4cdc9c28');
INSERT INTO `system_settings_config` (`config_key`,`config_value`,`category`,`description`,`is_editable`,`created_at`,`created_by`,`updated_at`,`updated_by`) VALUES ('security_login_fail_policy_enabled','1','SECURITY','로그인 실패 정책 사용 여부','1','2025-12-15 19:13:48','f113b666-ff40-4f93-a7e7-8cea4cdc9c28','2025-12-19 17:32:36','f113b666-ff40-4f93-a7e7-8cea4cdc9c28');
INSERT INTO `system_settings_config` (`config_key`,`config_value`,`category`,`description`,`is_editable`,`created_at`,`created_by`,`updated_at`,`updated_by`) VALUES ('security_login_lock_minutes','3','SECURITY','로그인 잠금 시간(분)','1','2025-12-15 19:13:49','f113b666-ff40-4f93-a7e7-8cea4cdc9c28','2025-12-19 17:32:36','f113b666-ff40-4f93-a7e7-8cea4cdc9c28');
INSERT INTO `system_settings_config` (`config_key`,`config_value`,`category`,`description`,`is_editable`,`created_at`,`created_by`,`updated_at`,`updated_by`) VALUES ('security_login_time_end','23:30','SECURITY','로그인 허용 종료 시간','1','2025-12-15 19:13:49','f113b666-ff40-4f93-a7e7-8cea4cdc9c28','2025-12-19 17:32:36','f113b666-ff40-4f93-a7e7-8cea4cdc9c28');
INSERT INTO `system_settings_config` (`config_key`,`config_value`,`category`,`description`,`is_editable`,`created_at`,`created_by`,`updated_at`,`updated_by`) VALUES ('security_login_time_start','07:00','SECURITY','로그인 허용 시작 시간','1','2025-12-15 19:13:49','f113b666-ff40-4f93-a7e7-8cea4cdc9c28','2025-12-19 17:32:36','f113b666-ff40-4f93-a7e7-8cea4cdc9c28');
INSERT INTO `system_settings_config` (`config_key`,`config_value`,`category`,`description`,`is_editable`,`created_at`,`created_by`,`updated_at`,`updated_by`) VALUES ('security_new_device_2fa','1','SECURITY','신규 기기 로그인 시 추가 인증','1','2025-12-15 19:13:49','f113b666-ff40-4f93-a7e7-8cea4cdc9c28','2025-12-19 17:32:36','f113b666-ff40-4f93-a7e7-8cea4cdc9c28');
INSERT INTO `system_settings_config` (`config_key`,`config_value`,`category`,`description`,`is_editable`,`created_at`,`created_by`,`updated_at`,`updated_by`) VALUES ('security_password_expire','30','SECURITY','비밀번호 만료 일수','1','2025-12-15 19:13:48','f113b666-ff40-4f93-a7e7-8cea4cdc9c28','2025-12-19 17:32:36','f113b666-ff40-4f93-a7e7-8cea4cdc9c28');
INSERT INTO `system_settings_config` (`config_key`,`config_value`,`category`,`description`,`is_editable`,`created_at`,`created_by`,`updated_at`,`updated_by`) VALUES ('security_password_min','4','SECURITY','비밀번호 최소 길이','1','2025-12-15 19:13:48','f113b666-ff40-4f93-a7e7-8cea4cdc9c28','2025-12-19 17:32:35','f113b666-ff40-4f93-a7e7-8cea4cdc9c28');
INSERT INTO `system_settings_config` (`config_key`,`config_value`,`category`,`description`,`is_editable`,`created_at`,`created_by`,`updated_at`,`updated_by`) VALUES ('security_password_policy_enabled','1','SECURITY','비밀번호 정책 사용 여부','1','2025-12-15 19:13:48','f113b666-ff40-4f93-a7e7-8cea4cdc9c28','2025-12-19 17:32:35','f113b666-ff40-4f93-a7e7-8cea4cdc9c28');
INSERT INTO `system_settings_config` (`config_key`,`config_value`,`category`,`description`,`is_editable`,`created_at`,`created_by`,`updated_at`,`updated_by`) VALUES ('security_pw_number','0','SECURITY','비밀번호 숫자 필수','1','2025-12-15 19:13:48','f113b666-ff40-4f93-a7e7-8cea4cdc9c28','2025-12-19 17:32:36','f113b666-ff40-4f93-a7e7-8cea4cdc9c28');
INSERT INTO `system_settings_config` (`config_key`,`config_value`,`category`,`description`,`is_editable`,`created_at`,`created_by`,`updated_at`,`updated_by`) VALUES ('security_pw_special','0','SECURITY','비밀번호 특수문자 필수','1','2025-12-15 19:13:48','f113b666-ff40-4f93-a7e7-8cea4cdc9c28','2025-12-19 17:32:36','f113b666-ff40-4f93-a7e7-8cea4cdc9c28');
INSERT INTO `system_settings_config` (`config_key`,`config_value`,`category`,`description`,`is_editable`,`created_at`,`created_by`,`updated_at`,`updated_by`) VALUES ('security_pw_upper','0','SECURITY','비밀번호 대문자 필수','1','2025-12-15 19:13:48','f113b666-ff40-4f93-a7e7-8cea4cdc9c28','2025-12-19 17:32:36','f113b666-ff40-4f93-a7e7-8cea4cdc9c28');
INSERT INTO `system_settings_config` (`config_key`,`config_value`,`category`,`description`,`is_editable`,`created_at`,`created_by`,`updated_at`,`updated_by`) VALUES ('session_alert','5','SESSION','세션 만료 알림 시간(분)','1','2025-12-13 18:59:20','f113b666-ff40-4f93-a7e7-8cea4cdc9c28','2025-12-19 20:56:36','f113b666-ff40-4f93-a7e7-8cea4cdc9c28');
INSERT INTO `system_settings_config` (`config_key`,`config_value`,`category`,`description`,`is_editable`,`created_at`,`created_by`,`updated_at`,`updated_by`) VALUES ('session_sound','default.mp3','SESSION','세션 만료 알림 사운드','1','2025-12-13 18:59:20','f113b666-ff40-4f93-a7e7-8cea4cdc9c28','2025-12-19 20:56:36','f113b666-ff40-4f93-a7e7-8cea4cdc9c28');
INSERT INTO `system_settings_config` (`config_key`,`config_value`,`category`,`description`,`is_editable`,`created_at`,`created_by`,`updated_at`,`updated_by`) VALUES ('session_timeout','60','SESSION','세션 유지 시간(분)','1','2025-12-13 18:59:19','f113b666-ff40-4f93-a7e7-8cea4cdc9c28','2025-12-19 20:56:36','f113b666-ff40-4f93-a7e7-8cea4cdc9c28');
INSERT INTO `system_settings_config` (`config_key`,`config_value`,`category`,`description`,`is_editable`,`created_at`,`created_by`,`updated_at`,`updated_by`) VALUES ('sidebar_default','expanded','SITE','사이드바 기본 상태','1','2025-12-13 18:58:55','f113b666-ff40-4f93-a7e7-8cea4cdc9c28','2026-03-18 21:59:05','f113b666-ff40-4f93-a7e7-8cea4cdc9c28');
INSERT INTO `system_settings_config` (`config_key`,`config_value`,`category`,`description`,`is_editable`,`created_at`,`created_by`,`updated_at`,`updated_by`) VALUES ('site_font_family','Noto Sans KR','SITE','기본 글꼴','1','2025-12-13 18:58:55','f113b666-ff40-4f93-a7e7-8cea4cdc9c28','2026-03-18 21:59:04','f113b666-ff40-4f93-a7e7-8cea4cdc9c28');
INSERT INTO `system_settings_config` (`config_key`,`config_value`,`category`,`description`,`is_editable`,`created_at`,`created_by`,`updated_at`,`updated_by`) VALUES ('site_title','SUKHYANG ERP(NEW)','SITE','사이트 제목','1','2025-12-13 18:58:54','f113b666-ff40-4f93-a7e7-8cea4cdc9c28','2026-03-18 21:59:04','f113b666-ff40-4f93-a7e7-8cea4cdc9c28');
INSERT INTO `system_settings_config` (`config_key`,`config_value`,`category`,`description`,`is_editable`,`created_at`,`created_by`,`updated_at`,`updated_by`) VALUES ('synology_caldav_path','/caldav.php/','EXTERNAL_SERVICE','CalDAV 경로','1','2026-01-10 19:20:17','f113b666-ff40-4f93-a7e7-8cea4cdc9c28','2026-01-10 19:20:17','f113b666-ff40-4f93-a7e7-8cea4cdc9c28');
INSERT INTO `system_settings_config` (`config_key`,`config_value`,`category`,`description`,`is_editable`,`created_at`,`created_by`,`updated_at`,`updated_by`) VALUES ('synology_enabled','1','EXTERNAL_SERVICE','Synology Calendar 사용 여부','1','2026-01-10 19:20:17','f113b666-ff40-4f93-a7e7-8cea4cdc9c28','2026-01-10 19:20:17','f113b666-ff40-4f93-a7e7-8cea4cdc9c28');
INSERT INTO `system_settings_config` (`config_key`,`config_value`,`category`,`description`,`is_editable`,`created_at`,`created_by`,`updated_at`,`updated_by`) VALUES ('synology_host','https://sukhyang.synology.me:20003','EXTERNAL_SERVICE','Synology 서버 주소','1','2026-01-10 19:20:17','f113b666-ff40-4f93-a7e7-8cea4cdc9c28','2026-01-10 19:20:17','f113b666-ff40-4f93-a7e7-8cea4cdc9c28');
INSERT INTO `system_settings_config` (`config_key`,`config_value`,`category`,`description`,`is_editable`,`created_at`,`created_by`,`updated_at`,`updated_by`) VALUES ('synology_ssl_verify','1','EXTERNAL_SERVICE','SSL 인증서 검증 여부','1','2026-01-10 19:20:17','f113b666-ff40-4f93-a7e7-8cea4cdc9c28','2026-01-10 19:20:17','f113b666-ff40-4f93-a7e7-8cea4cdc9c28');
INSERT INTO `system_settings_config` (`config_key`,`config_value`,`category`,`description`,`is_editable`,`created_at`,`created_by`,`updated_at`,`updated_by`) VALUES ('table_density','compact','SITE','테이블 밀도','1','2025-12-13 18:58:55','f113b666-ff40-4f93-a7e7-8cea4cdc9c28','2026-03-18 21:59:04','f113b666-ff40-4f93-a7e7-8cea4cdc9c28');
INSERT INTO `system_settings_config` (`config_key`,`config_value`,`category`,`description`,`is_editable`,`created_at`,`created_by`,`updated_at`,`updated_by`) VALUES ('theme_mode','system','SITE','테마 모드','1','2025-12-13 18:58:55','f113b666-ff40-4f93-a7e7-8cea4cdc9c28','2026-03-18 21:59:04','f113b666-ff40-4f93-a7e7-8cea4cdc9c28');
INSERT INTO `system_settings_config` (`config_key`,`config_value`,`category`,`description`,`is_editable`,`created_at`,`created_by`,`updated_at`,`updated_by`) VALUES ('ui_skin','dark','SITE','UI 스킨','1','2025-12-13 18:58:54','f113b666-ff40-4f93-a7e7-8cea4cdc9c28','2026-03-18 21:59:04','f113b666-ff40-4f93-a7e7-8cea4cdc9c28');

-- ----------------------------
-- Table structure for `user_approval_request_steps`
-- ----------------------------
DROP TABLE IF EXISTS `user_approval_request_steps`;
CREATE TABLE `user_approval_request_steps` (
  `id` varchar(36) NOT NULL COMMENT 'UUID: 실제 결재 단계 ID',
  `request_id` varchar(36) NOT NULL COMMENT '결재 요청 ID (user_approval_requests.id)',
  `sequence` int(11) NOT NULL COMMENT '결재 순서',
  `approver_id` varchar(36) NOT NULL COMMENT '해당 단계 결재자 ID',
  `status` enum('pending','approved','rejected') NOT NULL DEFAULT 'pending' COMMENT '결재 상태',
  `approved_at` datetime DEFAULT NULL COMMENT '승인 일시',
  `rejected_at` datetime DEFAULT NULL COMMENT '반려 일시',
  `comment` text DEFAULT NULL COMMENT '결재 의견',
  `is_active` tinyint(1) NOT NULL DEFAULT 1 COMMENT '활성 여부',
  `created_at` datetime NOT NULL DEFAULT current_timestamp() COMMENT '생성 일시',
  `created_by` varchar(36) DEFAULT NULL COMMENT '생성자',
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp() COMMENT '수정 일시',
  `updated_by` varchar(36) DEFAULT NULL COMMENT '수정자',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_request_sequence` (`request_id`,`sequence`),
  KEY `fk_request_steps_approver` (`approver_id`),
  CONSTRAINT `fk_request_steps_approver` FOREIGN KEY (`approver_id`) REFERENCES `auth_users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_request_steps_request` FOREIGN KEY (`request_id`) REFERENCES `user_approval_requests` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='실제 결재 단계 진행 기록';

-- `user_approval_request_steps` 데이터 없음

-- ----------------------------
-- Table structure for `user_approval_requests`
-- ----------------------------
DROP TABLE IF EXISTS `user_approval_requests`;
CREATE TABLE `user_approval_requests` (
  `id` varchar(36) NOT NULL COMMENT 'UUID: 실제 결재 요청 ID',
  `template_id` varchar(36) NOT NULL COMMENT '사용된 결재 템플릿 ID',
  `document_id` varchar(36) NOT NULL COMMENT 'ERP 본문 문서 ID (지출결의서, 휴가신청서 등)',
  `requester_id` varchar(36) NOT NULL COMMENT '기안자 ID (auth_users.id)',
  `status` enum('pending','in_progress','approved','rejected') NOT NULL DEFAULT 'pending' COMMENT '전체 결재 상태',
  `current_step` int(11) NOT NULL DEFAULT 1 COMMENT '현재 진행중인 단계 번호',
  `is_active` tinyint(1) NOT NULL DEFAULT 1 COMMENT '활성 여부 (삭제 대신 비활성화)',
  `created_at` datetime NOT NULL DEFAULT current_timestamp() COMMENT '기안 일시',
  `created_by` varchar(36) DEFAULT NULL COMMENT '기안자 ID',
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp() COMMENT '수정 일시',
  `updated_by` varchar(36) DEFAULT NULL COMMENT '수정자 ID',
  PRIMARY KEY (`id`),
  KEY `idx_document` (`document_id`),
  KEY `fk_request_template` (`template_id`),
  CONSTRAINT `fk_request_template` FOREIGN KEY (`template_id`) REFERENCES `user_approval_templates` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='결재 요청 본문 (기안)';

-- `user_approval_requests` 데이터 없음

-- ----------------------------
-- Table structure for `user_approval_template_steps`
-- ----------------------------
DROP TABLE IF EXISTS `user_approval_template_steps`;
CREATE TABLE `user_approval_template_steps` (
  `id` varchar(36) NOT NULL COMMENT 'UUID: 템플릿 단계 고유 ID',
  `template_id` varchar(36) NOT NULL COMMENT '결재 템플릿 ID (user_approval_templates.id)',
  `sequence` int(11) NOT NULL COMMENT '결재 순서 (1부터 시작)',
  `step_name` varchar(100) DEFAULT NULL COMMENT '단계 이름 (예: 1차 결재, 부서장 승인 등)',
  `role_id` varchar(36) DEFAULT NULL COMMENT '결재자 역할 ID (auth_roles.id)',
  `approver_id` varchar(36) DEFAULT NULL COMMENT '특정 결재자 ID 지정 (auth_users.id)',
  `is_active` tinyint(1) NOT NULL DEFAULT 1 COMMENT '활성 여부 (1=활성, 0=비활성)',
  `created_at` datetime NOT NULL DEFAULT current_timestamp() COMMENT '등록 일시',
  `created_by` varchar(36) DEFAULT NULL COMMENT '등록자 ID',
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp() COMMENT '수정 일시',
  `updated_by` varchar(36) DEFAULT NULL COMMENT '수정자 ID',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_template_sequence` (`template_id`,`sequence`),
  KEY `fk_template_steps_role` (`role_id`),
  KEY `fk_template_steps_approver` (`approver_id`),
  CONSTRAINT `fk_template_steps_approver` FOREIGN KEY (`approver_id`) REFERENCES `auth_users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_template_steps_role` FOREIGN KEY (`role_id`) REFERENCES `auth_roles` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_template_steps_template` FOREIGN KEY (`template_id`) REFERENCES `user_approval_templates` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='결재 템플릿 단계 정의';

-- Data for `user_approval_template_steps`
INSERT INTO `user_approval_template_steps` (`id`,`template_id`,`sequence`,`step_name`,`role_id`,`approver_id`,`is_active`,`created_at`,`created_by`,`updated_at`,`updated_by`) VALUES ('11ced80c-ffbd-41fa-a910-dc0814e0e540','d67cbe01-e015-45dc-9dee-204ed101d958','2','부서장 검토','08361618-c06d-4fd5-b18d-be61e1b1058e',NULL,'1','2025-12-05 19:46:25','f113b666-ff40-4f93-a7e7-8cea4cdc9c28','2025-12-05 20:34:00','f113b666-ff40-4f93-a7e7-8cea4cdc9c28');
INSERT INTO `user_approval_template_steps` (`id`,`template_id`,`sequence`,`step_name`,`role_id`,`approver_id`,`is_active`,`created_at`,`created_by`,`updated_at`,`updated_by`) VALUES ('67270465-d227-4f8a-90f2-ef18976b58b6','c9209b9b-2df8-4765-9c58-9689c424989c','1','발의','08361618-c06d-4fd5-b18d-be61e1b1058e',NULL,'1','2025-12-04 19:22:45','f113b666-ff40-4f93-a7e7-8cea4cdc9c28','2025-12-05 19:55:34','f113b666-ff40-4f93-a7e7-8cea4cdc9c28');
INSERT INTO `user_approval_template_steps` (`id`,`template_id`,`sequence`,`step_name`,`role_id`,`approver_id`,`is_active`,`created_at`,`created_by`,`updated_at`,`updated_by`) VALUES ('924d952c-0a11-4b50-a4a7-95d43a6ec683','d67cbe01-e015-45dc-9dee-204ed101d958','1','발의','93d51435-597a-47d8-b909-37d1e4b6659a',NULL,'1','2025-12-05 19:46:03','f113b666-ff40-4f93-a7e7-8cea4cdc9c28','2025-12-05 20:34:00','f113b666-ff40-4f93-a7e7-8cea4cdc9c28');
INSERT INTO `user_approval_template_steps` (`id`,`template_id`,`sequence`,`step_name`,`role_id`,`approver_id`,`is_active`,`created_at`,`created_by`,`updated_at`,`updated_by`) VALUES ('d5eb0f5f-1d13-4aed-9872-ed7779b41cde','354882f0-7a39-408f-b103-8ee1b22f1857','3','대표 승인','c1c90ecf-1a44-470c-8d9c-4d6e671cdcfa','f113b666-ff40-4f93-a7e7-8cea4cdc9c28','1','2025-12-05 19:56:54','f113b666-ff40-4f93-a7e7-8cea4cdc9c28','2025-12-11 15:11:38','f113b666-ff40-4f93-a7e7-8cea4cdc9c28');
INSERT INTO `user_approval_template_steps` (`id`,`template_id`,`sequence`,`step_name`,`role_id`,`approver_id`,`is_active`,`created_at`,`created_by`,`updated_at`,`updated_by`) VALUES ('e3fe46ee-13a2-4be4-8718-1e03c17b1a6c','d67cbe01-e015-45dc-9dee-204ed101d958','3','대표 승인','c1c90ecf-1a44-470c-8d9c-4d6e671cdcfa','f113b666-ff40-4f93-a7e7-8cea4cdc9c28','1','2025-12-05 19:46:58','f113b666-ff40-4f93-a7e7-8cea4cdc9c28','2025-12-05 20:34:46','f113b666-ff40-4f93-a7e7-8cea4cdc9c28');
INSERT INTO `user_approval_template_steps` (`id`,`template_id`,`sequence`,`step_name`,`role_id`,`approver_id`,`is_active`,`created_at`,`created_by`,`updated_at`,`updated_by`) VALUES ('ebff094b-0f8f-4da6-a986-d59a5cf65ccc','354882f0-7a39-408f-b103-8ee1b22f1857','1','발의','93d51435-597a-47d8-b909-37d1e4b6659a',NULL,'1','2025-12-05 19:54:16','f113b666-ff40-4f93-a7e7-8cea4cdc9c28','2025-12-11 15:11:38','f113b666-ff40-4f93-a7e7-8cea4cdc9c28');
INSERT INTO `user_approval_template_steps` (`id`,`template_id`,`sequence`,`step_name`,`role_id`,`approver_id`,`is_active`,`created_at`,`created_by`,`updated_at`,`updated_by`) VALUES ('ee6d3af1-35c0-4b9d-8db9-0a2d3bb5444a','354882f0-7a39-408f-b103-8ee1b22f1857','2','부서장 검토','08361618-c06d-4fd5-b18d-be61e1b1058e',NULL,'1','2025-12-05 19:54:38','f113b666-ff40-4f93-a7e7-8cea4cdc9c28','2025-12-11 15:11:38','f113b666-ff40-4f93-a7e7-8cea4cdc9c28');

-- ----------------------------
-- Table structure for `user_approval_templates`
-- ----------------------------
DROP TABLE IF EXISTS `user_approval_templates`;
CREATE TABLE `user_approval_templates` (
  `id` varchar(36) NOT NULL COMMENT 'UUID: 결재 템플릿 고유 ID',
  `template_key` varchar(50) DEFAULT NULL COMMENT '템플릿 고유 키 (unique, 예: expense, vacation)',
  `template_name` varchar(100) NOT NULL COMMENT '템플릿 이름 (예: 지출결의서)',
  `document_type` varchar(100) DEFAULT NULL COMMENT '문서 유형 (예: 지출결의서, 구매요청서, 휴가신청서 등)',
  `description` text DEFAULT NULL COMMENT '템플릿 설명',
  `is_active` tinyint(1) NOT NULL DEFAULT 1 COMMENT '활성 여부 (1=활성, 0=비활성)',
  `created_at` datetime NOT NULL DEFAULT current_timestamp() COMMENT '등록 일시',
  `created_by` varchar(36) DEFAULT NULL COMMENT '등록자 ID (auth_users.id)',
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp() COMMENT '수정 일시',
  `updated_by` varchar(36) DEFAULT NULL COMMENT '수정자 ID (auth_users.id)',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_template_key` (`template_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='결재 템플릿 마스터';

-- Data for `user_approval_templates`
INSERT INTO `user_approval_templates` (`id`,`template_key`,`template_name`,`document_type`,`description`,`is_active`,`created_at`,`created_by`,`updated_at`,`updated_by`) VALUES ('354882f0-7a39-408f-b103-8ee1b22f1857','template_41015f','구매요청서','발주서','발의>부서장>대표','1','2025-12-05 19:53:53','f113b666-ff40-4f93-a7e7-8cea4cdc9c28','2025-12-11 10:59:05','f113b666-ff40-4f93-a7e7-8cea4cdc9c28');
INSERT INTO `user_approval_templates` (`id`,`template_key`,`template_name`,`document_type`,`description`,`is_active`,`created_at`,`created_by`,`updated_at`,`updated_by`) VALUES ('c9209b9b-2df8-4765-9c58-9689c424989c','template_0795ba','지출결의서','비용결제','발의>대표>경리부>결제','1','2025-12-04 13:02:07','f113b666-ff40-4f93-a7e7-8cea4cdc9c28','2025-12-10 20:21:16','f113b666-ff40-4f93-a7e7-8cea4cdc9c28');
INSERT INTO `user_approval_templates` (`id`,`template_key`,`template_name`,`document_type`,`description`,`is_active`,`created_at`,`created_by`,`updated_at`,`updated_by`) VALUES ('d67cbe01-e015-45dc-9dee-204ed101d958','template_0795bb','공사실행보고','예산계획','발의>부서장>대표','1','2025-12-04 12:42:20','f113b666-ff40-4f93-a7e7-8cea4cdc9c28','2025-12-05 20:34:37','f113b666-ff40-4f93-a7e7-8cea4cdc9c28');

-- ----------------------------
-- Table structure for `user_departments`
-- ----------------------------
DROP TABLE IF EXISTS `user_departments`;
CREATE TABLE `user_departments` (
  `id` varchar(36) NOT NULL COMMENT '부서 고유 ID (UUID)',
  `code` int(11) NOT NULL AUTO_INCREMENT COMMENT '순번 (AUTO_INCREMENT)',
  `dept_name` varchar(100) NOT NULL COMMENT '부서명',
  `manager_id` varchar(36) DEFAULT NULL COMMENT '부서장 ID (auth_users.id 참조)',
  `description` varchar(255) DEFAULT NULL COMMENT '부서 설명',
  `is_active` tinyint(1) DEFAULT 1 COMMENT '활성 상태 (1=활성, 0=비활성)',
  `created_at` datetime NOT NULL DEFAULT current_timestamp() COMMENT '등록일',
  `created_by` varchar(36) DEFAULT NULL COMMENT '동록자ID',
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp() COMMENT '수정일',
  `updated_by` varchar(36) DEFAULT NULL COMMENT '수정자ID',
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `uk_code` (`code`) USING BTREE,
  KEY `idx_manager_id` (`manager_id`) USING BTREE,
  CONSTRAINT `fk_user_departments_manager` FOREIGN KEY (`manager_id`) REFERENCES `auth_users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='조직(부서) 관리 테이블 (UUID + 순번 구조, 자체 참조 계층형 구조)';

-- Data for `user_departments`
INSERT INTO `user_departments` (`id`,`code`,`dept_name`,`manager_id`,`description`,`is_active`,`created_at`,`created_by`,`updated_at`,`updated_by`) VALUES ('33e43734-4c4a-476b-b128-94cf1cb5617b','3','건설사업부','f113b666-ff40-4f93-a7e7-8cea4cdc9c28','건설현장 운영과 공사 계획·품질·안전 관리를 수행하며 \n프로젝트 완수를 책임지는 부서입니다.','1','2025-12-03 18:17:39','f113b666-ff40-4f93-a7e7-8cea4cdc9c28','2025-12-10 18:23:09',NULL);
INSERT INTO `user_departments` (`id`,`code`,`dept_name`,`manager_id`,`description`,`is_active`,`created_at`,`created_by`,`updated_at`,`updated_by`) VALUES ('4db22ded-3881-45b3-bc98-b0ccffd13683','1','영업부','f113b666-ff40-4f93-a7e7-8cea4cdc9c28','신규 고객 발굴과 견적·계약 관리, 자재 판매 등 \n회사 매출 창출을 담당하는 부서입니다.','1','2025-11-19 15:01:34','d2c21bea-bb48-48d0-8fbf-c6c9bdac831e','2025-12-11 11:55:20','f113b666-ff40-4f93-a7e7-8cea4cdc9c28');
INSERT INTO `user_departments` (`id`,`code`,`dept_name`,`manager_id`,`description`,`is_active`,`created_at`,`created_by`,`updated_at`,`updated_by`) VALUES ('51746487-00bd-4ae7-99f0-566b26aa9edc','4','유통사업부','2e7c4808-9325-47eb-918f-472d5b626180','자재 공급과 물류 유통 업무를 총괄하며 효율적 재고 관리와\n안정적 공급망 운영을 담당하는 부서입니다.','1','2025-12-09 19:16:09','f113b666-ff40-4f93-a7e7-8cea4cdc9c28','2025-12-10 18:23:36',NULL);
INSERT INTO `user_departments` (`id`,`code`,`dept_name`,`manager_id`,`description`,`is_active`,`created_at`,`created_by`,`updated_at`,`updated_by`) VALUES ('a61a03db-f93c-4235-830c-312997b1cee4','2','관리지원본부','f113b666-ff40-4f93-a7e7-8cea4cdc9c28','회사의 경영지원과 인사·회계·총무 등 \n다양한 업무를 수행하며 조직 운영을 지원하는 본부입니다.','1','2025-12-03 18:13:07','f113b666-ff40-4f93-a7e7-8cea4cdc9c28','2025-12-10 19:42:15','f113b666-ff40-4f93-a7e7-8cea4cdc9c28');

-- ----------------------------
-- Table structure for `user_external_accounts`
-- ----------------------------
DROP TABLE IF EXISTS `user_external_accounts`;
CREATE TABLE `user_external_accounts` (
  `id` varchar(36) NOT NULL COMMENT '외부 계정 고유 ID (UUID)',
  `user_id` varchar(36) NOT NULL COMMENT '사용자 ID (auth_users.id FK)',
  `service_key` varchar(50) NOT NULL COMMENT '외부 서비스 키 (synology, hometax, bank_kb 등)',
  `service_name` varchar(100) NOT NULL COMMENT '외부 서비스 표시 이름',
  `external_login_id` varchar(100) DEFAULT NULL COMMENT '외부 서비스 로그인 ID(아이디/이메일)',
  `external_password` text DEFAULT NULL COMMENT '외부 서비스 비밀번호 (암호화 저장)',
  `external_identifier` varchar(255) DEFAULT NULL COMMENT '외부 서비스 고유 식별자 (id / DN 등)',
  `access_token` text DEFAULT NULL COMMENT 'Access Token (암호화 저장)',
  `refresh_token` text DEFAULT NULL COMMENT 'Refresh Token (암호화 저장)',
  `token_expires_at` datetime DEFAULT NULL COMMENT 'Access Token 만료 시각',
  `is_connected` tinyint(1) NOT NULL DEFAULT 0 COMMENT '외부 서비스 연결 여부',
  `last_connected_at` datetime DEFAULT NULL COMMENT '마지막 연결 성공 시각',
  `last_error_message` varchar(255) DEFAULT NULL COMMENT '마지막 오류 메시지',
  `created_at` datetime DEFAULT current_timestamp() COMMENT '생성일시',
  `created_by` varchar(36) DEFAULT NULL COMMENT '생성자 ID',
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp() COMMENT '수정일시',
  `updated_by` varchar(36) DEFAULT NULL COMMENT '수정자 ID',
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `uk_user_service` (`user_id`,`service_key`) USING BTREE COMMENT '사용자 + 서비스 중복 방지',
  KEY `idx_user_id` (`user_id`) USING BTREE,
  KEY `idx_service_key` (`service_key`) USING BTREE,
  CONSTRAINT `fk_external_accounts_user` FOREIGN KEY (`user_id`) REFERENCES `auth_users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='사용자 외부 서비스 계정 연동 테이블 (Synology, 홈택스, 은행 등)';

-- Data for `user_external_accounts`
INSERT INTO `user_external_accounts` (`id`,`user_id`,`service_key`,`service_name`,`external_login_id`,`external_password`,`external_identifier`,`access_token`,`refresh_token`,`token_expires_at`,`is_connected`,`last_connected_at`,`last_error_message`,`created_at`,`created_by`,`updated_at`,`updated_by`) VALUES ('996ba6e3-16de-11f1-91d0-909f33c822b4','f113b666-ff40-4f93-a7e7-8cea4cdc9c28','synology','Synology Calendar','이정호','Ljh3603+',NULL,NULL,NULL,NULL,'1','2026-03-27 15:26:49',NULL,'2026-03-03 17:54:33','f113b666-ff40-4f93-a7e7-8cea4cdc9c28','2026-03-27 15:26:49','f113b666-ff40-4f93-a7e7-8cea4cdc9c28');

-- ----------------------------
-- Table structure for `user_positions`
-- ----------------------------
DROP TABLE IF EXISTS `user_positions`;
CREATE TABLE `user_positions` (
  `id` varchar(36) NOT NULL COMMENT '직위 고유 ID (UUID)',
  `code` int(11) NOT NULL AUTO_INCREMENT COMMENT '순번 (AUTO_INCREMENT)',
  `position_name` varchar(50) NOT NULL COMMENT '직위명 (예: 대표이사, 이사, 부장, 차장, 과장, 대리, 사원)',
  `level_rank` int(11) DEFAULT 0 COMMENT '직위 등급 (숫자가 작을수록 상위)',
  `description` varchar(255) DEFAULT NULL COMMENT '직책 설명',
  `is_active` tinyint(1) DEFAULT 1 COMMENT '활성 상태 (1=활성, 0=비활성)',
  `created_at` datetime DEFAULT current_timestamp() COMMENT '등록일시',
  `created_by` varchar(36) DEFAULT NULL COMMENT '등록자ID',
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp() COMMENT '수정일시',
  `updated_by` varchar(36) DEFAULT NULL COMMENT '수정자ID',
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `uk_code` (`code`) USING BTREE,
  KEY `idx_level_rank` (`level_rank`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='직위 / 직책 관리 테이블 (UUID + 순번 구조)';

-- Data for `user_positions`
INSERT INTO `user_positions` (`id`,`code`,`position_name`,`level_rank`,`description`,`is_active`,`created_at`,`created_by`,`updated_at`,`updated_by`) VALUES ('0f61f16c-8d53-4c0c-8ad1-9a8828df70ba','5','부장','5','부장은 부서 운영을 총괄하며 인력 관리, 업무 조정, \n의사결정 등을 수행하고, 조직 목표 달성을 위한 실무와 \n관리의 중추 역할을 맡습니다.','1','2025-12-03 15:36:51','f113b666-ff40-4f93-a7e7-8cea4cdc9c28','2025-12-10 18:51:01','f113b666-ff40-4f93-a7e7-8cea4cdc9c28');
INSERT INTO `user_positions` (`id`,`code`,`position_name`,`level_rank`,`description`,`is_active`,`created_at`,`created_by`,`updated_at`,`updated_by`) VALUES ('1d79d8de-8ca3-4113-a313-f10716a45934','2','전무','2','전무는 경영진의 핵심 구성원으로서 회사 주요 정책을 \n실행하고, 부문별 운영을 총괄하며 전략 목표 달성을 \n주도하는 역할을 수행합니다.','1','2025-12-03 15:36:16','f113b666-ff40-4f93-a7e7-8cea4cdc9c28','2025-12-10 18:50:09','f113b666-ff40-4f93-a7e7-8cea4cdc9c28');
INSERT INTO `user_positions` (`id`,`code`,`position_name`,`level_rank`,`description`,`is_active`,`created_at`,`created_by`,`updated_at`,`updated_by`) VALUES ('216d84f1-33aa-45f2-bb85-ba4e80ac42e5','6','과장','6','과장은 팀 내 주요 실무와 관리 업무를 담당하며 프로젝트 \n조율, 업무 분배, 성과 관리 등을 수행하는 중간관리자 \n역할을 맡습니다.','1','2025-12-03 15:36:56','f113b666-ff40-4f93-a7e7-8cea4cdc9c28','2025-12-10 18:51:24','f113b666-ff40-4f93-a7e7-8cea4cdc9c28');
INSERT INTO `user_positions` (`id`,`code`,`position_name`,`level_rank`,`description`,`is_active`,`created_at`,`created_by`,`updated_at`,`updated_by`) VALUES ('4ad51de9-49e3-4789-b05e-635013af830c','9','인턴','9','인턴은 실무 경험과 역량 향상을 위해 업무를 보조하며, \n조직의 다양한 업무 프로세스를 학습하고 \n실무 수행 능력을 키우는 직급입니다.','1','2025-12-10 18:57:45','f113b666-ff40-4f93-a7e7-8cea4cdc9c28','2025-12-10 18:57:49','f113b666-ff40-4f93-a7e7-8cea4cdc9c28');
INSERT INTO `user_positions` (`id`,`code`,`position_name`,`level_rank`,`description`,`is_active`,`created_at`,`created_by`,`updated_at`,`updated_by`) VALUES ('4e7f14d6-1b4c-462e-83f8-07034103fbc3','3','상무','3','상무는 주요 사업 부문의 운영을 책임지고, 조직 관리와 \n실무 조정 업무를 수행하며 회사 성장과 성과 창출을 \n위한 핵심 관리 역할을 맡습니다.','1','2025-12-03 15:36:34','f113b666-ff40-4f93-a7e7-8cea4cdc9c28','2025-12-10 18:50:25','f113b666-ff40-4f93-a7e7-8cea4cdc9c28');
INSERT INTO `user_positions` (`id`,`code`,`position_name`,`level_rank`,`description`,`is_active`,`created_at`,`created_by`,`updated_at`,`updated_by`) VALUES ('6a728e10-ceec-4075-b912-b56a555aad31','1','대표','1','대표는 회사의 최고 의사결정권자로서 조직 운영 전반을 \n총괄하며, 경영 전략 수립과 주요 사업 추진을 \n책임지는 위치입니다.','1','2025-12-03 15:06:49','f113b666-ff40-4f93-a7e7-8cea4cdc9c28','2025-12-10 19:42:22','f113b666-ff40-4f93-a7e7-8cea4cdc9c28');
INSERT INTO `user_positions` (`id`,`code`,`position_name`,`level_rank`,`description`,`is_active`,`created_at`,`created_by`,`updated_at`,`updated_by`) VALUES ('78d20c4b-c28d-4a40-a0eb-abb451fba75b','8','사원','8','사원은 조직의 기본 업무를 수행하며 실무 중심의 \n역할을 맡고, 부서 목표 달성을 위한 다양한 \n업무 지원과 실행을 담당하는 직급입니다.	','1','2025-12-10 18:57:30','f113b666-ff40-4f93-a7e7-8cea4cdc9c28','2025-12-10 18:57:30',NULL);
INSERT INTO `user_positions` (`id`,`code`,`position_name`,`level_rank`,`description`,`is_active`,`created_at`,`created_by`,`updated_at`,`updated_by`) VALUES ('a40ba3b7-8d93-40a9-a4ec-c62e2ce759f9','4','이사','4','이사는 특정 부서 또는 사업 영역을 책임지고 조직 운영을 \n관리하며, 경영진과 협력하여 전략 실행과 목표 달성에 \n기여하는 역할을 담당합니다.','1','2025-12-03 15:36:40','f113b666-ff40-4f93-a7e7-8cea4cdc9c28','2025-12-10 18:50:43','f113b666-ff40-4f93-a7e7-8cea4cdc9c28');
INSERT INTO `user_positions` (`id`,`code`,`position_name`,`level_rank`,`description`,`is_active`,`created_at`,`created_by`,`updated_at`,`updated_by`) VALUES ('f2d8ee9b-c359-4d44-a8f4-b00caf9a23cc','7','대리','7','대리는 실무의 핵심적인 역할을 수행하며 업무 실행과 \n팀 지원을 담당하고, 향후 관리자 역할을 준비하는 \n책임 있는 직급입니다.','1','2025-12-03 15:37:02','f113b666-ff40-4f93-a7e7-8cea4cdc9c28','2025-12-10 18:51:39','f113b666-ff40-4f93-a7e7-8cea4cdc9c28');

-- ----------------------------
-- Table structure for `user_profiles`
-- ----------------------------
DROP TABLE IF EXISTS `user_profiles`;
CREATE TABLE `user_profiles` (
  `id` varchar(36) NOT NULL COMMENT '프로필 고유 ID (UUID)',
  `code` int(11) NOT NULL,
  `user_id` varchar(36) NOT NULL COMMENT '사용자 ID (auth_users.id FK)',
  `employee_name` varchar(50) NOT NULL COMMENT '직원 이름',
  `phone` varchar(20) DEFAULT NULL COMMENT '연락처',
  `address` varchar(255) DEFAULT NULL COMMENT '주소',
  `address_detail` varchar(255) DEFAULT NULL COMMENT '상세주소',
  `department_id` varchar(36) DEFAULT NULL COMMENT '부서 ID (user_departments.id FK)',
  `position_id` varchar(36) DEFAULT NULL COMMENT '직위 ID (user_positions.id FK)',
  `doc_hire_date` date DEFAULT NULL COMMENT '문서상 입사일',
  `real_hire_date` date DEFAULT NULL COMMENT '실제 입사일',
  `doc_retire_date` date DEFAULT NULL COMMENT '문서상 퇴사일',
  `real_retire_date` date DEFAULT NULL COMMENT '실제 퇴사일',
  `rrn` varchar(255) DEFAULT NULL COMMENT '주민등록번호 암호화 저장값',
  `rrn_image` varchar(255) DEFAULT NULL COMMENT '신분증 파일 경로(공개 금지)',
  `emergency_phone` varchar(20) DEFAULT NULL COMMENT '비상연락처',
  `profile_image` varchar(255) DEFAULT NULL COMMENT '프로필 사진 경로',
  `certificate_name` varchar(50) DEFAULT NULL COMMENT '대표 자격증 이름',
  `certificate_file` varchar(255) DEFAULT NULL COMMENT '대표 자격증 파일 경로',
  `note` varchar(255) DEFAULT NULL COMMENT '비고',
  `memo` text DEFAULT NULL COMMENT '관리자 메모',
  `created_at` datetime DEFAULT current_timestamp() COMMENT '생성일시',
  `created_by` varchar(36) DEFAULT NULL COMMENT '생성자ID',
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp() COMMENT '수정일시',
  `updated_by` varchar(36) DEFAULT NULL COMMENT '수정자ID',
  PRIMARY KEY (`id`) USING BTREE,
  KEY `idx_user_id` (`user_id`) USING BTREE,
  KEY `idx_department_id` (`department_id`) USING BTREE,
  KEY `idx_position_id` (`position_id`) USING BTREE,
  KEY `idx_code` (`code`),
  CONSTRAINT `fk_user_profiles_department` FOREIGN KEY (`department_id`) REFERENCES `user_departments` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_user_profiles_position` FOREIGN KEY (`position_id`) REFERENCES `user_positions` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_user_profiles_user` FOREIGN KEY (`user_id`) REFERENCES `auth_users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='직원 인사 / 프로필 관리 테이블 (UUID + 순번 구조, 부서/직위 FK 연동)';

-- Data for `user_profiles`
INSERT INTO `user_profiles` (`id`,`code`,`user_id`,`employee_name`,`phone`,`address`,`address_detail`,`department_id`,`position_id`,`doc_hire_date`,`real_hire_date`,`doc_retire_date`,`real_retire_date`,`rrn`,`rrn_image`,`emergency_phone`,`profile_image`,`certificate_name`,`certificate_file`,`note`,`memo`,`created_at`,`created_by`,`updated_at`,`updated_by`) VALUES ('3d9d4728-2459-4b1a-9ca9-7e62d6d1e7e7','4','47b0c9ed-cd89-479b-bb2e-60784437f6c4','이정호4',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-03-24 07:59:50','f113b666-ff40-4f93-a7e7-8cea4cdc9c28','2026-03-25 14:02:24',NULL);
INSERT INTO `user_profiles` (`id`,`code`,`user_id`,`employee_name`,`phone`,`address`,`address_detail`,`department_id`,`position_id`,`doc_hire_date`,`real_hire_date`,`doc_retire_date`,`real_retire_date`,`rrn`,`rrn_image`,`emergency_phone`,`profile_image`,`certificate_name`,`certificate_file`,`note`,`memo`,`created_at`,`created_by`,`updated_at`,`updated_by`) VALUES ('6e8fb7ef-ea70-4d37-9aed-74f33b355127','2','2e7c4808-9325-47eb-918f-472d5b626180','이정호2',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2025-12-08 11:56:08','2e7c4808-9325-47eb-918f-472d5b626180','2026-03-27 13:27:36','f113b666-ff40-4f93-a7e7-8cea4cdc9c28');
INSERT INTO `user_profiles` (`id`,`code`,`user_id`,`employee_name`,`phone`,`address`,`address_detail`,`department_id`,`position_id`,`doc_hire_date`,`real_hire_date`,`doc_retire_date`,`real_retire_date`,`rrn`,`rrn_image`,`emergency_phone`,`profile_image`,`certificate_name`,`certificate_file`,`note`,`memo`,`created_at`,`created_by`,`updated_at`,`updated_by`) VALUES ('ce50c61c-8b08-4f58-b8bc-e11f1dbafb84','1','f113b666-ff40-4f93-a7e7-8cea4cdc9c28','이정호','01079423603','경기 광주시 광주역5로 18 광주역자연앤자이','108동 1202호','4db22ded-3881-45b3-bc98-b0ccffd13683','6a728e10-ceec-4075-b912-b56a555aad31','2013-07-19','2013-07-01',NULL,NULL,'e3veFQ09d5bse9dNMopMqw==','private://id_doc/f_69c0e9e1ecd077.30672540.png','01028162308','public://profile/f_69c0e9e1da5127.60014295.jpg','국가기술자격증','private://certificate/f_69c0e9e20b44b9.28220573.pdf','안녕하세요','저는 이정호 입니다. ㅇㅇ\r\n잘부탁 드립니다.','2025-11-26 17:53:26','f113b666-ff40-4f93-a7e7-8cea4cdc9c28','2026-03-27 13:27:36','f113b666-ff40-4f93-a7e7-8cea4cdc9c28');
INSERT INTO `user_profiles` (`id`,`code`,`user_id`,`employee_name`,`phone`,`address`,`address_detail`,`department_id`,`position_id`,`doc_hire_date`,`real_hire_date`,`doc_retire_date`,`real_retire_date`,`rrn`,`rrn_image`,`emergency_phone`,`profile_image`,`certificate_name`,`certificate_file`,`note`,`memo`,`created_at`,`created_by`,`updated_at`,`updated_by`) VALUES ('ecb74d72-8cb1-4aef-a84b-6e5d40caf6e5','3','7332d024-b430-4d0c-a6aa-3d17dc6b67a2','이정호3',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-03-23 13:22:48','7332d024-b430-4d0c-a6aa-3d17dc6b67a2','2026-03-27 13:27:36',NULL);

SET FOREIGN_KEY_CHECKS = 1;
