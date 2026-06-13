-- 실행 금지(검토/이관용): 2차 증빙 데이터 백필 SQL
-- 대상: CARD_SALES, EMPLOYEE_EXPENSE, PAYROLL, DAILY_WORKER, BUSINESS_INCOME, CASH_SALES
-- 전제: 20260601_06_create_ledger_evidence_phase2_tables.up.sql 적용 완료

START TRANSACTION;

SET @migration_batch_id := DATE_FORMAT(NOW(), 'PHASE2_%Y%m%d_%H%i%s');

-- 1) 시퀀스 초기값 보장
INSERT INTO `ledger_evidence_number_sequences` (`scope_code`, `last_evidence_sort_no`, `updated_at`, `updated_by`)
SELECT 'EVIDENCE_GLOBAL', 0, NOW(), 'SYSTEM:MIGRATION'
WHERE NOT EXISTS (
    SELECT 1
    FROM `ledger_evidence_number_sequences`
    WHERE `scope_code` = 'EVIDENCE_GLOBAL'
);

-- 2) CARD_SALES -> ledger_evidence_card_sales
INSERT INTO `ledger_evidence_card_sales` (
    `id`, `sort_no`, `evidence_sort_no`, `source_type`, `external_key`, `evidence_date`,
    `client_id`, `project_id`, `raw_client_name`, `evidence_status`,
    `card_id`, `raw_card_name`, `raw_card_number`, `approval_date`,
    `merchant_business_number`, `merchant_company_name`, `merchant_business_type`, `merchant_business_category`,
    `supply_amount`, `vat_amount`, `service_amount`, `total_amount`, `fee_amount`,
    `currency_code`, `exchange_rate`, `memo`,
    `created_at`, `updated_at`, `deleted_at`
)
SELECT
    e.`id`,
    COALESCE(NULLIF(e.`create_sort_no`, 0), ROW_NUMBER() OVER (ORDER BY e.`evidence_date`, e.`id`)) AS `sort_no`,
    0 AS `evidence_sort_no`,
    'CARD_SALES_SHOPPING' AS `source_type`,
    NULLIF(TRIM(e.`source_key`), '') AS `external_key`,
    e.`evidence_date`,
    e.`client_id`,
    e.`project_id`,
    NULLIF(TRIM(e.`client_name`), '') AS `raw_client_name`,
    CASE
        WHEN e.`deleted_at` IS NOT NULL OR e.`evidence_status` = 'DELETED' THEN 'DELETED'
        WHEN e.`evidence_status` = 'ERROR' THEN 'INVALID'
        ELSE 'ACTIVE'
    END AS `evidence_status`,
    e.`card_id`,
    NULLIF(TRIM(JSON_UNQUOTE(JSON_EXTRACT(p.`mapped_payload_json`, '$.card_name'))), '') AS `raw_card_name`,
    NULLIF(TRIM(JSON_UNQUOTE(JSON_EXTRACT(p.`mapped_payload_json`, '$.card_number'))), '') AS `raw_card_number`,
    STR_TO_DATE(JSON_UNQUOTE(JSON_EXTRACT(p.`mapped_payload_json`, '$.approval_date')), '%Y-%m-%d') AS `approval_date`,
    NULLIF(TRIM(JSON_UNQUOTE(JSON_EXTRACT(p.`mapped_payload_json`, '$.merchant_business_number'))), '') AS `merchant_business_number`,
    NULLIF(TRIM(JSON_UNQUOTE(JSON_EXTRACT(p.`mapped_payload_json`, '$.merchant_company_name'))), '') AS `merchant_company_name`,
    NULLIF(TRIM(JSON_UNQUOTE(JSON_EXTRACT(p.`mapped_payload_json`, '$.merchant_business_type'))), '') AS `merchant_business_type`,
    NULLIF(TRIM(JSON_UNQUOTE(JSON_EXTRACT(p.`mapped_payload_json`, '$.merchant_business_category'))), '') AS `merchant_business_category`,
    COALESCE(e.`supply_amount`, 0),
    COALESCE(e.`vat_amount`, 0),
    CAST(NULLIF(TRIM(JSON_UNQUOTE(JSON_EXTRACT(p.`mapped_payload_json`, '$.service_amount'))), '') AS DECIMAL(18,2)) AS `service_amount`,
    COALESCE(e.`total_amount`, 0),
    CAST(NULLIF(TRIM(JSON_UNQUOTE(JSON_EXTRACT(p.`mapped_payload_json`, '$.fee_amount'))), '') AS DECIMAL(18,2)) AS `fee_amount`,
    NULLIF(TRIM(JSON_UNQUOTE(JSON_EXTRACT(p.`mapped_payload_json`, '$.currency'))), '') AS `currency_code`,
    CAST(NULLIF(TRIM(JSON_UNQUOTE(JSON_EXTRACT(p.`mapped_payload_json`, '$.exchange_rate'))), '') AS DECIMAL(18,6)) AS `exchange_rate`,
    NULLIF(TRIM(JSON_UNQUOTE(JSON_EXTRACT(p.`mapped_payload_json`, '$.memo'))), '') AS `memo`,
    e.`created_at`,
    e.`updated_at`,
    e.`deleted_at`
