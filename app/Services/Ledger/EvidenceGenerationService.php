<?php

namespace App\Services\Ledger;

use App\Models\Ledger\ProcessingItemModel;
use App\Services\Ledger\ProcessingItemTreeService;
use PDO;

class EvidenceGenerationService
{
    private array $query = [];

    public function __construct(
        private PDO $pdo,
        private $ensureEvidenceBusinessInfoColumns,
        private $ensureEvidenceSortColumns,
        private $ensureBankTransactionBalanceColumns,
        private $ensureBankTransactionEvidenceRows,
        private $isAllowedDataType,
        private $normalizeDataType,
        private $normalizeImportSourceType,
        private $importTypesForSourceType,
        private $sourceTypeSql,
        private $sourceTypeForDataType,
        private $sourceTypeLabel,
        private $importTypeLabel,
        private $tableExists,
        private $placeholdersForIds,
        private $columns,
        private $normalizeBankTransactionPayload,
        private $normalizeEvidenceMappedPayloadForResponse,
        private $mergeEvidenceBusinessInfoIntoPayload,
        private $isUuid,
        private $businessRefNameById,
        private $applyReadinessToEvidenceRow,
        private $evidencePayloadSortNo,
        private $formatTransactionCreateError
    ) {
    }

    private function ensureEvidenceBusinessInfoColumns(): void
    {
        ($this->ensureEvidenceBusinessInfoColumns)();
    }

    private function ensureEvidenceSortColumns(): void
    {
        ($this->ensureEvidenceSortColumns)();
    }

    private function ensureBankTransactionBalanceColumns(): void
    {
        ($this->ensureBankTransactionBalanceColumns)();
    }

    private function isAllowedDataType(string $type): bool
    {
        return ($this->isAllowedDataType)($type);
    }

    private function normalizeDataType(string $type): string
    {
        return ($this->normalizeDataType)($type);
    }

    private function normalizeImportSourceType(string $sourceType): string
    {
        return ($this->normalizeImportSourceType)($sourceType);
    }

    private function importTypesForSourceType(string $sourceType): array
    {
        return ($this->importTypesForSourceType)($sourceType);
    }

    private function sourceTypeSql(string $column): string
    {
        return ($this->sourceTypeSql)($column);
    }

    private function sourceTypeForDataType(string $dataType): string
    {
        return ($this->sourceTypeForDataType)($dataType);
    }

    private function sourceTypeLabel(string $sourceType): string
    {
        return ($this->sourceTypeLabel)($sourceType);
    }

    private function importTypeLabel(string $importType): string
    {
        return ($this->importTypeLabel)($importType);
    }

    private function tableExists(string $table): bool
    {
        return ($this->tableExists)($table);
    }

    private function placeholdersForIds(array $ids, string $prefix): array
    {
        return ($this->placeholdersForIds)($ids, $prefix);
    }

    private function columns(string $formatId): array
    {
        return ($this->columns)($formatId);
    }

    private function isUuid(string $value): bool
    {
        return ($this->isUuid)($value);
    }

    private function businessRefNameById(string $refType, string $id): ?string
    {
        return ($this->businessRefNameById)($refType, $id);
    }

    private function applyReadinessToEvidenceRow(array &$row): void
    {
        ($this->applyReadinessToEvidenceRow)($row);
    }

    private function evidencePayloadSortNo(array $row, string $key): int
    {
        return ($this->evidencePayloadSortNo)($row, $key);
    }

    private function formatTransactionCreateError(string $message, array $row = [], int $rowNo = 0): string
    {
        return ($this->formatTransactionCreateError)($message, $row, $rowNo);
    }

