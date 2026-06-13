-- Phase1 backfill (DB JSON 함수 비의존 버전)
-- 대상: BANK, TAX_INVOICE(+MANUAL), CASH_RECEIPT, CARD_PURCHASE(HOMETAX/CARD/APPROVAL)

START TRANSACTION;

SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;
SET collation_connection = 'utf8mb4_unicode_ci';

SET @migration_batch_id := DATE_FORMAT(NOW(), 'PHASE1_%Y%m%d_%H%i%s');

INSERT INTO `ledger_evidence_number_sequences` (`scope_code`, `last_evidence_sort_no`, `updated_at`, `updated_by`)
SELECT 'EVIDENCE_GLOBAL', 0, NOW(), 'SYSTEM:MIGRATION'
FROM DUAL
WHERE NOT EXISTS (
    SELECT 1
    FROM `ledger_evidence_number_sequences`
    WHERE `scope_code` = 'EVIDENCE_GLOBAL'
);

SET @next_no := (
    SELECT `last_evidence_sort_no`
    FROM `ledger_evidence_number_sequences`
    WHERE `scope_code` = 'EVIDENCE_GLOBAL'
    FOR UPDATE
);

-- 1) BANK
SET @rn_bank := 0;
INSERT INTO `ledger_evidence_bank` (
    `id`, `sort_no`, `evidence_sort_no`, `source_type`, `external_key`, `evidence_date`,
    `client_id`, `project_id`, `raw_client_name`, `evidence_status`,
    `transaction_date`, `transaction_time`, `transaction_datetime`, `bank_account_id`, `transaction_type`,
    `deposit_amount`, `withdraw_amount`, `total_amount`, `balance_amount`, `balance_status`,
    `check_bill_amount`, `currency_code`, `exchange_rate`, `description`, `counterparty_name`,
    `counterparty_account_number`, `counterparty_bank_name`, `memo`,
    `created_at`, `updated_at`, `deleted_at`
)
SELECT
    b.`id`,
    COALESCE(NULLIF(b.`sort_no`, 0), (@rn_bank := @rn_bank + 1)) AS `sort_no`,
    (@next_no := @next_no + 1) AS `evidence_sort_no`,
    'BANK' AS `source_type`,
    NULLIF(TRIM(b.`bank_reference_no`), '') AS `external_key`,
    b.`transaction_date` AS `evidence_date`,
    e.`client_id`,
    e.`project_id`,
    NULLIF(TRIM(e.`client_name`), '') AS `raw_client_name`,
    CASE
        WHEN b.`deleted_at` IS NOT NULL OR e.`evidence_status` = 'DELETED' THEN 'DELETED'
        WHEN (COALESCE(b.`deposit_amount`, 0) = 0 AND COALESCE(b.`withdraw_amount`, 0) = 0) THEN 'INVALID'
        ELSE 'ACTIVE'
    END AS `evidence_status`,
    b.`transaction_date`,
    b.`transaction_time`,
    b.`transaction_datetime`,
    b.`bank_account_id`,
    b.`transaction_type`,
    b.`deposit_amount`,
    b.`withdraw_amount`,
    CASE
        WHEN COALESCE(b.`deposit_amount`, 0) > 0 THEN b.`deposit_amount`
        WHEN COALESCE(b.`withdraw_amount`, 0) > 0 THEN b.`withdraw_amount`
        ELSE 0
    END AS `total_amount`,
    b.`balance_amount`,
    b.`balance_status`,
    b.`check_bill_amount`,
    b.`currency_code`,
    b.`exchange_rate`,
    b.`description`,
    b.`counterparty_name`,
    b.`counterparty_account_number`,
    b.`counterparty_bank_name`,
    b.`memo`,
    COALESCE(b.`created_at`, NOW()),
    b.`updated_at`,
    b.`deleted_at`
FROM `ledger_bank_transactions` b
LEFT JOIN `ledger_data_evidences` e
    ON e.`id` = b.`evidence_id`
WHERE b.`deleted_at` IS NULL
  AND NOT EXISTS (
      SELECT 1
      FROM `ledger_evidence_bank` nb
      WHERE nb.`id` = b.`id`
  );