FROM `ledger_data_evidences` e
JOIN `ledger_evidence_payloads` p
  ON p.`evidence_type` = e.`source_type` COLLATE utf8mb4_unicode_ci
 AND p.`evidence_id` = e.`id` COLLATE utf8mb4_unicode_ci
WHERE e.`source_type` IN ('CARD_SALES', 'CARD_SALES_SHOPPING')
  AND e.`deleted_at` IS NULL
  AND NOT EXISTS (
      SELECT 1
      FROM `ledger_evidence_card_sales` t
      WHERE t.`id` = e.`id`
  );

-- 3) EMPLOYEE_EXPENSE -> ledger_evidence_employee_expense
INSERT INTO `ledger_evidence_employee_expense` (
    `id`, `sort_no`, `evidence_sort_no`, `source_type`, `external_key`, `evidence_date`,
    `client_id`, `project_id`, `raw_client_name`, `evidence_status`,
    `employee_name`, `expense_type`, `expense_date`,
    `supply_amount`, `vat_amount`, `service_amount`, `total_amount`,
    `payment_method`, `memo`,
    `created_at`, `updated_at`, `deleted_at`
)
SELECT
    e.`id`,
    COALESCE(NULLIF(e.`create_sort_no`, 0), ROW_NUMBER() OVER (ORDER BY e.`evidence_date`, e.`id`)) AS `sort_no`,
    0 AS `evidence_sort_no`,
    'EMPLOYEE_EXPENSE' AS `source_type`,
    NULLIF(TRIM(e.`source_key`), '') AS `external_key`,
    e.`evidence_date`,
    e.`client_id`,
    e.`project_id`,
    NULLIF(TRIM(e.`client_name`), '') AS `raw_client_name`,
    CASE
        WHEN e.`deleted_at` IS NOT NULL OR e.`evidence_status` = 'DELETED' THEN 'DELETED'
        WHEN e.`evidence_status` = 'ERROR' THEN 'INVALID'
        ELSE 'ACTIVE'
    END AS `evidence_status`,
    NULLIF(TRIM(JSON_UNQUOTE(JSON_EXTRACT(p.`mapped_payload_json`, '$.employee_name'))), '') AS `employee_name`,
    NULLIF(TRIM(JSON_UNQUOTE(JSON_EXTRACT(p.`mapped_payload_json`, '$.expense_type'))), '') AS `expense_type`,
    STR_TO_DATE(JSON_UNQUOTE(JSON_EXTRACT(p.`mapped_payload_json`, '$.expense_date')), '%Y-%m-%d') AS `expense_date`,
    COALESCE(e.`supply_amount`, 0),
    COALESCE(e.`vat_amount`, 0),
    CAST(NULLIF(TRIM(JSON_UNQUOTE(JSON_EXTRACT(p.`mapped_payload_json`, '$.service_amount'))), '') AS DECIMAL(18,2)) AS `service_amount`,
    COALESCE(e.`total_amount`, 0),
    NULLIF(TRIM(JSON_UNQUOTE(JSON_EXTRACT(p.`mapped_payload_json`, '$.payment_method'))), '') AS `payment_method`,
    NULLIF(TRIM(JSON_UNQUOTE(JSON_EXTRACT(p.`mapped_payload_json`, '$.memo'))), '') AS `memo`,
    e.`created_at`,
    e.`updated_at`,
    e.`deleted_at`
