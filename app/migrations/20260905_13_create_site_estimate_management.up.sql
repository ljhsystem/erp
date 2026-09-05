CREATE TABLE `site_estimates` (
  `id` char(36) NOT NULL COMMENT '견적 고유번호',
  `estimate_number` varchar(40) NOT NULL COMMENT '업무 기준 견적번호',
  `estimate_date` date NOT NULL COMMENT '견적 기준일자',
  `construction_name` varchar(200) NOT NULL COMMENT '견적 대상 공사명',
  `requester_name` varchar(150) DEFAULT NULL COMMENT '견적 의뢰업체명 Snapshot',
  `requester_person_name` varchar(80) DEFAULT NULL COMMENT '견적 의뢰 담당자명 Snapshot',
  `site_address` varchar(300) DEFAULT NULL COMMENT '견적 현장 주소',
  `owner_employee_id` varchar(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL COMMENT '사내 견적 담당 직원 고유번호',
  `submission_due_date` date DEFAULT NULL COMMENT '견적 제출기한',
  `estimate_status_code` varchar(20) NOT NULL DEFAULT 'WRITING' COMMENT '견적 진행상태(WRITING,SUBMITTED,NEGOTIATING,WON,LOST,HOLD)',
  `folder_path` varchar(500) DEFAULT NULL COMMENT '견적 관련 파일 폴더 경로',
  `note` text DEFAULT NULL COMMENT '견적 공통 비고',
  `created_at` datetime NOT NULL COMMENT '생성일시', `created_by` varchar(100) NOT NULL COMMENT '생성 Actor',
  `updated_at` datetime NOT NULL COMMENT '수정일시', `updated_by` varchar(100) NOT NULL COMMENT '수정 Actor',
  `deleted_at` datetime DEFAULT NULL COMMENT '삭제일시', `deleted_by` varchar(100) DEFAULT NULL COMMENT '삭제 Actor',
  PRIMARY KEY (`id`), UNIQUE KEY `uk_site_estimates_number` (`estimate_number`),
  KEY `idx_site_estimates_date_status` (`estimate_date`,`estimate_status_code`),
  CONSTRAINT `fk_site_estimates_owner` FOREIGN KEY (`owner_employee_id`) REFERENCES `user_employees` (`id`),
  CONSTRAINT `chk_site_estimates_status` CHECK (`estimate_status_code` IN ('WRITING','SUBMITTED','NEGOTIATING','WON','LOST','HOLD'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='현장별 견적案件 원본';

CREATE TABLE `site_estimate_packages` (
  `id` char(36) NOT NULL COMMENT '견적 묶음 고유번호', `estimate_id` char(36) NOT NULL COMMENT '견적 고유번호',
  `package_name` varchar(200) NOT NULL COMMENT '한 현장 안의 견적명', `work_scope` text DEFAULT NULL COMMENT '견적 공사범위',
  `sort_no` int NOT NULL DEFAULT 1 COMMENT '정렬순서', `package_status_code` varchar(20) NOT NULL DEFAULT 'WRITING' COMMENT '견적 묶음 상태(WRITING,SUBMITTED,NEGOTIATING,FINAL,WON,LOST,HOLD)',
  `created_at` datetime NOT NULL COMMENT '생성일시', `created_by` varchar(100) NOT NULL COMMENT '생성 Actor', `updated_at` datetime NOT NULL COMMENT '수정일시', `updated_by` varchar(100) NOT NULL COMMENT '수정 Actor',
  PRIMARY KEY (`id`), KEY `idx_site_estimate_packages_parent` (`estimate_id`,`sort_no`),
  CONSTRAINT `fk_site_estimate_packages_estimate` FOREIGN KEY (`estimate_id`) REFERENCES `site_estimates` (`id`) ON DELETE CASCADE,
  CONSTRAINT `chk_site_estimate_packages_status` CHECK (`package_status_code` IN ('WRITING','SUBMITTED','NEGOTIATING','FINAL','WON','LOST','HOLD'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='현장 견적 내부의 복수 견적 묶음';

CREATE TABLE `site_estimate_versions` (
  `id` char(36) NOT NULL COMMENT '견적 차수 고유번호', `estimate_package_id` char(36) NOT NULL COMMENT '견적 묶음 고유번호',
  `version_no` int NOT NULL COMMENT '견적 차수', `version_type_code` varchar(20) NOT NULL DEFAULT 'DRAFT' COMMENT '차수 유형(DRAFT,CHANGE,SUBMISSION,NEGOTIATION,FINAL)',
  `version_name` varchar(100) NOT NULL COMMENT '차수 표시명', `change_reason` varchar(500) DEFAULT NULL COMMENT '이전 차수 대비 변경사유',
  `overhead_rate` decimal(9,4) NOT NULL DEFAULT 0 COMMENT '일반관리비 요율', `risk_rate` decimal(9,4) NOT NULL DEFAULT 0 COMMENT '위험예비비 요율', `profit_rate` decimal(9,4) NOT NULL DEFAULT 0 COMMENT '목표이윤 요율',
  `rounding_unit` int NOT NULL DEFAULT 1 COMMENT '제출금액 반올림 단위', `adjustment_amount` decimal(18,2) NOT NULL DEFAULT 0 COMMENT '영업·협상 조정금액',
  `is_locked` tinyint(1) NOT NULL DEFAULT 0 COMMENT '확정 차수 수정잠금 여부', `confirmed_at` datetime DEFAULT NULL COMMENT '최종견적 확정일시', `confirmed_by` varchar(100) DEFAULT NULL COMMENT '최종견적 확정 Actor',
  `created_at` datetime NOT NULL COMMENT '생성일시', `created_by` varchar(100) NOT NULL COMMENT '생성 Actor', `updated_at` datetime NOT NULL COMMENT '수정일시', `updated_by` varchar(100) NOT NULL COMMENT '수정 Actor',
  PRIMARY KEY (`id`), UNIQUE KEY `uk_site_estimate_versions_no` (`estimate_package_id`,`version_no`),
  CONSTRAINT `fk_site_estimate_versions_package` FOREIGN KEY (`estimate_package_id`) REFERENCES `site_estimate_packages` (`id`) ON DELETE CASCADE,
  CONSTRAINT `chk_site_estimate_versions_type` CHECK (`version_type_code` IN ('DRAFT','CHANGE','SUBMISSION','NEGOTIATION','FINAL'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='최초·변경·제출·네고·최종 견적 차수 Snapshot';

CREATE TABLE `site_estimate_items` (
  `id` char(36) NOT NULL COMMENT '견적 아이템 고유번호', `estimate_version_id` char(36) NOT NULL COMMENT '견적 차수 고유번호', `sort_no` int NOT NULL COMMENT '정렬순서',
  `work_category_name` varchar(100) DEFAULT NULL COMMENT '공종 또는 구역명', `item_name` varchar(200) NOT NULL COMMENT '견적 품명', `specification` varchar(200) DEFAULT NULL COMMENT '견적 규격', `unit_name` varchar(40) DEFAULT NULL COMMENT '견적 단위 Snapshot',
  `quantity` decimal(18,4) NOT NULL DEFAULT 0 COMMENT '견적 수량', `execution_unit_price` decimal(18,2) NOT NULL DEFAULT 0 COMMENT '실행 원가 단가', `submission_unit_price` decimal(18,2) NOT NULL DEFAULT 0 COMMENT '제출 견적 단가',
  `calculation_basis` varchar(500) DEFAULT NULL COMMENT '수량·단가 산출근거', `note` varchar(500) DEFAULT NULL COMMENT '아이템 비고',
  `created_at` datetime NOT NULL COMMENT '생성일시', `created_by` varchar(100) NOT NULL COMMENT '생성 Actor', `updated_at` datetime NOT NULL COMMENT '수정일시', `updated_by` varchar(100) NOT NULL COMMENT '수정 Actor',
  PRIMARY KEY (`id`), KEY `idx_site_estimate_items_version` (`estimate_version_id`,`sort_no`),
  CONSTRAINT `fk_site_estimate_items_version` FOREIGN KEY (`estimate_version_id`) REFERENCES `site_estimate_versions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='차수별 실행·제출 견적 아이템';

CREATE TABLE `site_estimate_item_costs` (
  `id` char(36) NOT NULL COMMENT '실행원가 구성 고유번호', `estimate_item_id` char(36) NOT NULL COMMENT '견적 아이템 고유번호', `cost_type_code` varchar(20) NOT NULL COMMENT '원가유형(MATERIAL,LABOR,SUBCONTRACT,EQUIPMENT,TRANSPORT,EXPENSE,OTHER)',
  `cost_name` varchar(150) NOT NULL COMMENT '원가 구성명', `quantity` decimal(18,4) NOT NULL DEFAULT 0 COMMENT '원가 수량', `unit_price` decimal(18,2) NOT NULL DEFAULT 0 COMMENT '원가 단가', `calculation_basis` varchar(500) DEFAULT NULL COMMENT '원가 산출근거', `sort_no` int NOT NULL DEFAULT 1 COMMENT '정렬순서',
  `created_at` datetime NOT NULL COMMENT '생성일시', `created_by` varchar(100) NOT NULL COMMENT '생성 Actor', `updated_at` datetime NOT NULL COMMENT '수정일시', `updated_by` varchar(100) NOT NULL COMMENT '수정 Actor',
  PRIMARY KEY (`id`), KEY `idx_site_estimate_item_costs_item` (`estimate_item_id`,`sort_no`),
  CONSTRAINT `fk_site_estimate_item_costs_item` FOREIGN KEY (`estimate_item_id`) REFERENCES `site_estimate_items` (`id`) ON DELETE CASCADE,
  CONSTRAINT `chk_site_estimate_item_costs_type` CHECK (`cost_type_code` IN ('MATERIAL','LABOR','SUBCONTRACT','EQUIPMENT','TRANSPORT','EXPENSE','OTHER'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='견적 아이템별 실행원가 산출 구성';