-- 2) TAX_INVOICE
SET @rn_tax := 0;
INSERT INTO `ledger_evidence_tax_invoice` (
    `id`, `sort_no`, `evidence_sort_no`, `source_type`, `external_key`, `evidence_date`,
    `client_id`, `project_id`, `raw_client_name`, `evidence_status`,
    `transaction_date`, `issue_date`, `transmit_date`,
    `supplier_business_number`, `supplier_company_name`,
    `customer_business_number`, `customer_company_name`,
    `supply_amount`, `vat_amount`, `service_amount`, `total_amount`, `description`, `memo`,
    `created_at`, `updated_at`, `deleted_at`
)
SELECT
    e.`id`,
    COALESCE(NULLIF(e.`create_sort_no`, 0), (@rn_tax := @rn_tax + 1)) AS `sort_no`,
    (@next_no := @next_no + 1) AS `evidence_sort_no`,
    CASE WHEN e.`source_type` = 'TAX_INVOICE_MANUAL' THEN 'TAX_INVOICE_MANUAL' ELSE 'TAX_INVOICE_HOMETAX' END AS `source_type`,
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
    e.`evidence_date` AS `transaction_date`,
    NULL AS `issue_date`,
    NULL AS `transmit_date`,
    LEFT(COALESCE(NULLIF(TRIM(e.`source_key`), ''), CONCAT('NOBIZ', REPLACE(e.`id`, '-', ''))), 20) AS `supplier_business_number`,
    COALESCE(NULLIF(TRIM(e.`client_name`), ''), 'UNKNOWN') AS `supplier_company_name`,
    LEFT(COALESCE(NULLIF(TRIM(e.`source_key`), ''), CONCAT('NOBIZ', REPLACE(e.`id`, '-', ''))), 20) AS `customer_business_number`,
    COALESCE(NULLIF(TRIM(e.`client_name`), ''), 'UNKNOWN') AS `customer_company_name`,
    COALESCE(e.`supply_amount`, 0),
    COALESCE(e.`vat_amount`, 0),
    0 AS `service_amount`,
    COALESCE(e.`total_amount`, 0),
    NULL AS `description`,
    NULL AS `memo`,
    e.`created_at`,
    e.`updated_at`,
    e.`deleted_at`
FROM `ledger_data_evidences` e
WHERE e.`source_type` IN ('TAX_INVOICE', 'TAX_INVOICE_MANUAL')
  AND e.`deleted_at` IS NULL
  AND NOT EXISTS (
      SELECT 1
      FROM `ledger_evidence_tax_invoice` t
      WHERE t.`id` = e.`id`
  );

-- 3) TAX_INVOICE_ITEMS (JSON 비의존 모드에서는 생성하지 않음)
-- item 정보는 JSON 파싱 가능 DB에서 별도 보강 백필 권장

-- 4) CASH_RECEIPT
SET @rn_cash := 0;
INSERT INTO `ledger_evidence_cash_receipt` (
    `id`, `sort_no`, `evidence_sort_no`, `source_type`, `external_key`, `evidence_date`,
    `client_id`, `project_id`, `raw_client_name`, `evidence_status`,
    `write_date`, `issue_method`, `merchant_business_number`, `merchant_company_name`,
    `supply_amount`, `vat_amount`, `service_amount`, `total_amount`, `memo`,
    `created_at`, `updated_at`, `deleted_at`
)
SELECT
    e.`id`,
    COALESCE(NULLIF(e.`create_sort_no`, 0), (@rn_cash := @rn_cash + 1)) AS `sort_no`,
    (@next_no := @next_no + 1) AS `evidence_sort_no`,
    CASE
        WHEN e.`source_type` = 'CASH_RECEIPT_SALES' THEN 'CASH_RECEIPT_SALES'
        ELSE 'CASH_RECEIPT_PURCHASE'
    END AS `source_type`,
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
    NULL AS `write_date`,
    NULL AS `issue_method`,
    NULL AS `merchant_business_number`,
    NULL AS `merchant_company_name`,
    COALESCE(e.`supply_amount`, 0),
    COALESCE(e.`vat_amount`, 0),
    NULL AS `service_amount`,
    COALESCE(e.`total_amount`, 0),
    NULL AS `memo`,
    e.`created_at`,
    e.`updated_at`,
    e.`deleted_at`
FROM `ledger_data_evidences` e
WHERE e.`source_type` IN ('CASH_RECEIPT', 'CASH_RECEIPT_PURCHASE', 'CASH_RECEIPT_SALES')
  AND e.`deleted_at` IS NULL
  AND NOT EXISTS (
      SELECT 1
      FROM `ledger_evidence_cash_receipt` t
      WHERE t.`id` = e.`id`
  );

-- 5) CARD_PURCHASE
SET @rn_card := 0;
INSERT INTO `ledger_evidence_card_purchase` (
    `id`, `sort_no`, `evidence_sort_no`, `source_type`, `external_key`, `evidence_date`,
    `client_id`, `project_id`, `raw_client_name`, `evidence_status`,
    `card_id`, `raw_card_name`, `raw_card_number`, `approval_date`, `billing_date`,
    `merchant_business_number`, `merchant_company_name`,
    `supply_amount`, `vat_amount`, `service_amount`, `total_amount`, `fee_amount`,
    `created_at`, `updated_at`, `deleted_at`
)
SELECT
    e.`id`,
    COALESCE(NULLIF(e.`create_sort_no`, 0), (@rn_card := @rn_card + 1)) AS `sort_no`,
    (@next_no := @next_no + 1) AS `evidence_sort_no`,
    CASE
        WHEN e.`source_type` = 'CARD_STATEMENT' THEN 'CARD_PURCHASE_CARD'
        ELSE 'CARD_PURCHASE_HOMETAX'
    END AS `source_type`,
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
    NULLIF(TRIM(e.`card_name`), '') AS `raw_card_name`,
    NULL AS `raw_card_number`,
    NULL AS `approval_date`,
    NULL AS `billing_date`,
    NULL AS `merchant_business_number`,
    NULL AS `merchant_company_name`,
    COALESCE(e.`supply_amount`, 0),
    COALESCE(e.`vat_amount`, 0),
    NULL AS `service_amount`,
    COALESCE(e.`total_amount`, 0),
    NULL AS `fee_amount`,
    e.`created_at`,
    e.`updated_at`,
    e.`deleted_at`