FROM `ledger_data_evidences` e
JOIN `ledger_evidence_payloads` p
  ON p.`evidence_type` = e.`source_type` COLLATE utf8mb4_unicode_ci
 AND p.`evidence_id` = e.`id` COLLATE utf8mb4_unicode_ci
WHERE e.`source_type` = 'EMPLOYEE_EXPENSE'
  AND e.`deleted_at` IS NULL
  AND NOT EXISTS (
      SELECT 1
      FROM `ledger_evidence_employee_expense` t
      WHERE t.`id` = e.`id`
  );

-- 4) PAYROLL(+WITHHOLDING) -> ledger_evidence_payroll
INSERT INTO `ledger_evidence_payroll` (
    `id`, `sort_no`, `evidence_sort_no`, `source_type`, `external_key`, `evidence_date`,
    `client_id`, `project_id`, `raw_client_name`, `evidence_status`,
    `payroll_month`, `pay_date`, `employee_count`,
    `supply_amount`, `vat_amount`, `service_amount`, `total_amount`,
    `memo`, `created_at`, `updated_at`, `deleted_at`
)
SELECT
    e.`id`,
    COALESCE(NULLIF(e.`create_sort_no`, 0), ROW_NUMBER() OVER (ORDER BY e.`evidence_date`, e.`id`)) AS `sort_no`,
    0 AS `evidence_sort_no`,
    CASE WHEN e.`source_type` = 'PAYROLL_WITHHOLDING' THEN 'PAYROLL_WITHHOLDING' ELSE 'PAYROLL' END AS `source_type`,
    NULLIF(TRIM(e.`source_key`), '') AS `external_key`,
    e.`evidence_date`,
    e.`client_id`,
    e.`project_id`,
    NULLIF(TRIM(e.`client_name`), '') AS `raw_client_name`,
    CASE
        WHEN e.`deleted_at` IS NOT NULL OR e.`evidence_status` = 'DELETED' THEN 'DELETED'
        WHEN e.`evidence_status` = 'ERROR' THEN 'INVALID'
        ELSE 'ACTIVE'
    END AS `evidence_status`,
    NULLIF(TRIM(JSON_UNQUOTE(JSON_EXTRACT(p.`mapped_payload_json`, '$.payroll_month'))), '') AS `payroll_month`,
    STR_TO_DATE(JSON_UNQUOTE(JSON_EXTRACT(p.`mapped_payload_json`, '$.pay_date')), '%Y-%m-%d') AS `pay_date`,
    CAST(NULLIF(TRIM(JSON_UNQUOTE(JSON_EXTRACT(p.`mapped_payload_json`, '$.employee_count'))), '') AS UNSIGNED) AS `employee_count`,
    COALESCE(e.`supply_amount`, 0),
    COALESCE(e.`vat_amount`, 0),
    CAST(NULLIF(TRIM(JSON_UNQUOTE(JSON_EXTRACT(p.`mapped_payload_json`, '$.service_amount'))), '') AS DECIMAL(18,2)) AS `service_amount`,
    COALESCE(e.`total_amount`, 0),
    NULLIF(TRIM(JSON_UNQUOTE(JSON_EXTRACT(p.`mapped_payload_json`, '$.memo'))), '') AS `memo`,
    e.`created_at`,
    e.`updated_at`,
    e.`deleted_at`
FROM `ledger_data_evidences` e
JOIN `ledger_evidence_payloads` p
  ON p.`evidence_type` = e.`source_type` COLLATE utf8mb4_unicode_ci
 AND p.`evidence_id` = e.`id` COLLATE utf8mb4_unicode_ci
