CREATE TABLE IF NOT EXISTS `ledger_evidence_number_sequences` (
    `scope_code` VARCHAR(40) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '채번 스코프 코드(EVIDENCE_GLOBAL)',
    `last_evidence_sort_no` BIGINT UNSIGNED NOT NULL COMMENT '마지막 발급 증빙 공통 순번',
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '수정일시',
    `updated_by` VARCHAR(100) COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '수정자',
    PRIMARY KEY (`scope_code`)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci
  COMMENT='증빙 공통 순번 현재값 관리 테이블';

CREATE TABLE IF NOT EXISTS `ledger_evidence_number_histories` (
    `id` VARCHAR(36) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '고유 ID(UUID)',
    `scope_code` VARCHAR(40) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '채번 스코프 코드(EVIDENCE_GLOBAL)',
    `issued_evidence_sort_no` BIGINT UNSIGNED NOT NULL COMMENT '발급된 증빙 공통 순번',
    `issued_table` VARCHAR(100) COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '발급 대상 테이블명',
    `issued_row_id` VARCHAR(36) COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '발급 대상 레코드 ID',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '생성일시',
    `created_by` VARCHAR(100) COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '생성자',
    `note` VARCHAR(500) COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '비고',
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_evnh_issued_evidence_sort_no` (`issued_evidence_sort_no`),
    INDEX `idx_evnh_scope_code` (`scope_code`),
    INDEX `idx_evnh_issued_table_row` (`issued_table`, `issued_row_id`),
    INDEX `idx_evnh_created_at` (`created_at`)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci
  COMMENT='증빙 공통 순번 발급 이력 테이블';

CREATE TABLE IF NOT EXISTS `ledger_evidence_bank` (
    `id` VARCHAR(36) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '고유 ID(UUID)',
    `sort_no` INT UNSIGNED NOT NULL COMMENT '테이블 내부 순번',
    `evidence_sort_no` BIGINT UNSIGNED NOT NULL COMMENT '전체 증빙 공통 순번',
    `source_type` VARCHAR(40) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '증빙유형 코드',
    `external_key` VARCHAR(120) COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '외부 원본 식별값(거래고유번호)',
    `evidence_date` DATE NOT NULL COMMENT '증빙일자',
    `client_id` VARCHAR(36) COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '거래처 ID',
    `project_id` VARCHAR(36) COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '프로젝트 ID',
    `raw_client_name` VARCHAR(255) COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '원본 거래처명',
    `evidence_status` VARCHAR(30) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '증빙 상태(ACTIVE,DELETED,INVALID)',
    `transaction_date` DATE NOT NULL COMMENT '거래일자',
    `transaction_time` TIME NULL DEFAULT NULL COMMENT '거래시간',
    `transaction_datetime` DATETIME NULL DEFAULT NULL COMMENT '거래일시',
    `bank_account_id` VARCHAR(36) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '은행계좌 ID',
    `transaction_type` VARCHAR(30) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '입출금 유형',
    `deposit_amount` DECIMAL(18,2) NULL DEFAULT NULL COMMENT '입금액',
    `withdraw_amount` DECIMAL(18,2) NULL DEFAULT NULL COMMENT '출금액',
    `total_amount` DECIMAL(18,2) NOT NULL COMMENT '합계금액(입금=deposit_amount, 출금=withdraw_amount)',
    `balance_amount` DECIMAL(18,2) NULL DEFAULT NULL COMMENT '거래 후 잔액',
    `balance_status` VARCHAR(20) COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '잔액 상태',
    `check_bill_amount` DECIMAL(18,2) NULL DEFAULT NULL COMMENT '수표어음금액',
    `currency_code` VARCHAR(10) COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '통화코드',
    `exchange_rate` DECIMAL(18,6) NULL DEFAULT NULL COMMENT '환율',
    `description` VARCHAR(500) COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '적요',
    `counterparty_name` VARCHAR(255) COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '상대방명',
    `counterparty_account_number` VARCHAR(100) COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '상대 계좌번호',
    `counterparty_bank_name` VARCHAR(100) COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '상대 은행명',
    `memo` TEXT COLLATE utf8mb4_unicode_ci NULL COMMENT '메모',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '생성일시',
    `updated_at` DATETIME NULL DEFAULT NULL COMMENT '수정일시',
    `deleted_at` DATETIME NULL DEFAULT NULL COMMENT '삭제일시',
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_evb_evidence_sort_no` (`evidence_sort_no`),
    INDEX `idx_evb_sort_no` (`sort_no`),
    INDEX `idx_evb_source_date` (`source_type`, `evidence_date`),
    INDEX `idx_evb_external_key` (`external_key`),
    INDEX `idx_evb_bank_date` (`bank_account_id`, `transaction_date`),
    INDEX `idx_evb_client_id` (`client_id`),
    INDEX `idx_evb_project_id` (`project_id`),
    INDEX `idx_evb_deleted_at` (`deleted_at`),
    CONSTRAINT `fk_evb_client` FOREIGN KEY (`client_id`) REFERENCES `system_clients` (`id`),
    CONSTRAINT `fk_evb_project` FOREIGN KEY (`project_id`) REFERENCES `system_projects` (`id`),
    CONSTRAINT `fk_evb_bank_account` FOREIGN KEY (`bank_account_id`) REFERENCES `system_bank_accounts` (`id`)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci
  COMMENT='은행 입출금 증빙 본문 테이블';

CREATE TABLE IF NOT EXISTS `ledger_evidence_tax_invoice` (
    `id` VARCHAR(36) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '고유 ID(UUID)',
    `sort_no` INT UNSIGNED NOT NULL COMMENT '테이블 내부 순번',
    `evidence_sort_no` BIGINT UNSIGNED NOT NULL COMMENT '전체 증빙 공통 순번',
    `source_type` VARCHAR(40) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '증빙유형 코드',
    `external_key` VARCHAR(120) COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '외부 원본 식별값(승인번호 등)',
    `evidence_date` DATE NOT NULL COMMENT '증빙일자',
    `client_id` VARCHAR(36) COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '거래처 ID',
    `project_id` VARCHAR(36) COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '프로젝트 ID',
    `raw_client_name` VARCHAR(255) COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '원본 거래처명',
    `evidence_status` VARCHAR(30) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '증빙 상태(ACTIVE,DELETED,INVALID)',
    `transaction_date` DATE NOT NULL COMMENT '거래일자',
    `issue_date` DATE NULL DEFAULT NULL COMMENT '발급일자',
    `transmit_date` DATE NULL DEFAULT NULL COMMENT '전송일자',
    `supplier_business_number` VARCHAR(20) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '공급자 사업자등록번호',
    `supplier_branch_number` VARCHAR(20) COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '공급자 종사업장번호',
    `supplier_company_name` VARCHAR(255) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '공급자 상호',
    `supplier_ceo_name` VARCHAR(120) COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '공급자 대표자명',
    `supplier_address` VARCHAR(500) COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '공급자 주소',
    `supplier_email` VARCHAR(255) COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '공급자 이메일',
    `customer_business_number` VARCHAR(20) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '공급받는자 사업자등록번호',
    `customer_branch_number` VARCHAR(20) COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '공급받는자 종사업장번호',
    `customer_company_name` VARCHAR(255) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '공급받는자 상호',
    `customer_ceo_name` VARCHAR(120) COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '공급받는자 대표자명',
    `customer_address` VARCHAR(500) COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '공급받는자 주소',
    `customer_email_1` VARCHAR(255) COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '공급받는자 이메일1',
    `customer_email_2` VARCHAR(255) COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '공급받는자 이메일2',
    `tax_invoice_category` VARCHAR(30) COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '전자세금계산서 분류',
    `tax_invoice_type` VARCHAR(30) COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '전자세금계산서 종류',
    `issue_type` VARCHAR(30) COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '발급유형',
    `receipt_claim_type` VARCHAR(30) COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '영수/청구 구분',
    `supply_amount` DECIMAL(18,2) NOT NULL COMMENT '공급가액',
    `vat_amount` DECIMAL(18,2) NOT NULL COMMENT '부가세액',
    `service_amount` DECIMAL(18,2) NULL DEFAULT NULL COMMENT '봉사료',
    `total_amount` DECIMAL(18,2) NOT NULL COMMENT '합계금액',
    `description` VARCHAR(500) COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '설명/비고',
    `memo` TEXT COLLATE utf8mb4_unicode_ci NULL COMMENT '메모',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '생성일시',
    `updated_at` DATETIME NULL DEFAULT NULL COMMENT '수정일시',
    `deleted_at` DATETIME NULL DEFAULT NULL COMMENT '삭제일시',
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_evti_evidence_sort_no` (`evidence_sort_no`),
    INDEX `idx_evti_sort_no` (`sort_no`),
    INDEX `idx_evti_source_date` (`source_type`, `evidence_date`),
    INDEX `idx_evti_external_key` (`external_key`),
    INDEX `idx_evti_supplier_bn` (`supplier_business_number`),
    INDEX `idx_evti_customer_bn` (`customer_business_number`),
    INDEX `idx_evti_client_id` (`client_id`),
    INDEX `idx_evti_project_id` (`project_id`),
    INDEX `idx_evti_deleted_at` (`deleted_at`),
    CONSTRAINT `fk_evti_client` FOREIGN KEY (`client_id`) REFERENCES `system_clients` (`id`),
    CONSTRAINT `fk_evti_project` FOREIGN KEY (`project_id`) REFERENCES `system_projects` (`id`)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci
  COMMENT='세금계산서 증빙 헤더 테이블';

CREATE TABLE IF NOT EXISTS `ledger_evidence_tax_invoice_items` (
    `id` VARCHAR(36) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '고유 ID(UUID)',
    `tax_invoice_id` VARCHAR(36) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '세금계산서 헤더 ID',
    `sort_no` INT UNSIGNED NOT NULL COMMENT '헤더 내부 라인 순번',
    `item_date` DATE NULL DEFAULT NULL COMMENT '품목일자',
    `item_name` VARCHAR(255) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '품목명',
    `item_spec` VARCHAR(255) COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '품목규격',
    `item_qty` DECIMAL(18,3) NULL DEFAULT NULL COMMENT '품목수량',
    `item_price` DECIMAL(18,2) NULL DEFAULT NULL COMMENT '품목단가',
    `item_supply_amount` DECIMAL(18,2) NOT NULL COMMENT '품목공급가액',
    `item_vat_amount` DECIMAL(18,2) NOT NULL COMMENT '품목세액',
    `item_note` VARCHAR(500) COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '품목비고',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '생성일시',
    `updated_at` DATETIME NULL DEFAULT NULL COMMENT '수정일시',
    `deleted_at` DATETIME NULL DEFAULT NULL COMMENT '삭제일시',
    PRIMARY KEY (`id`),
    INDEX `idx_evtii_tax_invoice_id` (`tax_invoice_id`),
    INDEX `idx_evtii_sort_no` (`sort_no`),
    INDEX `idx_evtii_deleted_at` (`deleted_at`),
    CONSTRAINT `fk_evtii_tax_invoice` FOREIGN KEY (`tax_invoice_id`) REFERENCES `ledger_evidence_tax_invoice` (`id`)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci
  COMMENT='세금계산서 품목 라인 테이블';

CREATE TABLE IF NOT EXISTS `ledger_evidence_cash_receipt` (
    `id` VARCHAR(36) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '고유 ID(UUID)',
    `sort_no` INT UNSIGNED NOT NULL COMMENT '테이블 내부 순번',
    `evidence_sort_no` BIGINT UNSIGNED NOT NULL COMMENT '전체 증빙 공통 순번',
    `source_type` VARCHAR(40) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '증빙유형 코드',
    `external_key` VARCHAR(120) COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '외부 원본 식별값(승인번호)',
    `evidence_date` DATE NOT NULL COMMENT '증빙일자',
    `client_id` VARCHAR(36) COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '거래처 ID',
    `project_id` VARCHAR(36) COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '프로젝트 ID',
    `raw_client_name` VARCHAR(255) COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '원본 거래처명',
    `evidence_status` VARCHAR(30) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '증빙 상태(ACTIVE,DELETED,INVALID)',
    `write_date` DATETIME NULL DEFAULT NULL COMMENT '발행/거래 일시',
    `issue_method` VARCHAR(30) COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '발급수단',
    `merchant_business_number` VARCHAR(20) COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '가맹점 사업자등록번호',
    `merchant_company_name` VARCHAR(255) COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '가맹점 상호',
    `merchant_business_type` VARCHAR(120) COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '가맹점 업태',
    `merchant_business_category` VARCHAR(120) COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '가맹점 업종',
    `supply_amount` DECIMAL(18,2) NOT NULL COMMENT '공급가액',
    `vat_amount` DECIMAL(18,2) NOT NULL COMMENT '부가세액',
    `service_amount` DECIMAL(18,2) NULL DEFAULT NULL COMMENT '봉사료',
    `total_amount` DECIMAL(18,2) NOT NULL COMMENT '합계금액',
    `memo` TEXT COLLATE utf8mb4_unicode_ci NULL COMMENT '메모',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '생성일시',
    `updated_at` DATETIME NULL DEFAULT NULL COMMENT '수정일시',
    `deleted_at` DATETIME NULL DEFAULT NULL COMMENT '삭제일시',
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_evcr_evidence_sort_no` (`evidence_sort_no`),
    INDEX `idx_evcr_sort_no` (`sort_no`),
    INDEX `idx_evcr_source_date` (`source_type`, `evidence_date`),
    INDEX `idx_evcr_external_key` (`external_key`),
    INDEX `idx_evcr_merchant_bn` (`merchant_business_number`),
    INDEX `idx_evcr_client_id` (`client_id`),
    INDEX `idx_evcr_project_id` (`project_id`),
    INDEX `idx_evcr_deleted_at` (`deleted_at`),
    CONSTRAINT `fk_evcr_client` FOREIGN KEY (`client_id`) REFERENCES `system_clients` (`id`),
    CONSTRAINT `fk_evcr_project` FOREIGN KEY (`project_id`) REFERENCES `system_projects` (`id`)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci
  COMMENT='현금영수증 증빙 테이블';

CREATE TABLE IF NOT EXISTS `ledger_evidence_card_purchase` (
    `id` VARCHAR(36) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '고유 ID(UUID)',
    `sort_no` INT UNSIGNED NOT NULL COMMENT '테이블 내부 순번',
    `evidence_sort_no` BIGINT UNSIGNED NOT NULL COMMENT '전체 증빙 공통 순번',
    `source_type` VARCHAR(40) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '증빙유형 코드',
    `external_key` VARCHAR(120) COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '외부 원본 식별값(승인번호)',
    `evidence_date` DATE NOT NULL COMMENT '증빙일자',
    `client_id` VARCHAR(36) COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '거래처 ID',
    `project_id` VARCHAR(36) COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '프로젝트 ID',
    `raw_client_name` VARCHAR(255) COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '원본 거래처명',
    `evidence_status` VARCHAR(30) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '증빙 상태(ACTIVE,DELETED,INVALID)',
    `card_id` VARCHAR(36) COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '카드 ID',
    `raw_card_name` VARCHAR(255) COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '원본 카드명',
    `raw_card_number` VARCHAR(120) COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '원본 카드번호',
    `approval_date` DATE NULL DEFAULT NULL COMMENT '승인일자',
    `billing_date` DATE NULL DEFAULT NULL COMMENT '청구일자',
    `merchant_business_number` VARCHAR(20) COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '가맹점 사업자등록번호',
    `merchant_company_name` VARCHAR(255) COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '가맹점 상호',
    `merchant_business_type` VARCHAR(120) COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '가맹점 업태',
    `merchant_business_category` VARCHAR(120) COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '가맹점 업종',
    `supply_amount` DECIMAL(18,2) NOT NULL COMMENT '공급가액',
    `vat_amount` DECIMAL(18,2) NOT NULL COMMENT '부가세액',
    `service_amount` DECIMAL(18,2) NULL DEFAULT NULL COMMENT '봉사료',
    `total_amount` DECIMAL(18,2) NOT NULL COMMENT '합계금액',
    `fee_amount` DECIMAL(18,2) NULL DEFAULT NULL COMMENT '수수료',
    `currency_code` VARCHAR(10) COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '통화코드',
    `exchange_rate` DECIMAL(18,6) NULL DEFAULT NULL COMMENT '환율',
    `foreign_amount` DECIMAL(18,2) NULL DEFAULT NULL COMMENT '외화금액',
    `local_amount` DECIMAL(18,2) NULL DEFAULT NULL COMMENT '원화금액',
    `installment_period` VARCHAR(20) COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '할부기간',
    `installment_sequence` VARCHAR(20) COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '할부회차',
    `payment_account_number` VARCHAR(100) COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '결제계좌번호',
    `payment_bank_name` VARCHAR(100) COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '결제계좌 은행명',
    `memo` TEXT COLLATE utf8mb4_unicode_ci NULL COMMENT '메모',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '생성일시',
    `updated_at` DATETIME NULL DEFAULT NULL COMMENT '수정일시',
    `deleted_at` DATETIME NULL DEFAULT NULL COMMENT '삭제일시',
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_evcp_evidence_sort_no` (`evidence_sort_no`),
    INDEX `idx_evcp_sort_no` (`sort_no`),
    INDEX `idx_evcp_source_date` (`source_type`, `evidence_date`),
    INDEX `idx_evcp_external_key` (`external_key`),
    INDEX `idx_evcp_card_id` (`card_id`),
    INDEX `idx_evcp_merchant_bn` (`merchant_business_number`),
    INDEX `idx_evcp_client_id` (`client_id`),
    INDEX `idx_evcp_project_id` (`project_id`),
    INDEX `idx_evcp_deleted_at` (`deleted_at`),
    CONSTRAINT `fk_evcp_card` FOREIGN KEY (`card_id`) REFERENCES `system_cards` (`id`),
    CONSTRAINT `fk_evcp_client` FOREIGN KEY (`client_id`) REFERENCES `system_clients` (`id`),
    CONSTRAINT `fk_evcp_project` FOREIGN KEY (`project_id`) REFERENCES `system_projects` (`id`)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci
  COMMENT='카드매입 증빙 테이블';

CREATE TABLE IF NOT EXISTS `ledger_evidence_links` (
    `id` VARCHAR(36) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '고유 ID(UUID)',
    `evidence_type` VARCHAR(40) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '증빙유형 코드',
    `evidence_id` VARCHAR(36) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '증빙 레코드 ID',
    `target_type` VARCHAR(20) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '연결 대상 유형(TRANSACTION,VOUCHER)',
    `target_id` VARCHAR(36) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '연결 대상 ID',
    `link_type` VARCHAR(30) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '연결유형(SOURCE,PAYMENT,SUPPORTING,GENERATED_FROM)',
    `amount` DECIMAL(18,2) NULL DEFAULT NULL COMMENT '연결금액',
    `memo` VARCHAR(500) COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '비고',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '생성일시',
    `updated_at` DATETIME NULL DEFAULT NULL COMMENT '수정일시',
    `deleted_at` DATETIME NULL DEFAULT NULL COMMENT '삭제일시',
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_evl_unique_link` (`evidence_type`, `evidence_id`, `target_type`, `target_id`, `link_type`),
    INDEX `idx_evl_evidence_ref` (`evidence_type`, `evidence_id`),
    INDEX `idx_evl_target_ref` (`target_type`, `target_id`),
    INDEX `idx_evl_link_type` (`link_type`),
    INDEX `idx_evl_deleted_at` (`deleted_at`)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci
  COMMENT='증빙-거래/전표 연결 링크 테이블';

CREATE TABLE IF NOT EXISTS `ledger_evidence_processing` (
    `id` VARCHAR(36) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '고유 ID(UUID)',
    `evidence_type` VARCHAR(40) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '증빙유형 코드',
    `evidence_id` VARCHAR(36) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '증빙 레코드 ID',
    `processing_status` VARCHAR(30) COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '처리 상태',
    `review_status` VARCHAR(30) COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '검토 상태',
    `last_error_message` TEXT COLLATE utf8mb4_unicode_ci NULL COMMENT '마지막 오류 메시지',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '생성일시',
    `updated_at` DATETIME NULL DEFAULT NULL COMMENT '수정일시',
    `deleted_at` DATETIME NULL DEFAULT NULL COMMENT '삭제일시',
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_evp_evidence_ref` (`evidence_type`, `evidence_id`),
    INDEX `idx_evp_processing_status` (`processing_status`),
    INDEX `idx_evp_review_status` (`review_status`),
    INDEX `idx_evp_deleted_at` (`deleted_at`)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci
  COMMENT='증빙 처리 상태 관리 테이블(단순화 버전)';

CREATE TABLE IF NOT EXISTS `ledger_evidence_processing_logs` (
    `id` VARCHAR(36) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '고유 ID(UUID)',
    `processing_id` VARCHAR(36) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '처리 상태 ID',
    `action_type` VARCHAR(40) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '작업유형',
    `before_status` VARCHAR(100) COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '변경 전 상태',
    `after_status` VARCHAR(100) COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '변경 후 상태',
    `message` VARCHAR(1000) COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '처리 메시지',
    `actor_type` VARCHAR(20) COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '작업 주체 유형',
    `actor_id` VARCHAR(100) COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '작업 주체 ID',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '생성일시',
    PRIMARY KEY (`id`),
    INDEX `idx_evpl_processing_id` (`processing_id`),
    INDEX `idx_evpl_action_type` (`action_type`),
    INDEX `idx_evpl_created_at` (`created_at`),
    CONSTRAINT `fk_evpl_processing` FOREIGN KEY (`processing_id`) REFERENCES `ledger_evidence_processing` (`id`)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci
  COMMENT='증빙 처리 이력 로그 테이블';