    public function seedRows(array $query): array
    {
        $this->query = $query;
        if ((string) ($query['repair_schema'] ?? '') === '1') {
            $this->ensureEvidenceBusinessInfoColumns();
            $this->ensureEvidenceSortColumns();
        }
        if ((string) ($query['repair_bank_orphans'] ?? '') === '1') {
            $this->ensureEvidenceBusinessInfoColumns();
            $this->ensureBankTransactionBalanceColumns();
            ($this->ensureBankTransactionEvidenceRows)();
        }
        $status = strtoupper(trim((string) ($query['process_status'] ?? $query['status'] ?? '')));
        $requestedSourceType = strtoupper(trim((string) ($query['source_type'] ?? '')));
        $importType = $this->normalizeDataType((string) ($query['import_type'] ?? $query['data_type'] ?? ''));
        $sourceType = '';
        if ($requestedSourceType !== '') {
            $normalizedRequested = $this->normalizeDataType($requestedSourceType);
            if ($this->isAllowedDataType($normalizedRequested)) {
                $importType = $normalizedRequested;
            } else {
                $sourceType = $this->normalizeImportSourceType($requestedSourceType);
            }
        }
        if ($importType !== '' && !$this->isAllowedDataType($importType)) {
            return ['payload' => ['success' => true, 'data' => []]];
        }
        $sequenceScope = $this->evidenceSequenceScopeFromRequest('', $importType);
        $this->ensureEvidenceSortColumns();
        if ((string) ($query['type_counts'] ?? '') === '1') {
            $stmt = $this->pdo->query("
                SELECT
                    CASE
                        WHEN import_type IS NULL OR import_type = '' THEN 'UNKNOWN'
                        ELSE import_type
                    END AS import_type,
                    SUM(row_count) AS row_count
                FROM (
                    SELECT source_type AS import_type, COUNT(*) AS row_count
                    FROM ledger_evidence_bank
                    WHERE deleted_at IS NULL
                    GROUP BY source_type
                    UNION ALL
                    SELECT source_type AS import_type, COUNT(*) AS row_count
                    FROM ledger_evidence_tax_invoice
                    WHERE deleted_at IS NULL
                    GROUP BY source_type
                    UNION ALL
                    SELECT source_type AS import_type, COUNT(*) AS row_count
                    FROM ledger_evidence_cash_receipt
                    WHERE deleted_at IS NULL
                    GROUP BY source_type
                    UNION ALL
                    SELECT source_type AS import_type, COUNT(*) AS row_count
                    FROM ledger_evidence_card_purchase
                    WHERE deleted_at IS NULL
                    GROUP BY source_type
                ) t
                GROUP BY
                    CASE
                        WHEN import_type IS NULL OR import_type = '' THEN 'UNKNOWN'
                        ELSE import_type
                    END
            ");
            $data = [];
            foreach (($stmt->fetchAll(PDO::FETCH_ASSOC) ?: []) as $row) {
                $type = $this->normalizeDataType((string) ($row['import_type'] ?? ''));
                $data[] = [
                    'import_type' => $type !== '' ? $type : 'UNKNOWN',
                    'row_count' => (int) ($row['row_count'] ?? 0),
                ];
            }
            return ['payload' => ['success' => true, 'data' => $data]];
        }
        $filters = $this->seedRowFiltersFromRequest();

        $where = [];
        $params = [];
        $requestedId = trim((string) ($query['id'] ?? ''));
        if ($requestedId !== '') {
            $where[] = 'r.evidence_id = :requested_id';
            $params[':requested_id'] = $requestedId;
        }
        if ($status === 'READY') {
            $where[] = "COALESCE(pr.processing_status, 'READY') = 'READY'";
        } elseif ($status === 'PROCESSED') {
            $where[] = "COALESCE(pr.processing_status, '') = 'PROCESSED'";
        } elseif ($status === 'ERROR') {
            $where[] = "COALESCE(pr.processing_status, '') = 'ERROR'";
        } elseif ($status === 'DUPLICATED') {
            $where[] = "COALESCE(pr.processing_status, '') = 'DUPLICATED'";
        }
        if ($importType !== '') {
            $where[] = 'r.evidence_type = :import_type';
            $params[':import_type'] = $importType;
        } elseif ($sourceType !== '') {
            $types = $this->importTypesForSourceType($sourceType);
            if ($types === []) {
                return ['payload' => ['success' => true, 'data' => []]];
            }
            $keys = [];
            foreach ($types as $index => $type) {
                $key = ':source_type_' . $index;
                $keys[] = $key;
                $params[$key] = $type;
            }
            $where[] = 'r.evidence_type IN (' . implode(', ', $keys) . ')';
        }
        $where[] = $status === 'DELETED' ? 'r.deleted_at IS NOT NULL' : 'r.deleted_at IS NULL';
        $isServerPaged = isset($query['draw']) || isset($query['start']) || isset($query['length']);
        $pageStart = max(0, (int) ($query['start'] ?? 0));
        $pageLength = (int) ($query['length'] ?? 0);
        if ($pageLength <= 0) {
            $pageLength = 100;
        }
        $pageLength = min($pageLength, 500);
        $recordsFiltered = null;
        if ($isServerPaged && $filters === []) {
            $countStmt = $this->pdo->prepare("
                SELECT COUNT(*)
                FROM ledger_evidence_payloads r
                LEFT JOIN ledger_evidence_processing pr
                    ON pr.evidence_type = r.evidence_type
                   AND pr.evidence_id = r.evidence_id
                WHERE " . implode(' AND ', $where) . "
            ");
            $countStmt->execute($params);
            $recordsFiltered = (int) $countStmt->fetchColumn();

            $pageIds = $this->evidencePageIdsForServerPaging($where, $params, $importType, $pageStart, $pageLength, $sequenceScope);
            if ($pageIds === []) {
                return ['payload' => [
                    'success' => true,
                    'draw' => (int) ($query['draw'] ?? 0),
                    'recordsTotal' => $recordsFiltered,
                    'recordsFiltered' => $recordsFiltered,
                    'data' => [],
                ]];
            }
            $idPlaceholders = [];
            foreach ($pageIds as $index => $id) {
                $key = ':page_id_' . $index;
                $idPlaceholders[] = $key;
                $params[$key] = $id;
            }
            $where[] = 'r.evidence_id IN (' . implode(', ', $idPlaceholders) . ')';
        }

        $sql = "
            SELECT
                r.evidence_id AS id,
                NULL AS seed_batch_id,
                " . $this->sourceTypeSql('r.evidence_type') . " AS source_type,
                r.evidence_type AS import_type,
                st.code_name AS source_type_name,
                it.code_name AS import_type_name,
                CAST(JSON_UNQUOTE(JSON_EXTRACT(r.mapped_payload_json, '$._create_sort_no')) AS UNSIGNED) AS create_sort_no,
                CAST(JSON_UNQUOTE(JSON_EXTRACT(r.mapped_payload_json, '$._status_sort_no')) AS UNSIGNED) AS status_sort_no,
                0 AS row_no,
                r.format_id,
                r.raw_json,
                r.mapped_payload_json AS parsed_json,
                r.source_key,
                NULL AS evidence_date,
                JSON_UNQUOTE(JSON_EXTRACT(r.mapped_payload_json, '$.client_id')) AS client_id,
                JSON_UNQUOTE(JSON_EXTRACT(r.mapped_payload_json, '$.project_id')) AS project_id,
                JSON_UNQUOTE(JSON_EXTRACT(r.mapped_payload_json, '$.employee_id')) AS employee_id,
                JSON_UNQUOTE(JSON_EXTRACT(r.mapped_payload_json, '$.bank_account_id')) AS bank_account_id,
                JSON_UNQUOTE(JSON_EXTRACT(r.mapped_payload_json, '$.card_id')) AS card_id,
                JSON_UNQUOTE(JSON_EXTRACT(r.mapped_payload_json, '$.client_name')) AS client_name,
                JSON_UNQUOTE(JSON_EXTRACT(r.mapped_payload_json, '$.project_name')) AS project_name,
                JSON_UNQUOTE(JSON_EXTRACT(r.mapped_payload_json, '$.employee_name')) AS employee_name,
                JSON_UNQUOTE(JSON_EXTRACT(r.mapped_payload_json, '$.bank_account_name')) AS bank_account_name,
                JSON_UNQUOTE(JSON_EXTRACT(r.mapped_payload_json, '$.card_name')) AS card_name,
                CASE WHEN r.deleted_at IS NULL THEN 'ACTIVE' ELSE 'DELETED' END AS evidence_status,
                COALESCE(pr.processing_status, 'READY') AS transaction_status,
                CASE WHEN vx.target_id IS NULL THEN 'WAITING' ELSE 'LINKED' END AS voucher_status,
                COALESCE(pr.review_status, 'NORMAL') AS review_status,
                CASE
                    WHEN COALESCE(pr.processing_status, 'READY') IN ('ERROR', 'DUPLICATED', 'PROCESSING', 'PROCESSED') THEN pr.processing_status
                    WHEN tx.target_id IS NOT NULL THEN 'PROCESSED'
                    ELSE COALESCE(pr.processing_status, 'READY')
                END AS process_status,
                CASE
                    WHEN COALESCE(pr.processing_status, 'READY') IN ('ERROR', 'DUPLICATED', 'PROCESSING', 'PROCESSED') THEN pr.processing_status
                    WHEN tx.target_id IS NOT NULL THEN 'PROCESSED'
                    ELSE COALESCE(pr.processing_status, 'READY')
                END AS status,
                pr.last_error_message AS error_message,
                tx.target_id AS transaction_id,
                r.latest_imported_at AS processed_at,
                r.created_at,
                r.updated_at,
                r.deleted_at,
                NULL AS file_name,
                '' AS format_name
            FROM ledger_evidence_payloads r
            LEFT JOIN ledger_evidence_processing pr
                ON pr.evidence_type = r.evidence_type
               AND pr.evidence_id = r.evidence_id
               AND pr.deleted_at IS NULL
            LEFT JOIN ledger_evidence_links tx
                ON tx.evidence_type = r.evidence_type
               AND tx.evidence_id = r.evidence_id
               AND tx.target_type = 'TRANSACTION'
               AND tx.deleted_at IS NULL
            LEFT JOIN ledger_evidence_links vx
                ON vx.evidence_type = r.evidence_type
               AND vx.evidence_id = r.evidence_id
               AND vx.target_type = 'VOUCHER'
               AND vx.deleted_at IS NULL
            LEFT JOIN system_codes st
                ON st.deleted_at IS NULL
               AND st.is_active = 1
               AND st.code_group IN ('IMPORT_SOURCE', 'SOURCE_TYPE')
               AND st.code = " . $this->sourceTypeSql('r.evidence_type') . "
            LEFT JOIN system_codes it
                ON it.deleted_at IS NULL
               AND it.is_active = 1
               AND it.code_group = 'IMPORT_TYPE'
               AND it.code = r.evidence_type
        ";
        $sql .= ' WHERE ' . implode(' AND ', $where);
        $sql .= ' ' . $this->evidenceRowsOrderSql($importType, $sequenceScope);

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $this->syncVoucherStatusFromActiveLinks($rows);
        foreach ($rows as &$row) {
            $row['raw_payload'] = json_decode((string) ($row['raw_json'] ?? ''), true) ?: [];
            $row['mapped_payload'] = json_decode((string) ($row['parsed_json'] ?? ''), true) ?: [];
            $mappedPayload = is_array($row['mapped_payload']) ? $row['mapped_payload'] : [];
            if ($row['import_type'] === 'BANK_TRANSACTION') {
                $mappedPayload = ($this->normalizeBankTransactionPayload)($mappedPayload);
            }
            $mappedPayload = ($this->normalizeEvidenceMappedPayloadForResponse)($mappedPayload);
            ($this->mergeEvidenceBusinessInfoIntoPayload)($row, $mappedPayload);
            $row['mapped_payload'] = $mappedPayload;
            $payloadDataType = $this->normalizeDataType((string) ($mappedPayload['import_type'] ?? $mappedPayload['data_type'] ?? $mappedPayload['evidence_type'] ?? ''));
            if (in_array((string) ($row['import_type'] ?? ''), ['', 'MANUAL'], true) && $payloadDataType !== '') {
                $row['import_type'] = $payloadDataType;
                $row['source_type'] = $this->sourceTypeForDataType($payloadDataType);
                $row['source_type_name'] = $this->sourceTypeLabel((string) $row['source_type']);
                $row['import_type_name'] = $this->importTypeLabel($payloadDataType);
            }
            $resolvedClientName = '';
            $clientIdForDisplay = trim((string) ($row['client_id'] ?? $mappedPayload['client_id'] ?? ''));
            if ($clientIdForDisplay !== '' && $this->isUuid($clientIdForDisplay)) {
                $resolvedClientName = (string) ($this->businessRefNameById('CLIENT', $clientIdForDisplay) ?? '');
            }
            $row['client_name'] = (string) (
                $row['import_type'] === 'BANK_TRANSACTION'
                    ? ($mappedPayload['counterparty_name']
                        ?? $mappedPayload['counterparty_account_holder_name']
                        ?? $mappedPayload['counterparty_account_holder']
                        ?? ($resolvedClientName !== '' ? $resolvedClientName : null)
                        ?? $row['client_name']
                        ?? $mappedPayload['client_name']
                        ?? $mappedPayload['client_company_name']
                        ?? '')
                    : (($resolvedClientName !== '' ? $resolvedClientName : null)
                ?? $row['client_name']
                ?? $mappedPayload['client_name']
                ?? $mappedPayload['client_company_name']
                ?? $mappedPayload['client_business_number']
                ?? $mappedPayload['supplier_name']
                ?? $mappedPayload['customer_name']
                ?? $mappedPayload['supplier_company_name']
                ?? $mappedPayload['customer_company_name']
                ?? '')
            );
            unset($row['raw_json'], $row['parsed_json']);
        }
        unset($row);
        $this->attachRequiredFormatColumnsToRows($rows);
        $this->sortEvidenceRowsForResponse($rows, $importType, $sequenceScope);
        foreach ($rows as &$row) {
            $this->applyReadinessToEvidenceRow($row);
        }
        unset($row);
        if ($status === 'READY') {
            $rows = array_values(array_filter($rows, static fn(array $row): bool => ($row['readiness_status'] ?? '') === 'READY'));
        } elseif (in_array($status, ['NOT_READY', 'REVIEW_REQUIRED', 'VERIFY_ONLY'], true)) {
            $rows = array_values(array_filter($rows, static fn(array $row): bool => ($row['readiness_status'] ?? '') === $status));
        }
        if ($filters !== []) {
            $rows = array_values(array_filter($rows, fn(array $row): bool => $this->seedRowMatchesFilters($row, $filters)));
        }
        $responseSortKey = $this->evidenceSortKeyForScope($sequenceScope, $importType);
        foreach ($rows as $index => &$row) {
            $row['applied_sort_no'] = $this->evidencePayloadSortNo($row, $responseSortKey);
            $row['row_no'] = $index + 1;
            if (!empty($row['error_message'])) {
                $row['error_message'] = $this->formatTransactionCreateError(
                    (string) $row['error_message'],
                    is_array($row['mapped_payload']) ? $row['mapped_payload'] : [],
                    (int) ($row['row_no'] ?? 0)
                );
            }
        }
        unset($row);
        $rows = $this->expandEvidenceRowsWithProcessingItems($rows, $sequenceScope);

        if ($isServerPaged) {
            $total = $recordsFiltered ?? count($rows);
            return ['payload' => [
                'success' => true,
                'draw' => (int) ($query['draw'] ?? 0),
                'recordsTotal' => $total,
                'recordsFiltered' => $total,
                'data' => $rows,
            ]];
        }

        return ['payload' => ['success' => true, 'data' => $rows]];
    }

    private function attachRequiredFormatColumnsToRows(array &$rows): void
    {
        if ($rows === []) {
            return;
        }

        $columnsByFormatId = [];
        $formatIdByType = [];

        foreach ($rows as &$row) {
            $formatId = trim((string) ($row['format_id'] ?? ''));
            if ($formatId === '') {
                $dataType = $this->normalizeDataType((string) ($row['import_type'] ?? $row['source_type'] ?? $row['data_type'] ?? ''));
                if ($dataType !== '') {
                    if (!array_key_exists($dataType, $formatIdByType)) {
                        $formatIdByType[$dataType] = $this->defaultFormatIdForDataType($dataType);
                    }
                    $formatId = $formatIdByType[$dataType] ?? '';
                }
            }
            if ($formatId !== '' && !array_key_exists($formatId, $columnsByFormatId)) {
                $columnsByFormatId[$formatId] = $this->columns($formatId);
            }
            if ($formatId !== '' && trim((string) ($row['format_id'] ?? '')) === '') {
                $row['format_id'] = $formatId;
            }
            $row['format_columns'] = $formatId !== '' ? ($columnsByFormatId[$formatId] ?? []) : [];
        }
        unset($row);
    }

    private function expandEvidenceRowsWithProcessingItems(array $rows, string $sequenceScope = ''): array
    {
        if ($rows === [] || !$this->tableExists('ledger_processing_items')) {
            return $rows;
        }

        $itemModel = new ProcessingItemModel($this->pdo);
        $treeService = new ProcessingItemTreeService();
        $expanded = [];

        foreach ($rows as $row) {
            $evidenceId = trim((string) ($row['id'] ?? ''));
            if ($evidenceId === '') {
                $expanded[] = $row;
                continue;
            }

            $items = $itemModel->getBySource('ledger_data_evidences', $evidenceId);
            $hasSplitStructure = false;
            foreach ($items as $item) {
                if (trim((string) ($item['parent_item_id'] ?? '')) !== '' || strtoupper((string) ($item['item_status'] ?? '')) === 'SPLIT') {
                    $hasSplitStructure = true;
                    break;
                }
            }
            if (!$hasSplitStructure || $items === []) {
                $row['evidence_id'] = $evidenceId;
                $row['processing_item_id'] = $items[0]['id'] ?? null;
                $row['processing_display_path'] = (string) ($row['row_no'] ?? '');
                $row['processing_has_children'] = false;
                $row['processing_is_child'] = false;
                $expanded[] = $row;
                continue;
            }

            $tree = $treeService->buildTree($items, (string) ($row['row_no'] ?? ''), false);
            $flat = $treeService->flattenTree($tree);
            $childIdsByParent = [];
            foreach ($items as $item) {
                $parentId = trim((string) ($item['parent_item_id'] ?? ''));
                if ($parentId !== '') {
                    $childIdsByParent[$parentId] = true;
                }
            }

            $processingRows = [];
            foreach ($flat as $item) {
                $expandedRow = $row;
                $expandedRow['evidence_id'] = $evidenceId;
                $expandedRow['processing_item_id'] = (string) ($item['id'] ?? '');
                $expandedRow['processing_parent_item_id'] = $item['parent_item_id'] ?? null;
                $expandedRow['processing_display_path'] = (string) ($item['display_no'] ?? $item['display_path'] ?? $item['sort_no'] ?? $row['row_no'] ?? '');
                $expandedRow['processing_item_status'] = (string) ($item['item_status'] ?? '');
                $expandedRow['processing_is_current'] = (int) ($item['is_current'] ?? 0) === 1;
                $expandedRow['processing_is_child'] = trim((string) ($item['parent_item_id'] ?? '')) !== '';
                $expandedRow['processing_has_children'] = isset($childIdsByParent[(string) ($item['id'] ?? '')]);
                $expandedRow['processing_level'] = (int) ($item['level'] ?? 1);
                $expandedRow['_select_disabled'] = (bool) $expandedRow['processing_is_child'];
                if ($expandedRow['processing_display_path'] !== '') {
                    $expandedRow['row_no'] = $expandedRow['processing_display_path'];
                }

                $payload = json_decode((string) ($item['mapped_payload_json'] ?? ''), true);
                if (is_array($payload)) {
                    if ($expandedRow['import_type'] === 'BANK_TRANSACTION') {
                        $payload = ($this->normalizeBankTransactionPayload)($payload);
                    }
                    $payload = ($this->normalizeEvidenceMappedPayloadForResponse)($payload);
                    ($this->mergeEvidenceBusinessInfoIntoPayload)($expandedRow, $payload);
                    foreach (['quantity', 'unit_price', 'supply_amount', 'vat_amount', 'total_amount', 'currency', 'description', 'memo'] as $key) {
                        if (array_key_exists($key, $item) && $item[$key] !== null && $item[$key] !== '') {
                            $payload[$key] = $item[$key];
                        }
                    }
                    $expandedRow['mapped_payload'] = $payload;
                }

                $processingRows[] = $expandedRow;
            }
            $parentRow = null;
            $children = [];
            foreach ($processingRows as $processingRow) {
                if (!empty($processingRow['processing_is_child'])) {
                    $children[] = $processingRow;
                    continue;
                }
                if ($parentRow === null || !empty($processingRow['processing_has_children'])) {
                    $parentRow = $processingRow;
                }
            }
            if ($parentRow === null) {
                $parentRow = $row;
                $parentRow['evidence_id'] = $evidenceId;
                $parentRow['processing_has_children'] = $children !== [];
                $parentRow['processing_is_child'] = false;
            }
            $parentRow['processing_children'] = array_values($children);
            $parentRow['processing_child_count'] = count($children);
            $parentRow['processing_has_children'] = count($children) > 0;
            $parentRow['processing_is_child'] = false;
            $parentRow['row_no'] = $row['row_no'] ?? ($parentRow['row_no'] ?? '');
            $parentRow['processing_display_path'] = (string) ($parentRow['row_no'] ?? '');
            $parentRow['_select_disabled'] = false;
            $expanded[] = $parentRow;
        }

        return $expanded;
    }

    private function defaultFormatIdForDataType(string $dataType): string
    {
        return '';
    }

    private function evidencePageIdsForServerPaging(array $where, array $params, string $importType, int $pageStart, int $pageLength, string $sequenceScope = ''): array
    {
        $pageStart = max(0, $pageStart);
        $pageLength = max(1, min($pageLength, 500));
        $stmt = $this->pdo->prepare("
            SELECT r.evidence_id AS id
            FROM ledger_evidence_payloads r
            LEFT JOIN ledger_evidence_processing pr
                ON pr.evidence_type = r.evidence_type
               AND pr.evidence_id = r.evidence_id
            WHERE " . implode(' AND ', $where) . "
            " . $this->evidenceRowsOrderSql($importType, $sequenceScope) . "
            LIMIT {$pageLength} OFFSET {$pageStart}
        ");
        $stmt->execute($params);

        return array_values(array_filter(array_map(
            static fn(array $row): string => trim((string) ($row['id'] ?? '')),
            $stmt->fetchAll(PDO::FETCH_ASSOC) ?: []
        )));
    }

    private function syncVoucherStatusFromActiveLinks(array &$rows): void
    {
        if ($rows === [] || !$this->tableExists('ledger_evidence_links') || !$this->tableExists('ledger_vouchers')) {
            return;
        }

        $evidenceIds = array_values(array_filter(array_unique(array_map(
            static fn(array $row): string => trim((string) ($row['id'] ?? '')),
            $rows
        ))));
        if ($evidenceIds === []) {
            return;
        }

        [$inSql, $params] = $this->placeholdersForIds($evidenceIds, 'active_voucher_evidence');
        $stmt = $this->pdo->prepare("
            SELECT DISTINCT l.evidence_id
            FROM ledger_evidence_links l
            INNER JOIN ledger_vouchers v
                ON v.id = l.target_id
               AND v.deleted_at IS NULL
            WHERE l.deleted_at IS NULL
              AND l.target_type = 'VOUCHER'
              AND l.evidence_id IN ({$inSql})
        ");
        $stmt->execute($params);
        $createdEvidenceIds = array_flip(array_filter(array_map(
            static fn(array $row): string => trim((string) ($row['evidence_id'] ?? '')),
            $stmt->fetchAll(PDO::FETCH_ASSOC) ?: []
        )));

        if ($createdEvidenceIds === []) {
            return;
        }

        foreach ($rows as &$row) {
            $id = trim((string) ($row['id'] ?? ''));
            if ($id !== '' && isset($createdEvidenceIds[$id])) {
                $row['voucher_status'] = 'CREATED';
            }
        }
        unset($row);
    }

    private function evidenceRowsOrderSql(string $importType, string $sequenceScope = ''): string
    {
        $normalizedType = $this->normalizeDataType($importType);
        $sortColumn = $this->evidenceSortColumnForScope($sequenceScope, $importType);
        if ($sortColumn !== '') {
            $sortExpr = "CAST(JSON_UNQUOTE(JSON_EXTRACT(r.mapped_payload_json, '$._" . $sortColumn . "')) AS UNSIGNED)";
            $fallback = $sortColumn === 'create_sort_no'
                ? "
                    r.latest_imported_at DESC,
                    r.created_at DESC,
                    r.evidence_id ASC
                "
                : "
                    COALESCE(
                        JSON_UNQUOTE(JSON_EXTRACT(r.mapped_payload_json, '$.evidence_date')),
                        JSON_UNQUOTE(JSON_EXTRACT(r.mapped_payload_json, '$.transaction_date')),
                        DATE(r.latest_imported_at),
                        DATE(r.created_at)
                    ) DESC,
                    r.latest_imported_at DESC,
                    r.created_at DESC,
                    r.evidence_id ASC
                ";

            return "
                ORDER BY
                    CASE WHEN {$sortExpr} IS NULL OR {$sortExpr} < 1 THEN 1 ELSE 0 END ASC,
                    {$sortExpr} ASC,
                    {$fallback}
            ";
        }

        if ($normalizedType === '') {
            return "
                ORDER BY
                    r.latest_imported_at DESC,
                    r.created_at DESC,
                    r.evidence_id ASC
            ";
        }

        return "
            ORDER BY
                COALESCE(
                    JSON_UNQUOTE(JSON_EXTRACT(r.mapped_payload_json, '$.evidence_date')),
                    JSON_UNQUOTE(JSON_EXTRACT(r.mapped_payload_json, '$.transaction_date')),
                    DATE(r.latest_imported_at),
                    DATE(r.created_at)
                ) DESC,
                r.latest_imported_at DESC,
                r.created_at DESC,
                r.evidence_id ASC
        ";
    }

    private function bankTransactionSortValue(array $row): string
    {
        $payload = is_array($row['mapped_payload'] ?? null) ? $row['mapped_payload'] : [];
        $dateTime = trim((string) (
            $payload['transaction_datetime']
            ?? $payload['transaction_at']
            ?? $payload['거래일시']
            ?? ''
        ));
        if ($dateTime === '') {
            $date = trim((string) (
                $payload['transaction_date']
                ?? $payload['거래일자']
                ?? $row['evidence_date']
                ?? ''
            ));
            $time = trim((string) (
                $payload['transaction_time']
                ?? $payload['거래시간']
                ?? ''
            ));
            $dateTime = trim($date . ' ' . $time);
        }

        $timestamp = strtotime($dateTime);
        if ($timestamp !== false) {
            return date('Y-m-d H:i:s', $timestamp);
        }

        return (string) ($row['processed_at'] ?? $row['created_at'] ?? '');
    }

    private function sortEvidenceRowsForResponse(array &$rows, string $importType, string $sequenceScope = ''): void
    {
        $normalizedType = $this->normalizeDataType($importType);
        $sortKey = $this->evidenceSortKeyForScope($sequenceScope, $importType);
        $isCreateScope = $sortKey === '_create_sort_no';

        usort($rows, function (array $a, array $b) use ($normalizedType, $sortKey, $isCreateScope): int {
            $aSort = $this->evidencePayloadSortNo($a, $sortKey);
            $bSort = $this->evidencePayloadSortNo($b, $sortKey);
            if ($aSort > 0 && $bSort > 0 && $aSort !== $bSort) {
                return $aSort <=> $bSort;
            }
            if ($aSort > 0 && $bSort < 1) {
                return -1;
            }
            if ($aSort < 1 && $bSort > 0) {
                return 1;
            }

            if ($isCreateScope) {
                $createdCompare = strcmp(
                    (string) ($b['processed_at'] ?? $b['created_at'] ?? ''),
                    (string) ($a['processed_at'] ?? $a['created_at'] ?? '')
                );
                if ($createdCompare !== 0) {
                    return $createdCompare;
                }
                return $this->evidencePayloadSortNo($a, '_row_no') <=> $this->evidencePayloadSortNo($b, '_row_no');
            }

            return strcmp(
                $this->evidenceTypeSortValue($b, $normalizedType),
                $this->evidenceTypeSortValue($a, $normalizedType)
            );
        });
    }

    private function evidenceSequenceScopeFromRequest(string $default = '', string $importType = ''): string
    {
        $scope = strtolower(trim((string) ($this->query['sequence_scope'] ?? $this->query['sort_scope'] ?? $default)));
        if (in_array($scope, ['create', 'status'], true)) {
            return $scope;
        }

        return $this->normalizeDataType($importType) === '' ? 'create' : 'status';
    }

    private function evidenceSortKeyForScope(string $sequenceScope, string $importType = ''): string
    {
        $scope = strtolower(trim($sequenceScope));
        if ($scope === 'create') {
            return '_create_sort_no';
        }
        if ($scope === 'status') {
            return '_status_sort_no';
        }

        return $this->normalizeDataType($importType) === '' ? '_create_sort_no' : '_status_sort_no';
    }

    private function evidenceSortColumnForScope(string $sequenceScope, string $importType = ''): string
    {
        $key = $this->evidenceSortKeyForScope($sequenceScope, $importType);
        return $key === '_create_sort_no' ? 'create_sort_no' : 'status_sort_no';
    }

    private function evidenceTypeSortValue(array $row, string $dataType): string
    {
        if ($dataType === 'BANK_TRANSACTION') {
            return $this->bankTransactionSortValue($row);
        }

        $payload = is_array($row['mapped_payload'] ?? null) ? $row['mapped_payload'] : [];
        $keys = match ($dataType) {
            'TAX_INVOICE' => ['write_date', 'written_date', 'transaction_date', 'evidence_date', 'issue_date'],
            'CASH_RECEIPT', 'CASH_RECEIPT_PURCHASE', 'CASH_RECEIPT_SALES' => ['purchase_datetime', 'purchase_at', 'purchase_date', 'transaction_datetime', 'transaction_date', 'evidence_date'],
            'CARD_STATEMENT', 'CARD_APPROVAL', 'CARD_HOMETAX', 'CARD_COMPANY', 'CARD', 'CREDIT_CARD' => ['approval_datetime', 'approved_at', 'approval_date', 'approved_date', 'transaction_datetime', 'transaction_date', 'evidence_date'],
            default => ['transaction_datetime', 'transaction_date', 'evidence_date', 'issue_date'],
        };

        foreach ($keys as $key) {
            $value = trim((string) ($payload[$key] ?? ''));
            if ($value === '') {
                continue;
            }
            $timestamp = strtotime($value);
            if ($timestamp !== false) {
                return date('Y-m-d H:i:s', $timestamp);
            }
        }

        $timestamp = strtotime((string) ($row['processed_at'] ?? $row['created_at'] ?? ''));
        return $timestamp !== false ? date('Y-m-d H:i:s', $timestamp) : '';
    }

    private function seedRowFiltersFromRequest(): array
    {
        $decoded = [];
        if (!empty($this->query['filters'])) {
            $json = json_decode((string) $this->query['filters'], true);
            $decoded = is_array($json) ? $json : [];
        }

        return array_values(array_filter($decoded, static function ($filter): bool {
            return is_array($filter) && !empty($filter['field']);
        }));
    }

    private function seedRowMatchesFilters(array $row, array $filters): bool
    {
        foreach ($filters as $filter) {
            $field = (string) ($filter['field'] ?? '');
            $value = $filter['value'] ?? '';
            if ($field === '' || $value === '' || $value === null) {
                continue;
            }

            $actual = $this->seedRowFilterValue($row, $field);
            if (is_array($value)) {
                $start = (string) ($value['start'] ?? '');
                $end = (string) ($value['end'] ?? '');
                $text = (string) $actual;
                if ($start !== '' && $text < $start) {
                    return false;
                }
                if ($end !== '' && $text > $end) {
                    return false;
                }
                continue;
            }

            $needles = array_values(array_filter(array_map('trim', explode(',', (string) $value))));
            if ($needles === []) {
                continue;
            }

            $haystack = mb_strtolower((string) $actual);
            $matched = false;
            foreach ($needles as $needle) {
                if ($needle !== '' && str_contains($haystack, mb_strtolower($needle))) {
                    $matched = true;
                    break;
                }
            }
            if (!$matched) {
                return false;
            }
        }

        return true;
    }

    private function seedRowFilterValue(array $row, string $field): string
    {
        if ($field === 'source_type') {
            $value = (string) ($row['source_type'] ?? '');
            return trim($value . ' ' . (string) ($row['source_type_name'] ?? '') . ' ' . $this->sourceTypeLabel($value));
        }

        if ($field === 'import_type') {
            $value = (string) ($row['import_type'] ?? $row['seed_source_type'] ?? '');
            return trim($value . ' ' . (string) ($row['import_type_name'] ?? '') . ' ' . $this->importTypeLabel($value));
        }

        if ($field === 'client_name') {
            $mapped = is_array($row['mapped_payload'] ?? null) ? $row['mapped_payload'] : [];
            $clientId = trim((string) ($row['client_id'] ?? $mapped['client_id'] ?? ''));
            $resolvedClientName = $clientId !== '' && $this->isUuid($clientId)
                ? (string) ($this->businessRefNameById('CLIENT', $clientId) ?? '')
                : '';
            return (string) (
                ($resolvedClientName !== '' ? $resolvedClientName : null)
                ?? $row['client_name']
                ?? $mapped['client_name']
                ?? $mapped['client_company_name']
                ?? $mapped['client_business_number']
                ?? $mapped['supplier_company_name']
                ?? $mapped['customer_company_name']
                ?? ''
            );
        }

        if (str_starts_with($field, 'mapped_payload.')) {
            $key = substr($field, strlen('mapped_payload.'));
            $mapped = is_array($row['mapped_payload'] ?? null) ? $row['mapped_payload'] : [];
            return (string) ($mapped[$key] ?? '');
        }

        return (string) ($row[$field] ?? '');
    }
}