WHERE e.`source_type` IN ('PAYROLL', 'PAYROLL_WITHHOLDING')
  AND e.`deleted_at` IS NULL
  AND NOT EXISTS (
      SELECT 1
      FROM `ledger_evidence_payroll` t
      WHERE t.`id` = e.`id`
  );

-- 5) DAILY_WORKER -> ledger_evidence_daily_worker
INSERT INTO `ledger_evidence_daily_worker` (
    `id`, `sort_no`, `evidence_sort_no`, `source_type`, `external_key`, `evidence_date`,
    `client_id`, `project_id`, `raw_client_name`, `evidence_status`,
    `work_date`, `worker_count`,
    `supply_amount`, `vat_amount`, `service_amount`, `total_amount`,
    `memo`, `created_at`, `updated_at`, `deleted_at`
)
SELECT
    e.`id`,
    COALESCE(NULLIF(e.`create_sort_no`, 0), ROW_NUMBER() OVER (ORDER BY e.`evidence_date`, e.`id`)) AS `sort_no`,
    0 AS `evidence_sort_no`,
    'DAILY_WORKER' AS `source_type`,
    NULLIF(TRIM(e.`source_key`), '') AS `external_key`,
    e.`evidence_date`,
    e.`client_id`,
    e.`project_id`,
    NULLIF(TRIM(e.`client_name`), '') AS `raw_client_name`,
    CASE
        WHEN e.`deleted_at` IS NOT NULL OR e.`evidence_status` = 'DELETED' THEN 'DELETED'
        WHEN e.`evidence_status` = 'ERROR' THEN 'INVALID'
        ELSE 'ACTIVE'
    END AS `evidence_status`,
    STR_TO_DATE(JSON_UNQUOTE(JSON_EXTRACT(p.`mapped_payload_json`, '$.work_date')), '%Y-%m-%d') AS `work_date`,
    CAST(NULLIF(TRIM(JSON_UNQUOTE(JSON_EXTRACT(p.`mapped_payload_json`, '$.worker_count'))), '') AS UNSIGNED) AS `worker_count`,
    COALESCE(e.`supply_amount`, 0),
    COALESCE(e.`vat_amount`, 0),
    CAST(NULLIF(TRIM(JSON_UNQUOTE(JSON_EXTRACT(p.`mapped_payload_json`, '$.service_amount'))), '') AS DECIMAL(18,2)) AS `service_amount`,
    COALESCE(e.`total_amount`, 0),
    NULLIF(TRIM(JSON_UNQUOTE(JSON_EXTRACT(p.`mapped_payload_json`, '$.memo'))), '') AS `memo`,
    e.`created_at`,
    e.`updated_at`,
    e.`deleted_at`
FROM `ledger_data_evidences` e
JOIN `ledger_evidence_payloads` p
  ON p.`evidence_type` = e.`source_type` COLLATE utf8mb4_unicode_ci
 AND p.`evidence_id` = e.`id` COLLATE utf8mb4_unicode_ci
WHERE e.`source_type` = 'DAILY_WORKER'
  AND e.`deleted_at` IS NULL
  AND NOT EXISTS (
      SELECT 1
      FROM `ledger_evidence_daily_worker` t
      WHERE t.`id` = e.`id`
  );