FROM `ledger_data_evidences` e
WHERE e.`source_type` IN ('CARD_HOMETAX', 'CARD_STATEMENT', 'CARD_APPROVAL')
  AND e.`deleted_at` IS NULL
  AND NOT EXISTS (
      SELECT 1
      FROM `ledger_evidence_card_purchase` t
      WHERE t.`id` = e.`id`
  );

UPDATE `ledger_evidence_number_sequences`
SET `last_evidence_sort_no` = @next_no,
    `updated_at` = NOW(),
    `updated_by` = 'SYSTEM:MIGRATION'
WHERE `scope_code` = 'EVIDENCE_GLOBAL';

INSERT INTO `ledger_evidence_number_histories` (
    `id`, `scope_code`, `issued_evidence_sort_no`, `issued_table`, `issued_row_id`, `created_at`, `created_by`, `note`
)
SELECT UUID(), 'EVIDENCE_GLOBAL', b.`evidence_sort_no`, 'ledger_evidence_bank', b.`id`, NOW(), 'SYSTEM:MIGRATION', CONCAT('phase1 backfill:', @migration_batch_id)
FROM `ledger_evidence_bank` b
WHERE b.`evidence_sort_no` > 0
  AND NOT EXISTS (
      SELECT 1 FROM `ledger_evidence_number_histories` h
      WHERE BINARY h.`scope_code` = 'EVIDENCE_GLOBAL'
        AND BINARY h.`issued_table` = 'ledger_evidence_bank'
        AND BINARY h.`issued_row_id` = BINARY b.`id`
  );

INSERT INTO `ledger_evidence_number_histories` (
    `id`, `scope_code`, `issued_evidence_sort_no`, `issued_table`, `issued_row_id`, `created_at`, `created_by`, `note`
)
SELECT UUID(), 'EVIDENCE_GLOBAL', t.`evidence_sort_no`, 'ledger_evidence_tax_invoice', t.`id`, NOW(), 'SYSTEM:MIGRATION', CONCAT('phase1 backfill:', @migration_batch_id)
FROM `ledger_evidence_tax_invoice` t
WHERE t.`evidence_sort_no` > 0
  AND NOT EXISTS (
      SELECT 1 FROM `ledger_evidence_number_histories` h
      WHERE BINARY h.`scope_code` = 'EVIDENCE_GLOBAL'
        AND BINARY h.`issued_table` = 'ledger_evidence_tax_invoice'
        AND BINARY h.`issued_row_id` = BINARY t.`id`
  );

INSERT INTO `ledger_evidence_number_histories` (
    `id`, `scope_code`, `issued_evidence_sort_no`, `issued_table`, `issued_row_id`, `created_at`, `created_by`, `note`
)
SELECT UUID(), 'EVIDENCE_GLOBAL', c.`evidence_sort_no`, 'ledger_evidence_cash_receipt', c.`id`, NOW(), 'SYSTEM:MIGRATION', CONCAT('phase1 backfill:', @migration_batch_id)
FROM `ledger_evidence_cash_receipt` c
WHERE c.`evidence_sort_no` > 0
  AND NOT EXISTS (
      SELECT 1 FROM `ledger_evidence_number_histories` h
      WHERE BINARY h.`scope_code` = 'EVIDENCE_GLOBAL'
        AND BINARY h.`issued_table` = 'ledger_evidence_cash_receipt'
        AND BINARY h.`issued_row_id` = BINARY c.`id`
  );

INSERT INTO `ledger_evidence_number_histories` (
    `id`, `scope_code`, `issued_evidence_sort_no`, `issued_table`, `issued_row_id`, `created_at`, `created_by`, `note`
)
SELECT UUID(), 'EVIDENCE_GLOBAL', p.`evidence_sort_no`, 'ledger_evidence_card_purchase', p.`id`, NOW(), 'SYSTEM:MIGRATION', CONCAT('phase1 backfill:', @migration_batch_id)
FROM `ledger_evidence_card_purchase` p
WHERE p.`evidence_sort_no` > 0
  AND NOT EXISTS (
      SELECT 1 FROM `ledger_evidence_number_histories` h
      WHERE BINARY h.`scope_code` = 'EVIDENCE_GLOBAL'
        AND BINARY h.`issued_table` = 'ledger_evidence_card_purchase'
        AND BINARY h.`issued_row_id` = BINARY p.`id`
  );

COMMIT;
