-- Phase1 evidence tables finalize (COMMENT / INDEX / FK)
-- Policy:
-- - No CREATE TABLE
-- - No DROP TABLE
-- - ALTER TABLE only
-- - Idempotent via information_schema checks

SET @schema_name := DATABASE();

DROP PROCEDURE IF EXISTS `sp_finalize_phase1_evidence`;
DELIMITER $$
CREATE PROCEDURE `sp_finalize_phase1_evidence`()
BEGIN
    DECLARE v_cnt INT DEFAULT 0;

    -- =========================================================
    -- 1) ledger_evidence_number_sequences
    -- =========================================================
    ALTER TABLE `ledger_evidence_number_sequences`
        MODIFY COLUMN `scope_code` VARCHAR(40) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '채번 스코프 코드(EVIDENCE_GLOBAL)',
        MODIFY COLUMN `last_evidence_sort_no` BIGINT UNSIGNED NOT NULL COMMENT '마지막 발급 증빙 공통 순번',
        MODIFY COLUMN `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '수정일시',
        MODIFY COLUMN `updated_by` VARCHAR(100) COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '수정자',
        COMMENT='증빙 공통 순번 현재값 관리 테이블';

    -- =========================================================
    -- 2) ledger_evidence_number_histories
    -- =========================================================
    ALTER TABLE `ledger_evidence_number_histories`
        MODIFY COLUMN `id` VARCHAR(36) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '고유 ID(UUID)',
        MODIFY COLUMN `scope_code` VARCHAR(40) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '채번 스코프 코드(EVIDENCE_GLOBAL)',
        MODIFY COLUMN `issued_evidence_sort_no` BIGINT UNSIGNED NOT NULL COMMENT '발급된 증빙 공통 순번',
        MODIFY COLUMN `issued_table` VARCHAR(100) COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '발급 대상 테이블명',
        MODIFY COLUMN `issued_row_id` VARCHAR(36) COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '발급 대상 레코드 ID',
        MODIFY COLUMN `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '생성일시',
        MODIFY COLUMN `created_by` VARCHAR(100) COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '생성자',
        MODIFY COLUMN `note` VARCHAR(500) COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '비고',
        COMMENT='증빙 공통 순번 발급 이력 테이블';

    SELECT COUNT(*) INTO v_cnt
      FROM information_schema.statistics
     WHERE table_schema = @schema_name AND table_name = 'ledger_evidence_number_histories' AND index_name = 'uk_evnh_issued_evidence_sort_no';
    IF v_cnt = 0 THEN
        ALTER TABLE `ledger_evidence_number_histories` ADD UNIQUE KEY `uk_evnh_issued_evidence_sort_no` (`issued_evidence_sort_no`);
    END IF;

    SELECT COUNT(*) INTO v_cnt
      FROM information_schema.statistics
     WHERE table_schema = @schema_name AND table_name = 'ledger_evidence_number_histories' AND index_name = 'idx_evnh_scope_code';
    IF v_cnt = 0 THEN
        ALTER TABLE `ledger_evidence_number_histories` ADD INDEX `idx_evnh_scope_code` (`scope_code`);
    END IF;

    SELECT COUNT(*) INTO v_cnt
      FROM information_schema.statistics
     WHERE table_schema = @schema_name AND table_name = 'ledger_evidence_number_histories' AND index_name = 'idx_evnh_issued_table_row';
    IF v_cnt = 0 THEN
        ALTER TABLE `ledger_evidence_number_histories` ADD INDEX `idx_evnh_issued_table_row` (`issued_table`, `issued_row_id`);
    END IF;

    SELECT COUNT(*) INTO v_cnt
      FROM information_schema.statistics
     WHERE table_schema = @schema_name AND table_name = 'ledger_evidence_number_histories' AND index_name = 'idx_evnh_created_at';
    IF v_cnt = 0 THEN
        ALTER TABLE `ledger_evidence_number_histories` ADD INDEX `idx_evnh_created_at` (`created_at`);
    END IF;

    -- =========================================================
    -- 3) ledger_evidence_bank
    -- =========================================================
    ALTER TABLE `ledger_evidence_bank`
        MODIFY COLUMN `evidence_status` VARCHAR(30) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '증빙 상태(ACTIVE,DELETED,INVALID)',
        COMMENT='은행 입출금 증빙 본문 테이블';

    SELECT COUNT(*) INTO v_cnt
      FROM information_schema.statistics
     WHERE table_schema = @schema_name AND table_name = 'ledger_evidence_bank' AND index_name = 'uk_evb_evidence_sort_no';
    IF v_cnt = 0 THEN
        ALTER TABLE `ledger_evidence_bank` ADD UNIQUE KEY `uk_evb_evidence_sort_no` (`evidence_sort_no`);
    END IF;

    SELECT COUNT(*) INTO v_cnt
      FROM information_schema.statistics
     WHERE table_schema = @schema_name AND table_name = 'ledger_evidence_bank' AND index_name = 'idx_evb_sort_no';
    IF v_cnt = 0 THEN
        ALTER TABLE `ledger_evidence_bank` ADD INDEX `idx_evb_sort_no` (`sort_no`);
    END IF;

    SELECT COUNT(*) INTO v_cnt
      FROM information_schema.statistics
     WHERE table_schema = @schema_name AND table_name = 'ledger_evidence_bank' AND index_name = 'idx_evb_source_date';
    IF v_cnt = 0 THEN
        ALTER TABLE `ledger_evidence_bank` ADD INDEX `idx_evb_source_date` (`source_type`, `evidence_date`);
    END IF;

    SELECT COUNT(*) INTO v_cnt
      FROM information_schema.statistics
     WHERE table_schema = @schema_name AND table_name = 'ledger_evidence_bank' AND index_name = 'idx_evb_external_key';
    IF v_cnt = 0 THEN
        ALTER TABLE `ledger_evidence_bank` ADD INDEX `idx_evb_external_key` (`external_key`);
    END IF;

    SELECT COUNT(*) INTO v_cnt
      FROM information_schema.statistics
     WHERE table_schema = @schema_name AND table_name = 'ledger_evidence_bank' AND index_name = 'idx_evb_bank_date';
    IF v_cnt = 0 THEN
        ALTER TABLE `ledger_evidence_bank` ADD INDEX `idx_evb_bank_date` (`bank_account_id`, `transaction_date`);
    END IF;

    SELECT COUNT(*) INTO v_cnt
      FROM information_schema.statistics
     WHERE table_schema = @schema_name AND table_name = 'ledger_evidence_bank' AND index_name = 'idx_evb_client_id';
    IF v_cnt = 0 THEN
        ALTER TABLE `ledger_evidence_bank` ADD INDEX `idx_evb_client_id` (`client_id`);
    END IF;

    SELECT COUNT(*) INTO v_cnt
      FROM information_schema.statistics
     WHERE table_schema = @schema_name AND table_name = 'ledger_evidence_bank' AND index_name = 'idx_evb_project_id';
    IF v_cnt = 0 THEN
        ALTER TABLE `ledger_evidence_bank` ADD INDEX `idx_evb_project_id` (`project_id`);
    END IF;

    SELECT COUNT(*) INTO v_cnt
      FROM information_schema.statistics
     WHERE table_schema = @schema_name AND table_name = 'ledger_evidence_bank' AND index_name = 'idx_evb_deleted_at';
    IF v_cnt = 0 THEN
        ALTER TABLE `ledger_evidence_bank` ADD INDEX `idx_evb_deleted_at` (`deleted_at`);
    END IF;

    SELECT COUNT(*) INTO v_cnt
      FROM information_schema.table_constraints
     WHERE table_schema = @schema_name AND table_name = 'ledger_evidence_bank' AND constraint_name = 'fk_evb_client' AND constraint_type = 'FOREIGN KEY';
    IF v_cnt = 0 THEN
        ALTER TABLE `ledger_evidence_bank` ADD CONSTRAINT `fk_evb_client` FOREIGN KEY (`client_id`) REFERENCES `system_clients` (`id`);
    END IF;

    SELECT COUNT(*) INTO v_cnt
      FROM information_schema.table_constraints
     WHERE table_schema = @schema_name AND table_name = 'ledger_evidence_bank' AND constraint_name = 'fk_evb_project' AND constraint_type = 'FOREIGN KEY';
    IF v_cnt = 0 THEN
        ALTER TABLE `ledger_evidence_bank` ADD CONSTRAINT `fk_evb_project` FOREIGN KEY (`project_id`) REFERENCES `system_projects` (`id`);
    END IF;

    SELECT COUNT(*) INTO v_cnt
      FROM information_schema.table_constraints
     WHERE table_schema = @schema_name AND table_name = 'ledger_evidence_bank' AND constraint_name = 'fk_evb_bank_account' AND constraint_type = 'FOREIGN KEY';
    IF v_cnt = 0 THEN
        ALTER TABLE `ledger_evidence_bank` ADD CONSTRAINT `fk_evb_bank_account` FOREIGN KEY (`bank_account_id`) REFERENCES `system_bank_accounts` (`id`);
    END IF;

    -- =========================================================
    -- 4) ledger_evidence_tax_invoice
    -- =========================================================
    ALTER TABLE `ledger_evidence_tax_invoice`
        MODIFY COLUMN `evidence_status` VARCHAR(30) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '증빙 상태(ACTIVE,DELETED,INVALID)',
        COMMENT='세금계산서 증빙 헤더 테이블';

    SELECT COUNT(*) INTO v_cnt FROM information_schema.statistics
     WHERE table_schema=@schema_name AND table_name='ledger_evidence_tax_invoice' AND index_name='uk_evti_evidence_sort_no';
    IF v_cnt = 0 THEN
        ALTER TABLE `ledger_evidence_tax_invoice` ADD UNIQUE KEY `uk_evti_evidence_sort_no` (`evidence_sort_no`);
    END IF;

    SELECT COUNT(*) INTO v_cnt FROM information_schema.statistics
     WHERE table_schema=@schema_name AND table_name='ledger_evidence_tax_invoice' AND index_name='idx_evti_sort_no';
    IF v_cnt = 0 THEN
        ALTER TABLE `ledger_evidence_tax_invoice` ADD INDEX `idx_evti_sort_no` (`sort_no`);
    END IF;

    SELECT COUNT(*) INTO v_cnt FROM information_schema.statistics
     WHERE table_schema=@schema_name AND table_name='ledger_evidence_tax_invoice' AND index_name='idx_evti_source_date';
    IF v_cnt = 0 THEN
        ALTER TABLE `ledger_evidence_tax_invoice` ADD INDEX `idx_evti_source_date` (`source_type`, `evidence_date`);
    END IF;

    SELECT COUNT(*) INTO v_cnt FROM information_schema.statistics
     WHERE table_schema=@schema_name AND table_name='ledger_evidence_tax_invoice' AND index_name='idx_evti_external_key';
    IF v_cnt = 0 THEN
        ALTER TABLE `ledger_evidence_tax_invoice` ADD INDEX `idx_evti_external_key` (`external_key`);
    END IF;

    SELECT COUNT(*) INTO v_cnt FROM information_schema.statistics
     WHERE table_schema=@schema_name AND table_name='ledger_evidence_tax_invoice' AND index_name='idx_evti_supplier_bn';
    IF v_cnt = 0 THEN
        ALTER TABLE `ledger_evidence_tax_invoice` ADD INDEX `idx_evti_supplier_bn` (`supplier_business_number`);
    END IF;

    SELECT COUNT(*) INTO v_cnt FROM information_schema.statistics
     WHERE table_schema=@schema_name AND table_name='ledger_evidence_tax_invoice' AND index_name='idx_evti_customer_bn';
    IF v_cnt = 0 THEN
        ALTER TABLE `ledger_evidence_tax_invoice` ADD INDEX `idx_evti_customer_bn` (`customer_business_number`);
    END IF;

    SELECT COUNT(*) INTO v_cnt FROM information_schema.statistics
     WHERE table_schema=@schema_name AND table_name='ledger_evidence_tax_invoice' AND index_name='idx_evti_client_id';
    IF v_cnt = 0 THEN
        ALTER TABLE `ledger_evidence_tax_invoice` ADD INDEX `idx_evti_client_id` (`client_id`);
    END IF;

    SELECT COUNT(*) INTO v_cnt FROM information_schema.statistics
     WHERE table_schema=@schema_name AND table_name='ledger_evidence_tax_invoice' AND index_name='idx_evti_project_id';
    IF v_cnt = 0 THEN
        ALTER TABLE `ledger_evidence_tax_invoice` ADD INDEX `idx_evti_project_id` (`project_id`);
    END IF;

    SELECT COUNT(*) INTO v_cnt FROM information_schema.statistics
     WHERE table_schema=@schema_name AND table_name='ledger_evidence_tax_invoice' AND index_name='idx_evti_deleted_at';
    IF v_cnt = 0 THEN
        ALTER TABLE `ledger_evidence_tax_invoice` ADD INDEX `idx_evti_deleted_at` (`deleted_at`);
    END IF;

    SELECT COUNT(*) INTO v_cnt FROM information_schema.table_constraints
     WHERE table_schema=@schema_name AND table_name='ledger_evidence_tax_invoice' AND constraint_name='fk_evti_client' AND constraint_type='FOREIGN KEY';
    IF v_cnt = 0 THEN
        ALTER TABLE `ledger_evidence_tax_invoice` ADD CONSTRAINT `fk_evti_client` FOREIGN KEY (`client_id`) REFERENCES `system_clients` (`id`);
    END IF;

    SELECT COUNT(*) INTO v_cnt FROM information_schema.table_constraints
     WHERE table_schema=@schema_name AND table_name='ledger_evidence_tax_invoice' AND constraint_name='fk_evti_project' AND constraint_type='FOREIGN KEY';
    IF v_cnt = 0 THEN
        ALTER TABLE `ledger_evidence_tax_invoice` ADD CONSTRAINT `fk_evti_project` FOREIGN KEY (`project_id`) REFERENCES `system_projects` (`id`);
    END IF;

    -- =========================================================
    -- 5) ledger_evidence_tax_invoice_items
    -- =========================================================
    ALTER TABLE `ledger_evidence_tax_invoice_items`
        COMMENT='세금계산서 품목 라인 테이블';

    SELECT COUNT(*) INTO v_cnt FROM information_schema.statistics
     WHERE table_schema=@schema_name AND table_name='ledger_evidence_tax_invoice_items' AND index_name='idx_evtii_tax_invoice_id';
    IF v_cnt = 0 THEN
        ALTER TABLE `ledger_evidence_tax_invoice_items` ADD INDEX `idx_evtii_tax_invoice_id` (`tax_invoice_id`);
    END IF;

    SELECT COUNT(*) INTO v_cnt FROM information_schema.statistics
     WHERE table_schema=@schema_name AND table_name='ledger_evidence_tax_invoice_items' AND index_name='idx_evtii_sort_no';
    IF v_cnt = 0 THEN
        ALTER TABLE `ledger_evidence_tax_invoice_items` ADD INDEX `idx_evtii_sort_no` (`sort_no`);
    END IF;

    SELECT COUNT(*) INTO v_cnt FROM information_schema.statistics
     WHERE table_schema=@schema_name AND table_name='ledger_evidence_tax_invoice_items' AND index_name='idx_evtii_deleted_at';
    IF v_cnt = 0 THEN
        ALTER TABLE `ledger_evidence_tax_invoice_items` ADD INDEX `idx_evtii_deleted_at` (`deleted_at`);
    END IF;

    SELECT COUNT(*) INTO v_cnt FROM information_schema.table_constraints
     WHERE table_schema=@schema_name AND table_name='ledger_evidence_tax_invoice_items' AND constraint_name='fk_evtii_tax_invoice' AND constraint_type='FOREIGN KEY';
    IF v_cnt = 0 THEN
        ALTER TABLE `ledger_evidence_tax_invoice_items` ADD CONSTRAINT `fk_evtii_tax_invoice` FOREIGN KEY (`tax_invoice_id`) REFERENCES `ledger_evidence_tax_invoice` (`id`);
    END IF;

    -- =========================================================
    -- 6) ledger_evidence_cash_receipt
    -- =========================================================
    ALTER TABLE `ledger_evidence_cash_receipt`
        MODIFY COLUMN `evidence_status` VARCHAR(30) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '증빙 상태(ACTIVE,DELETED,INVALID)',
        COMMENT='현금영수증 증빙 테이블';

    SELECT COUNT(*) INTO v_cnt FROM information_schema.statistics
     WHERE table_schema=@schema_name AND table_name='ledger_evidence_cash_receipt' AND index_name='uk_evcr_evidence_sort_no';
    IF v_cnt = 0 THEN
        ALTER TABLE `ledger_evidence_cash_receipt` ADD UNIQUE KEY `uk_evcr_evidence_sort_no` (`evidence_sort_no`);
    END IF;

    SELECT COUNT(*) INTO v_cnt FROM information_schema.statistics
     WHERE table_schema=@schema_name AND table_name='ledger_evidence_cash_receipt' AND index_name='idx_evcr_sort_no';
    IF v_cnt = 0 THEN
        ALTER TABLE `ledger_evidence_cash_receipt` ADD INDEX `idx_evcr_sort_no` (`sort_no`);
    END IF;

    SELECT COUNT(*) INTO v_cnt FROM information_schema.statistics
     WHERE table_schema=@schema_name AND table_name='ledger_evidence_cash_receipt' AND index_name='idx_evcr_source_date';
    IF v_cnt = 0 THEN
        ALTER TABLE `ledger_evidence_cash_receipt` ADD INDEX `idx_evcr_source_date` (`source_type`, `evidence_date`);
    END IF;

    SELECT COUNT(*) INTO v_cnt FROM information_schema.statistics
     WHERE table_schema=@schema_name AND table_name='ledger_evidence_cash_receipt' AND index_name='idx_evcr_external_key';
    IF v_cnt = 0 THEN
        ALTER TABLE `ledger_evidence_cash_receipt` ADD INDEX `idx_evcr_external_key` (`external_key`);
    END IF;

    SELECT COUNT(*) INTO v_cnt FROM information_schema.statistics
     WHERE table_schema=@schema_name AND table_name='ledger_evidence_cash_receipt' AND index_name='idx_evcr_merchant_bn';
    IF v_cnt = 0 THEN
        ALTER TABLE `ledger_evidence_cash_receipt` ADD INDEX `idx_evcr_merchant_bn` (`merchant_business_number`);
    END IF;

    SELECT COUNT(*) INTO v_cnt FROM information_schema.statistics
     WHERE table_schema=@schema_name AND table_name='ledger_evidence_cash_receipt' AND index_name='idx_evcr_client_id';
    IF v_cnt = 0 THEN
        ALTER TABLE `ledger_evidence_cash_receipt` ADD INDEX `idx_evcr_client_id` (`client_id`);
    END IF;

    SELECT COUNT(*) INTO v_cnt FROM information_schema.statistics
     WHERE table_schema=@schema_name AND table_name='ledger_evidence_cash_receipt' AND index_name='idx_evcr_project_id';
    IF v_cnt = 0 THEN
        ALTER TABLE `ledger_evidence_cash_receipt` ADD INDEX `idx_evcr_project_id` (`project_id`);
    END IF;

    SELECT COUNT(*) INTO v_cnt FROM information_schema.statistics
     WHERE table_schema=@schema_name AND table_name='ledger_evidence_cash_receipt' AND index_name='idx_evcr_deleted_at';
    IF v_cnt = 0 THEN
        ALTER TABLE `ledger_evidence_cash_receipt` ADD INDEX `idx_evcr_deleted_at` (`deleted_at`);
    END IF;

    SELECT COUNT(*) INTO v_cnt FROM information_schema.table_constraints
     WHERE table_schema=@schema_name AND table_name='ledger_evidence_cash_receipt' AND constraint_name='fk_evcr_client' AND constraint_type='FOREIGN KEY';
    IF v_cnt = 0 THEN
        ALTER TABLE `ledger_evidence_cash_receipt` ADD CONSTRAINT `fk_evcr_client` FOREIGN KEY (`client_id`) REFERENCES `system_clients` (`id`);
    END IF;

    SELECT COUNT(*) INTO v_cnt FROM information_schema.table_constraints
     WHERE table_schema=@schema_name AND table_name='ledger_evidence_cash_receipt' AND constraint_name='fk_evcr_project' AND constraint_type='FOREIGN KEY';
    IF v_cnt = 0 THEN
        ALTER TABLE `ledger_evidence_cash_receipt` ADD CONSTRAINT `fk_evcr_project` FOREIGN KEY (`project_id`) REFERENCES `system_projects` (`id`);
    END IF;

    -- =========================================================
    -- 7) ledger_evidence_card_purchase
    -- =========================================================
    ALTER TABLE `ledger_evidence_card_purchase`
        MODIFY COLUMN `evidence_status` VARCHAR(30) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '증빙 상태(ACTIVE,DELETED,INVALID)',
        COMMENT='카드매입 증빙 테이블';

    SELECT COUNT(*) INTO v_cnt FROM information_schema.statistics
     WHERE table_schema=@schema_name AND table_name='ledger_evidence_card_purchase' AND index_name='uk_evcp_evidence_sort_no';
    IF v_cnt = 0 THEN
        ALTER TABLE `ledger_evidence_card_purchase` ADD UNIQUE KEY `uk_evcp_evidence_sort_no` (`evidence_sort_no`);
    END IF;

    SELECT COUNT(*) INTO v_cnt FROM information_schema.statistics
     WHERE table_schema=@schema_name AND table_name='ledger_evidence_card_purchase' AND index_name='idx_evcp_sort_no';
    IF v_cnt = 0 THEN
        ALTER TABLE `ledger_evidence_card_purchase` ADD INDEX `idx_evcp_sort_no` (`sort_no`);
    END IF;

    SELECT COUNT(*) INTO v_cnt FROM information_schema.statistics
     WHERE table_schema=@schema_name AND table_name='ledger_evidence_card_purchase' AND index_name='idx_evcp_source_date';
    IF v_cnt = 0 THEN
        ALTER TABLE `ledger_evidence_card_purchase` ADD INDEX `idx_evcp_source_date` (`source_type`, `evidence_date`);
    END IF;

    SELECT COUNT(*) INTO v_cnt FROM information_schema.statistics
     WHERE table_schema=@schema_name AND table_name='ledger_evidence_card_purchase' AND index_name='idx_evcp_external_key';
    IF v_cnt = 0 THEN
        ALTER TABLE `ledger_evidence_card_purchase` ADD INDEX `idx_evcp_external_key` (`external_key`);
    END IF;

    SELECT COUNT(*) INTO v_cnt FROM information_schema.statistics
     WHERE table_schema=@schema_name AND table_name='ledger_evidence_card_purchase' AND index_name='idx_evcp_card_id';
    IF v_cnt = 0 THEN
        ALTER TABLE `ledger_evidence_card_purchase` ADD INDEX `idx_evcp_card_id` (`card_id`);
    END IF;

    SELECT COUNT(*) INTO v_cnt FROM information_schema.statistics
     WHERE table_schema=@schema_name AND table_name='ledger_evidence_card_purchase' AND index_name='idx_evcp_merchant_bn';
    IF v_cnt = 0 THEN
        ALTER TABLE `ledger_evidence_card_purchase` ADD INDEX `idx_evcp_merchant_bn` (`merchant_business_number`);
    END IF;

    SELECT COUNT(*) INTO v_cnt FROM information_schema.statistics
     WHERE table_schema=@schema_name AND table_name='ledger_evidence_card_purchase' AND index_name='idx_evcp_client_id';
    IF v_cnt = 0 THEN
        ALTER TABLE `ledger_evidence_card_purchase` ADD INDEX `idx_evcp_client_id` (`client_id`);
    END IF;

    SELECT COUNT(*) INTO v_cnt FROM information_schema.statistics
     WHERE table_schema=@schema_name AND table_name='ledger_evidence_card_purchase' AND index_name='idx_evcp_project_id';
    IF v_cnt = 0 THEN
        ALTER TABLE `ledger_evidence_card_purchase` ADD INDEX `idx_evcp_project_id` (`project_id`);
    END IF;

    SELECT COUNT(*) INTO v_cnt FROM information_schema.statistics
     WHERE table_schema=@schema_name AND table_name='ledger_evidence_card_purchase' AND index_name='idx_evcp_deleted_at';
    IF v_cnt = 0 THEN
        ALTER TABLE `ledger_evidence_card_purchase` ADD INDEX `idx_evcp_deleted_at` (`deleted_at`);
    END IF;

    SELECT COUNT(*) INTO v_cnt FROM information_schema.table_constraints
     WHERE table_schema=@schema_name AND table_name='ledger_evidence_card_purchase' AND constraint_name='fk_evcp_card' AND constraint_type='FOREIGN KEY';
    IF v_cnt = 0 THEN
        ALTER TABLE `ledger_evidence_card_purchase` ADD CONSTRAINT `fk_evcp_card` FOREIGN KEY (`card_id`) REFERENCES `system_cards` (`id`);
    END IF;

    SELECT COUNT(*) INTO v_cnt FROM information_schema.table_constraints
     WHERE table_schema=@schema_name AND table_name='ledger_evidence_card_purchase' AND constraint_name='fk_evcp_client' AND constraint_type='FOREIGN KEY';
    IF v_cnt = 0 THEN
        ALTER TABLE `ledger_evidence_card_purchase` ADD CONSTRAINT `fk_evcp_client` FOREIGN KEY (`client_id`) REFERENCES `system_clients` (`id`);
    END IF;

    SELECT COUNT(*) INTO v_cnt FROM information_schema.table_constraints
     WHERE table_schema=@schema_name AND table_name='ledger_evidence_card_purchase' AND constraint_name='fk_evcp_project' AND constraint_type='FOREIGN KEY';
    IF v_cnt = 0 THEN
        ALTER TABLE `ledger_evidence_card_purchase` ADD CONSTRAINT `fk_evcp_project` FOREIGN KEY (`project_id`) REFERENCES `system_projects` (`id`);
    END IF;

    -- =========================================================
    -- 8) ledger_evidence_links
    -- =========================================================
    ALTER TABLE `ledger_evidence_links`
        COMMENT='증빙-거래/전표 연결 링크 테이블';

    SELECT COUNT(*) INTO v_cnt FROM information_schema.statistics
     WHERE table_schema=@schema_name AND table_name='ledger_evidence_links' AND index_name='uk_evl_unique_link';
    IF v_cnt = 0 THEN
        ALTER TABLE `ledger_evidence_links` ADD UNIQUE KEY `uk_evl_unique_link` (`evidence_type`, `evidence_id`, `target_type`, `target_id`, `link_type`);
    END IF;

    SELECT COUNT(*) INTO v_cnt FROM information_schema.statistics
     WHERE table_schema=@schema_name AND table_name='ledger_evidence_links' AND index_name='idx_evl_evidence_ref';
    IF v_cnt = 0 THEN
        ALTER TABLE `ledger_evidence_links` ADD INDEX `idx_evl_evidence_ref` (`evidence_type`, `evidence_id`);
    END IF;

    SELECT COUNT(*) INTO v_cnt FROM information_schema.statistics
     WHERE table_schema=@schema_name AND table_name='ledger_evidence_links' AND index_name='idx_evl_target_ref';
    IF v_cnt = 0 THEN
        ALTER TABLE `ledger_evidence_links` ADD INDEX `idx_evl_target_ref` (`target_type`, `target_id`);
    END IF;

    SELECT COUNT(*) INTO v_cnt FROM information_schema.statistics
     WHERE table_schema=@schema_name AND table_name='ledger_evidence_links' AND index_name='idx_evl_link_type';
    IF v_cnt = 0 THEN
        ALTER TABLE `ledger_evidence_links` ADD INDEX `idx_evl_link_type` (`link_type`);
    END IF;

    SELECT COUNT(*) INTO v_cnt FROM information_schema.statistics
     WHERE table_schema=@schema_name AND table_name='ledger_evidence_links' AND index_name='idx_evl_deleted_at';
    IF v_cnt = 0 THEN
        ALTER TABLE `ledger_evidence_links` ADD INDEX `idx_evl_deleted_at` (`deleted_at`);
    END IF;

    -- =========================================================
    -- 9) ledger_evidence_processing
    -- =========================================================
    ALTER TABLE `ledger_evidence_processing`
        COMMENT='증빙 처리 상태 관리 테이블(단순화 버전)';

    SELECT COUNT(*) INTO v_cnt FROM information_schema.statistics
     WHERE table_schema=@schema_name AND table_name='ledger_evidence_processing' AND index_name='uk_evp_evidence_ref';
    IF v_cnt = 0 THEN
        ALTER TABLE `ledger_evidence_processing` ADD UNIQUE KEY `uk_evp_evidence_ref` (`evidence_type`, `evidence_id`);
    END IF;

    SELECT COUNT(*) INTO v_cnt FROM information_schema.statistics
     WHERE table_schema=@schema_name AND table_name='ledger_evidence_processing' AND index_name='idx_evp_processing_status';
    IF v_cnt = 0 THEN
        ALTER TABLE `ledger_evidence_processing` ADD INDEX `idx_evp_processing_status` (`processing_status`);
    END IF;

    SELECT COUNT(*) INTO v_cnt FROM information_schema.statistics
     WHERE table_schema=@schema_name AND table_name='ledger_evidence_processing' AND index_name='idx_evp_review_status';
    IF v_cnt = 0 THEN
        ALTER TABLE `ledger_evidence_processing` ADD INDEX `idx_evp_review_status` (`review_status`);
    END IF;

    SELECT COUNT(*) INTO v_cnt FROM information_schema.statistics
     WHERE table_schema=@schema_name AND table_name='ledger_evidence_processing' AND index_name='idx_evp_deleted_at';
    IF v_cnt = 0 THEN
        ALTER TABLE `ledger_evidence_processing` ADD INDEX `idx_evp_deleted_at` (`deleted_at`);
    END IF;

    -- =========================================================
    -- 10) ledger_evidence_processing_logs
    -- =========================================================
    ALTER TABLE `ledger_evidence_processing_logs`
        COMMENT='증빙 처리 이력 로그 테이블';

    SELECT COUNT(*) INTO v_cnt FROM information_schema.statistics
     WHERE table_schema=@schema_name AND table_name='ledger_evidence_processing_logs' AND index_name='idx_evpl_processing_id';
    IF v_cnt = 0 THEN
        ALTER TABLE `ledger_evidence_processing_logs` ADD INDEX `idx_evpl_processing_id` (`processing_id`);
    END IF;

    SELECT COUNT(*) INTO v_cnt FROM information_schema.statistics
     WHERE table_schema=@schema_name AND table_name='ledger_evidence_processing_logs' AND index_name='idx_evpl_action_type';
    IF v_cnt = 0 THEN
        ALTER TABLE `ledger_evidence_processing_logs` ADD INDEX `idx_evpl_action_type` (`action_type`);
    END IF;

    SELECT COUNT(*) INTO v_cnt FROM information_schema.statistics
     WHERE table_schema=@schema_name AND table_name='ledger_evidence_processing_logs' AND index_name='idx_evpl_created_at';
    IF v_cnt = 0 THEN
        ALTER TABLE `ledger_evidence_processing_logs` ADD INDEX `idx_evpl_created_at` (`created_at`);
    END IF;

    SELECT COUNT(*) INTO v_cnt FROM information_schema.table_constraints
     WHERE table_schema=@schema_name AND table_name='ledger_evidence_processing_logs' AND constraint_name='fk_evpl_processing' AND constraint_type='FOREIGN KEY';
    IF v_cnt = 0 THEN
        ALTER TABLE `ledger_evidence_processing_logs` ADD CONSTRAINT `fk_evpl_processing` FOREIGN KEY (`processing_id`) REFERENCES `ledger_evidence_processing` (`id`);
    END IF;

END$$
DELIMITER ;

CALL `sp_finalize_phase1_evidence`();
DROP PROCEDURE IF EXISTS `sp_finalize_phase1_evidence`;