-- 6) BUSINESS_INCOME -> ledger_evidence_business_income
INSERT INTO `ledger_evidence_business_income` (
    `id`, `sort_no`, `evidence_sort_no`, `source_type`, `external_key`, `evidence_date`,
    `client_id`, `project_id`, `raw_client_name`, `evidence_status`,
    `income_date`, `provider_name`, `provider_reg_no`,
    `supply_amount`, `vat_amount`, `service_amount`, `total_amount`,
    `memo`, `created_at`, `updated_at`, `deleted_at`
)
SELECT
    e.`id`,
    COALESCE(NULLIF(e.`create_sort_no`, 0), ROW_NUMBER() OVER (ORDER BY e.`evidence_date`, e.`id`)) AS `sort_no`,
    0 AS `evidence_sort_no`,
    'BUSINESS_INCOME' AS `source_type`,
    NULLIF(TRIM(e.`source_key`), '') AS `external_key`,
    e.`evidence_date`,
    e.`client_id`,
    e.`project_id`,
    NULLIF(TRIM(e.`client_name`), '') AS `raw_client_name`,
    CASE
        WHEN e.`deleted_at` IS NOT NULL OR e.`evidence_status` = 'DELETED' THEN 'DELETED'
        WHEN e.`evidence_status` = 'ERROR' THEN 'INVALID'
        ELSE 'ACTIVE'
    END AS `evidence_status`,
    STR_TO_DATE(JSON_UNQUOTE(JSON_EXTRACT(p.`mapped_payload_json`, '$.income_date')), '%Y-%m-%d') AS `income_date`,
    NULLIF(TRIM(JSON_UNQUOTE(JSON_EXTRACT(p.`mapped_payload_json`, '$.provider_name'))), '') AS `provider_name`,
    NULLIF(TRIM(JSON_UNQUOTE(JSON_EXTRACT(p.`mapped_payload_json`, '$.provider_reg_no'))), '') AS `provider_reg_no`,
    COALESCE(e.`supply_amount`, 0),
    COALESCE(e.`vat_amount`, 0),
    CAST(NULLIF(TRIM(JSON_UNQUOTE(JSON_EXTRACT(p.`mapped_payload_json`, '$.service_amount'))), '') AS DECIMAL(18,2)) AS `service_amount`,
    COALESCE(e.`total_amount`, 0),
    NULLIF(TRIM(JSON_UNQUOTE(JSON_EXTRACT(p.`mapped_payload_json`, '$.memo'))), '') AS `memo`,
    e.`created_at`,
    e.`updated_at`,
    e.`deleted_at`
FROM `ledger_data_evidences` e
JOIN `ledger_evidence_payloads` p
  ON p.`evidence_type` = e.`source_type` COLLATE utf8mb4_unicode_ci
 AND p.`evidence_id` = e.`id` COLLATE utf8mb4_unicode_ci
WHERE e.`source_type` = 'BUSINESS_INCOME'
  AND e.`deleted_at` IS NULL
  AND NOT EXISTS (
      SELECT 1
      FROM `ledger_evidence_business_income` t
      WHERE t.`id` = e.`id`
  );

-- 7) CASH_SALES -> ledger_evidence_cash_sales
INSERT INTO `ledger_evidence_cash_sales` (
    `id`, `sort_no`, `evidence_sort_no`, `source_type`, `external_key`, `evidence_date`,
    `client_id`, `project_id`, `raw_client_name`, `evidence_status`,
    `sales_date`, `sales_channel`, `order_count`,
    `supply_amount`, `vat_amount`, `service_amount`, `total_amount`,
    `memo`, `created_at`, `updated_at`, `deleted_at`
)
SELECT
    e.`id`,
    COALESCE(NULLIF(e.`create_sort_no`, 0), ROW_NUMBER() OVER (ORDER BY e.`evidence_date`, e.`id`)) AS `sort_no`,
    0 AS `evidence_sort_no`,
    'CASH_SALES' AS `source_type`,
    NULLIF(TRIM(e.`source_key`), '') AS `external_key`,
    e.`evidence_date`,
    e.`client_id`,
    e.`project_id`,
    NULLIF(TRIM(e.`client_name`), '') AS `raw_client_name`,
    CASE
        WHEN e.`deleted_at` IS NOT NULL OR e.`evidence_status` = 'DELETED' THEN 'DELETED'
        WHEN e.`evidence_status` = 'ERROR' THEN 'INVALID'
        ELSE 'ACTIVE'
    END AS `evidence_status`,
    STR_TO_DATE(JSON_UNQUOTE(JSON_EXTRACT(p.`mapped_payload_json`, '$.sales_date')), '%Y-%m-%d') AS `sales_date`,
    NULLIF(TRIM(JSON_UNQUOTE(JSON_EXTRACT(p.`mapped_payload_json`, '$.sales_channel'))), '') AS `sales_channel`,
    CAST(NULLIF(TRIM(JSON_UNQUOTE(JSON_EXTRACT(p.`mapped_payload_json`, '$.order_count'))), '') AS UNSIGNED) AS `order_count`,
    COALESCE(e.`supply_amount`, 0),
    COALESCE(e.`vat_amount`, 0),
    CAST(NULLIF(TRIM(JSON_UNQUOTE(JSON_EXTRACT(p.`mapped_payload_json`, '$.service_amount'))), '') AS DECIMAL(18,2)) AS `service_amount`,
    COALESCE(e.`total_amount`, 0),
    NULLIF(TRIM(JSON_UNQUOTE(JSON_EXTRACT(p.`mapped_payload_json`, '$.memo'))), '') AS `memo`,
    e.`created_at`,
    e.`updated_at`,
    e.`deleted_at`
