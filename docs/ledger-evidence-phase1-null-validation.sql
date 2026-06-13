-- 실행 전 검증용 SQL (실행 계획서 전용)
-- 원칙:
-- 1) UNKNOWN 강제 입력 금지
-- 2) 0 강제 보정 금지
-- 3) 예외 데이터는 분리 후 수기/재처리

/* A. 세금계산서 필수 식별값 NULL 건수 */
SELECT
    COUNT(*) AS total_rows,
    SUM(
        CASE
            WHEN NULLIF(TRIM(JSON_UNQUOTE(JSON_EXTRACT(p.mapped_payload_json, '$.supplier_business_number'))), '') IS NULL
            THEN 1 ELSE 0
        END
    ) AS supplier_business_number_null_cnt,
    SUM(
        CASE
            WHEN NULLIF(TRIM(JSON_UNQUOTE(JSON_EXTRACT(p.mapped_payload_json, '$.customer_business_number'))), '') IS NULL
            THEN 1 ELSE 0
        END
    ) AS customer_business_number_null_cnt
FROM ledger_data_evidences e
JOIN ledger_evidence_payloads p
  ON p.evidence_type = e.source_type
 AND p.evidence_id = e.id
WHERE e.source_type IN ('TAX_INVOICE', 'TAX_INVOICE_MANUAL')
  AND e.deleted_at IS NULL;

/* B. 세금계산서 필수 식별값 예외 리스트 */
SELECT
    e.id,
    e.source_type,
    e.evidence_date,
    e.source_key AS legacy_source_key,
    NULLIF(TRIM(JSON_UNQUOTE(JSON_EXTRACT(p.mapped_payload_json, '$.supplier_business_number'))), '') AS supplier_business_number,
    NULLIF(TRIM(JSON_UNQUOTE(JSON_EXTRACT(p.mapped_payload_json, '$.customer_business_number'))), '') AS customer_business_number
FROM ledger_data_evidences e
JOIN ledger_evidence_payloads p
  ON p.evidence_type = e.source_type
 AND p.evidence_id = e.id
WHERE e.source_type IN ('TAX_INVOICE', 'TAX_INVOICE_MANUAL')
  AND e.deleted_at IS NULL
  AND (
      NULLIF(TRIM(JSON_UNQUOTE(JSON_EXTRACT(p.mapped_payload_json, '$.supplier_business_number'))), '') IS NULL
      OR NULLIF(TRIM(JSON_UNQUOTE(JSON_EXTRACT(p.mapped_payload_json, '$.customer_business_number'))), '') IS NULL
  )
ORDER BY e.evidence_date, e.id;

/* C. 세금계산서 품목 필수 금액 NULL 건수 (item_name 있는 경우만) */
SELECT
    COUNT(*) AS candidate_item_rows,
    SUM(
        CASE
            WHEN CAST(NULLIF(TRIM(JSON_UNQUOTE(JSON_EXTRACT(p.mapped_payload_json, '$.item_supply_amount'))), '') AS DECIMAL(18,2)) IS NULL
            THEN 1 ELSE 0
        END
    ) AS item_supply_amount_null_cnt,
    SUM(
        CASE
            WHEN CAST(NULLIF(TRIM(JSON_UNQUOTE(JSON_EXTRACT(p.mapped_payload_json, '$.item_vat_amount'))), '') AS DECIMAL(18,2)) IS NULL
            THEN 1 ELSE 0
        END
    ) AS item_vat_amount_null_cnt
FROM ledger_data_evidences e
JOIN ledger_evidence_payloads p
  ON p.evidence_type = e.source_type
 AND p.evidence_id = e.id
WHERE e.source_type IN ('TAX_INVOICE', 'TAX_INVOICE_MANUAL')
  AND e.deleted_at IS NULL
  AND NULLIF(TRIM(JSON_UNQUOTE(JSON_EXTRACT(p.mapped_payload_json, '$.item_name'))), '') IS NOT NULL;

/* D. 세금계산서 품목 필수 금액 예외 리스트 */
SELECT
    e.id AS tax_invoice_id,
    e.source_type,
    e.evidence_date,
    NULLIF(TRIM(JSON_UNQUOTE(JSON_EXTRACT(p.mapped_payload_json, '$.item_name'))), '') AS item_name,
    CAST(NULLIF(TRIM(JSON_UNQUOTE(JSON_EXTRACT(p.mapped_payload_json, '$.item_supply_amount'))), '') AS DECIMAL(18,2)) AS item_supply_amount,
    CAST(NULLIF(TRIM(JSON_UNQUOTE(JSON_EXTRACT(p.mapped_payload_json, '$.item_vat_amount'))), '') AS DECIMAL(18,2)) AS item_vat_amount
FROM ledger_data_evidences e
JOIN ledger_evidence_payloads p
  ON p.evidence_type = e.source_type
 AND p.evidence_id = e.id
WHERE e.source_type IN ('TAX_INVOICE', 'TAX_INVOICE_MANUAL')
  AND e.deleted_at IS NULL
  AND NULLIF(TRIM(JSON_UNQUOTE(JSON_EXTRACT(p.mapped_payload_json, '$.item_name'))), '') IS NOT NULL
  AND (
      CAST(NULLIF(TRIM(JSON_UNQUOTE(JSON_EXTRACT(p.mapped_payload_json, '$.item_supply_amount'))), '') AS DECIMAL(18,2)) IS NULL
      OR CAST(NULLIF(TRIM(JSON_UNQUOTE(JSON_EXTRACT(p.mapped_payload_json, '$.item_vat_amount'))), '') AS DECIMAL(18,2)) IS NULL
  )
ORDER BY e.evidence_date, e.id;