FROM `ledger_data_evidences` e
JOIN `ledger_evidence_payloads` p
  ON p.`evidence_type` = e.`source_type` COLLATE utf8mb4_unicode_ci
 AND p.`evidence_id` = e.`id` COLLATE utf8mb4_unicode_ci
WHERE e.`source_type` = 'CASH_SALES'
  AND e.`deleted_at` IS NULL
  AND NOT EXISTS (
      SELECT 1
      FROM `ledger_evidence_cash_sales` t
      WHERE t.`id` = e.`id`
  );

-- 8) 공통 순번 발급
SET @next_no := (
    SELECT `last_evidence_sort_no`
    FROM `ledger_evidence_number_sequences`
    WHERE `scope_code` = 'EVIDENCE_GLOBAL'
    FOR UPDATE
);

UPDATE `ledger_evidence_card_sales`
SET `evidence_sort_no` = (@next_no := @next_no + 1)
WHERE `evidence_sort_no` = 0;

UPDATE `ledger_evidence_employee_expense`
SET `evidence_sort_no` = (@next_no := @next_no + 1)
WHERE `evidence_sort_no` = 0;

UPDATE `ledger_evidence_payroll`
SET `evidence_sort_no` = (@next_no := @next_no + 1)
WHERE `evidence_sort_no` = 0;

UPDATE `ledger_evidence_daily_worker`
SET `evidence_sort_no` = (@next_no := @next_no + 1)
WHERE `evidence_sort_no` = 0;

UPDATE `ledger_evidence_business_income`
SET `evidence_sort_no` = (@next_no := @next_no + 1)
WHERE `evidence_sort_no` = 0;

UPDATE `ledger_evidence_cash_sales`
SET `evidence_sort_no` = (@next_no := @next_no + 1)
WHERE `evidence_sort_no` = 0;

UPDATE `ledger_evidence_number_sequences`
SET `last_evidence_sort_no` = @next_no,
    `updated_at` = NOW(),
    `updated_by` = 'SYSTEM:MIGRATION'
WHERE `scope_code` = 'EVIDENCE_GLOBAL';

-- 9) 채번 이력 적재
INSERT INTO `ledger_evidence_number_histories` (
    `id`, `scope_code`, `issued_evidence_sort_no`, `issued_table`, `issued_row_id`, `created_at`, `created_by`, `note`
)
SELECT UUID(), 'EVIDENCE_GLOBAL', t.`evidence_sort_no`, 'ledger_evidence_card_sales', t.`id`, NOW(), 'SYSTEM:MIGRATION', CONCAT('phase2 backfill:', @migration_batch_id)
FROM `ledger_evidence_card_sales` t
WHERE t.`evidence_sort_no` > 0
  AND NOT EXISTS (
      SELECT 1 FROM `ledger_evidence_number_histories` h
      WHERE h.`scope_code`='EVIDENCE_GLOBAL' AND h.`issued_table`='ledger_evidence_card_sales' AND h.`issued_row_id`=t.`id`
  );

INSERT INTO `ledger_evidence_number_histories` (
    `id`, `scope_code`, `issued_evidence_sort_no`, `issued_table`, `issued_row_id`, `created_at`, `created_by`, `note`
)
SELECT UUID(), 'EVIDENCE_GLOBAL', t.`evidence_sort_no`, 'ledger_evidence_employee_expense', t.`id`, NOW(), 'SYSTEM:MIGRATION', CONCAT('phase2 backfill:', @migration_batch_id)
FROM `ledger_evidence_employee_expense` t
WHERE t.`evidence_sort_no` > 0
  AND NOT EXISTS (
      SELECT 1 FROM `ledger_evidence_number_histories` h
      WHERE h.`scope_code`='EVIDENCE_GLOBAL' AND h.`issued_table`='ledger_evidence_employee_expense' AND h.`issued_row_id`=t.`id`
  );

INSERT INTO `ledger_evidence_number_histories` (
    `id`, `scope_code`, `issued_evidence_sort_no`, `issued_table`, `issued_row_id`, `created_at`, `created_by`, `note`
)
SELECT UUID(), 'EVIDENCE_GLOBAL', t.`evidence_sort_no`, 'ledger_evidence_payroll', t.`id`, NOW(), 'SYSTEM:MIGRATION', CONCAT('phase2 backfill:', @migration_batch_id)
FROM `ledger_evidence_payroll` t
WHERE t.`evidence_sort_no` > 0
  AND NOT EXISTS (
      SELECT 1 FROM `ledger_evidence_number_histories` h
      WHERE h.`scope_code`='EVIDENCE_GLOBAL' AND h.`issued_table`='ledger_evidence_payroll' AND h.`issued_row_id`=t.`id`
  );

INSERT INTO `ledger_evidence_number_histories` (
    `id`, `scope_code`, `issued_evidence_sort_no`, `issued_table`, `issued_row_id`, `created_at`, `created_by`, `note`
)
SELECT UUID(), 'EVIDENCE_GLOBAL', t.`evidence_sort_no`, 'ledger_evidence_daily_worker', t.`id`, NOW(), 'SYSTEM:MIGRATION', CONCAT('phase2 backfill:', @migration_batch_id)
FROM `ledger_evidence_daily_worker` t
WHERE t.`evidence_sort_no` > 0
  AND NOT EXISTS (
      SELECT 1 FROM `ledger_evidence_number_histories` h
      WHERE h.`scope_code`='EVIDENCE_GLOBAL' AND h.`issued_table`='ledger_evidence_daily_worker' AND h.`issued_row_id`=t.`id`
  );

INSERT INTO `ledger_evidence_number_histories` (
    `id`, `scope_code`, `issued_evidence_sort_no`, `issued_table`, `issued_row_id`, `created_at`, `created_by`, `note`
)
SELECT UUID(), 'EVIDENCE_GLOBAL', t.`evidence_sort_no`, 'ledger_evidence_business_income', t.`id`, NOW(), 'SYSTEM:MIGRATION', CONCAT('phase2 backfill:', @migration_batch_id)
FROM `ledger_evidence_business_income` t
WHERE t.`evidence_sort_no` > 0
  AND NOT EXISTS (
      SELECT 1 FROM `ledger_evidence_number_histories` h
      WHERE h.`scope_code`='EVIDENCE_GLOBAL' AND h.`issued_table`='ledger_evidence_business_income' AND h.`issued_row_id`=t.`id`
  );

INSERT INTO `ledger_evidence_number_histories` (
    `id`, `scope_code`, `issued_evidence_sort_no`, `issued_table`, `issued_row_id`, `created_at`, `created_by`, `note`
)
SELECT UUID(), 'EVIDENCE_GLOBAL', t.`evidence_sort_no`, 'ledger_evidence_cash_sales', t.`id`, NOW(), 'SYSTEM:MIGRATION', CONCAT('phase2 backfill:', @migration_batch_id)
FROM `ledger_evidence_cash_sales` t
WHERE t.`evidence_sort_no` > 0
  AND NOT EXISTS (
      SELECT 1 FROM `ledger_evidence_number_histories` h
      WHERE h.`scope_code`='EVIDENCE_GLOBAL' AND h.`issued_table`='ledger_evidence_cash_sales' AND h.`issued_row_id`=t.`id`
  );

COMMIT;
