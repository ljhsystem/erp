<?php

namespace App\Services\Ledger;

use App\Models\Ledger\ProcessingItemModel;
use App\Services\Ledger\ProcessingItemTreeService;
use PDO;

class EvidenceGenerationService
{
    private const PAYLOAD_ONLY_TYPE_COUNTS = [
        'TAX_INVOICE_MANUAL',
        'IMPORT_INVOICE',
        'SHOPPING_ORDER',
        'PAYROLL_WITHHOLDING',
        'BUSINESS_DATA',
        'PAYROLL',
        'BUSINESS_INCOME',
        'EMPLOYEE_EXPENSE',
        'CONSTRUCTION',
    ];

    private array $columnExistsCache = [];

    private array $query = [];

    public function __construct(
        private PDO $pdo,
        private $ensureEvidenceBusinessInfoColumns,
        private $ensureEvidenceSortColumns,
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

    private function columnExists(string $tableName, string $columnName): bool
    {
        $cacheKey = $tableName . '.' . $columnName;
        if (array_key_exists($cacheKey, $this->columnExistsCache)) {
            return $this->columnExistsCache[$cacheKey];
        }

        try {
            $stmt = $this->pdo->prepare("
                SELECT 1
                FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME = :table_name
                  AND COLUMN_NAME = :column_name
                LIMIT 1
            ");
            $stmt->execute([
                ':table_name' => $tableName,
                ':column_name' => $columnName,
            ]);
            $exists = (bool) $stmt->fetchColumn();
        } catch (\Throwable) {
            $exists = false;
        }

        $this->columnExistsCache[$cacheKey] = $exists;
        return $exists;
    }

    private function firstExistingColumnExpr(string $tableName, string $alias, array $candidates, string $fallback = 'NULL'): string
    {
        $normalizedAlias = trim($alias);
        foreach ($candidates as $columnName) {
            $normalizedColumn = trim((string) $columnName);
            if ($normalizedColumn !== '' && $this->columnExists($tableName, $normalizedColumn)) {
                return "{$normalizedAlias}.{$normalizedColumn}";
            }
        }

        return $fallback;
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

    private function applyReferenceDisplayNames(array &$row, array $mappedPayload): void
    {
        $referenceMap = [
            'CLIENT' => ['id' => 'client_id', 'name' => 'client_name'],
            'PROJECT' => ['id' => 'project_id', 'name' => 'project_name'],
            'EMPLOYEE' => ['id' => 'employee_id', 'name' => 'employee_name'],
            'ACCOUNT' => ['id' => 'bank_account_id', 'name' => 'bank_account_name'],
            'CARD' => ['id' => 'card_id', 'name' => 'card_name'],
            'TEAM' => ['id' => 'team_id', 'name' => 'team_name'],
        ];

        foreach ($referenceMap as $refType => $keys) {
            $idKey = $keys['id'];
            $nameKey = $keys['name'];
            $id = trim((string) ($row[$idKey] ?? $mappedPayload[$idKey] ?? ''));
            $currentName = trim((string) ($row[$nameKey] ?? $mappedPayload[$nameKey] ?? ''));
            $resolvedName = '';

            if ($id === '' && $currentName !== '' && $this->isUuid($currentName)) {
                $id = $currentName;
            }

            if ($id !== '' && $this->isUuid($id)) {
                $resolvedName = trim((string) ($this->businessRefNameById($refType, $id) ?? ''));
            }

            if ($resolvedName !== '') {
                $row[$nameKey] = $resolvedName;
                continue;
            }

            if ($currentName !== '' && !$this->isUuid($currentName)) {
                $row[$nameKey] = $currentName;
            }
        }
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
            $payloadCounts = $this->payloadEvidenceTypeCounts();
            $bodyCounts = $this->bodyEvidenceTypeCounts();
            $knownTypes = array_values(array_unique(array_merge(array_keys($payloadCounts), array_keys($bodyCounts))));
            sort($knownTypes);

            $data = [];
            foreach ($knownTypes as $type) {
                $normalizedType = $this->normalizeDataType((string) $type);
                if ($normalizedType === '') {
                    continue;
                }

                $data[] = [
                    'import_type' => $normalizedType,
                    'row_count' => max(
                        (int) ($payloadCounts[$normalizedType] ?? 0),
                        (int) ($bodyCounts[$normalizedType] ?? 0)
                    ),
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
            $where[] = 'r.evidence_type COLLATE utf8mb4_general_ci = :import_type COLLATE utf8mb4_general_ci';
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
            $collatedKeys = array_map(static fn(string $key): string => $key . ' COLLATE utf8mb4_general_ci', $keys);
            $where[] = 'r.evidence_type COLLATE utf8mb4_general_ci IN (' . implode(', ', $collatedKeys) . ')';
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
        $bodyTableTypes = [
            'BANK_TRANSACTION',
            'TAX_INVOICE',
            'TAX_INVOICE_MANUAL',
            'CASH_RECEIPT',
            'CASH_RECEIPT_PURCHASE',
            'CASH_RECEIPT_SALES',
        ];
        $useBodyFallbackPaging = in_array($importType, $bodyTableTypes, true);
        $useBodyTable = in_array($importType, $bodyTableTypes, true);
        if ($isServerPaged && $filters === []) {
            if ($useBodyFallbackPaging) {
                $recordsFiltered = match ($importType) {
                    'BANK_TRANSACTION' => $this->countBankRowsFromBodyTable($status, $requestedId),
                    'CASH_RECEIPT', 'CASH_RECEIPT_PURCHASE', 'CASH_RECEIPT_SALES' => $this->countCashRowsFromBodyTable($importType, $status, $requestedId),
                    default => $this->countTaxRowsFromBodyTable($importType, $status, $requestedId),
                };
            } else {
                $countStmt = $this->pdo->prepare("
                    SELECT COUNT(*)
                    FROM ledger_evidence_payloads r
                    LEFT JOIN ledger_evidence_processing pr
                        ON pr.evidence_type COLLATE utf8mb4_general_ci = r.evidence_type COLLATE utf8mb4_general_ci
                       AND pr.evidence_id COLLATE utf8mb4_general_ci = r.evidence_id COLLATE utf8mb4_general_ci
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
        }

        if ($useBodyTable) {
            $rows = match ($importType) {
                'BANK_TRANSACTION' => $this->bankRowsFromBodyTable($status, $requestedId),
                'CASH_RECEIPT', 'CASH_RECEIPT_PURCHASE', 'CASH_RECEIPT_SALES' => $this->cashRowsFromBodyTable($importType, $status, $requestedId),
                default => $this->taxRowsFromBodyTable($importType, $status, $requestedId),
            };
        } else {
            $sql = "
                SELECT
                    r.evidence_id AS id,
                    NULL AS seed_batch_id,
                    " . $this->sourceTypeSql('r.evidence_type') . " AS source_type,
                    r.evidence_type AS import_type,
                    '' AS source_type_name,
                    '' AS import_type_name,
                    " . $this->evidenceBodySortNoSql('r.evidence_type', 'r.evidence_id', 'sort_no') . " AS sort_no,
                    " . $this->evidenceBodySortNoSql('r.evidence_type', 'r.evidence_id', 'evidence_sort_no') . " AS evidence_sort_no,
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
                    JSON_UNQUOTE(JSON_EXTRACT(r.mapped_payload_json, '$.team_id')) AS team_id,
                    JSON_UNQUOTE(JSON_EXTRACT(r.mapped_payload_json, '$.client_name')) AS client_name,
                    JSON_UNQUOTE(JSON_EXTRACT(r.mapped_payload_json, '$.project_name')) AS project_name,
                    JSON_UNQUOTE(JSON_EXTRACT(r.mapped_payload_json, '$.employee_name')) AS employee_name,
                    JSON_UNQUOTE(JSON_EXTRACT(r.mapped_payload_json, '$.bank_account_name')) AS bank_account_name,
                    JSON_UNQUOTE(JSON_EXTRACT(r.mapped_payload_json, '$.card_name')) AS card_name,
                    JSON_UNQUOTE(JSON_EXTRACT(r.mapped_payload_json, '$.team_name')) AS team_name,
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
                    ON pr.evidence_type COLLATE utf8mb4_general_ci = r.evidence_type COLLATE utf8mb4_general_ci
                   AND pr.evidence_id COLLATE utf8mb4_general_ci = r.evidence_id COLLATE utf8mb4_general_ci
                   AND pr.deleted_at IS NULL
                LEFT JOIN ledger_evidence_links tx
                    ON tx.evidence_type COLLATE utf8mb4_general_ci = r.evidence_type COLLATE utf8mb4_general_ci
                   AND tx.evidence_id COLLATE utf8mb4_general_ci = r.evidence_id COLLATE utf8mb4_general_ci
                   AND tx.target_type = 'TRANSACTION'
                   AND tx.deleted_at IS NULL
                LEFT JOIN ledger_evidence_links vx
                    ON vx.evidence_type COLLATE utf8mb4_general_ci = r.evidence_type COLLATE utf8mb4_general_ci
                   AND vx.evidence_id COLLATE utf8mb4_general_ci = r.evidence_id COLLATE utf8mb4_general_ci
                   AND vx.target_type = 'VOUCHER'
                   AND vx.deleted_at IS NULL
            ";
            $sql .= ' WHERE ' . implode(' AND ', $where);
            $sql .= ' ' . $this->evidenceRowsOrderSql($importType, $sequenceScope);

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        }
        $this->logTrashListQueryDiagnostics($status, $importType, implode(' AND ', $where), $params, $rows);
        $this->syncVoucherStatusFromActiveLinks($rows);
        foreach ($rows as &$row) {
            $row['raw_payload'] = json_decode((string) ($row['raw_json'] ?? ''), true) ?: [];
            $row['mapped_payload'] = json_decode((string) ($row['parsed_json'] ?? ''), true) ?: [];
            $mappedPayload = is_array($row['mapped_payload']) ? $row['mapped_payload'] : [];
            if ($row['import_type'] === 'BANK_TRANSACTION' && $mappedPayload === []) {
                $mappedPayload = array_merge($row, [
                    'import_type' => $row['import_type'] ?? 'BANK_TRANSACTION',
                    'source_type' => $row['source_type'] ?? 'BANK',
                ]);
            }
            if ($row['import_type'] === 'BANK_TRANSACTION') {
                $mappedPayload = ($this->normalizeBankTransactionPayload)($mappedPayload);
            }
            $mappedPayload = ($this->normalizeEvidenceMappedPayloadForResponse)($mappedPayload);
            ($this->mergeEvidenceBusinessInfoIntoPayload)($row, $mappedPayload);
            $row['mapped_payload'] = $mappedPayload;
            $this->mergeMappedPayloadIntoRow($row, $mappedPayload);
            $payloadDataType = $this->normalizeDataType((string) ($mappedPayload['import_type'] ?? $mappedPayload['data_type'] ?? $mappedPayload['evidence_type'] ?? ''));
            if (in_array((string) ($row['import_type'] ?? ''), ['', 'MANUAL'], true) && $payloadDataType !== '') {
                $row['import_type'] = $payloadDataType;
                $row['source_type'] = $this->sourceTypeForDataType($payloadDataType);
            }
            $row['source_type_name'] = $this->sourceTypeLabel((string) ($row['source_type'] ?? ''));
            $row['import_type_name'] = $this->importTypeLabel((string) ($row['import_type'] ?? $payloadDataType));
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
            $this->applyReferenceDisplayNames($row, $mappedPayload);
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
        if ($useBodyTable) {
            $recordsFiltered = count($rows);
        }
        $responseSortKey = $this->evidenceSortKeyForScope($sequenceScope, $importType);
        foreach ($rows as $index => &$row) {
            $row['applied_sort_no'] = max(0, (int) ($row['sort_no'] ?? 0));
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
            if ($useBodyTable) {
                $rows = array_slice($rows, $pageStart, $pageLength);
            }
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

        foreach ($rows as &$row) {
            $formatId = trim((string) ($row['format_id'] ?? ''));
            if ($formatId !== '' && !array_key_exists($formatId, $columnsByFormatId)) {
                $columnsByFormatId[$formatId] = $this->columns($formatId);
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

            $items = $itemModel->getBySource('ledger_evidence_payloads', $evidenceId);
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
                    $this->mergeMappedPayloadIntoRow($expandedRow, $payload);
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

    private function evidencePageIdsForServerPaging(array $where, array $params, string $importType, int $pageStart, int $pageLength, string $sequenceScope = ''): array
    {
        $pageStart = max(0, $pageStart);
        $pageLength = max(1, min($pageLength, 500));
        $stmt = $this->pdo->prepare("
            SELECT r.evidence_id AS id
            FROM ledger_evidence_payloads r
            LEFT JOIN ledger_evidence_processing pr
                ON pr.evidence_type COLLATE utf8mb4_general_ci = r.evidence_type COLLATE utf8mb4_general_ci
               AND pr.evidence_id COLLATE utf8mb4_general_ci = r.evidence_id COLLATE utf8mb4_general_ci
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
        $sortExpr = $this->evidenceBodySortNoSql('r.evidence_type', 'r.evidence_id', 'sort_no');
        $fallback = "
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
        $requestedOrder = $this->requestedEvidenceRowOrderings();

        if ($requestedOrder !== []) {
            usort($rows, function (array $a, array $b) use ($requestedOrder, $normalizedType): int {
                foreach ($requestedOrder as $ordering) {
                    $field = (string) ($ordering['field'] ?? '');
                    if ($field === '') {
                        continue;
                    }

                    $comparison = $this->compareEvidenceRowValues(
                        $this->evidenceRowSortableValue($a, $field),
                        $this->evidenceRowSortableValue($b, $field)
                    );
                    if ($comparison === 0) {
                        continue;
                    }

                    return (($ordering['dir'] ?? 'asc') === 'desc') ? -$comparison : $comparison;
                }

                return $this->compareDefaultEvidenceRows($a, $b, $normalizedType);
            });
            return;
        }

        usort($rows, fn(array $a, array $b): int => $this->compareDefaultEvidenceRows($a, $b, $normalizedType));
    }

    private function requestedEvidenceRowOrderings(): array
    {
        $orders = $this->query['order'] ?? [];
        $columns = $this->query['columns'] ?? [];
        if (!is_array($orders) || !is_array($columns)) {
            return [];
        }

        $result = [];
        foreach ($orders as $order) {
            if (!is_array($order)) {
                continue;
            }

            $columnIndex = isset($order['column']) ? (int) $order['column'] : -1;
            if ($columnIndex < 0 || !isset($columns[$columnIndex]) || !is_array($columns[$columnIndex])) {
                continue;
            }

            $field = $this->normalizeRequestedEvidenceOrderField($columns[$columnIndex]);
            if ($field === '') {
                continue;
            }

            $result[] = [
                'field' => $field,
                'dir' => strtolower(trim((string) ($order['dir'] ?? 'asc'))) === 'desc' ? 'desc' : 'asc',
            ];
        }

        return $result;
    }

    private function normalizeRequestedEvidenceOrderField(array $column): string
    {
        $candidates = [
            $column['data'] ?? '',
            $column['name'] ?? '',
        ];

        foreach ($candidates as $candidate) {
            $field = trim((string) $candidate);
            if ($field === '' || $field === 'null' || str_starts_with($field, '__')) {
                continue;
            }

            if (str_contains($field, '.')) {
                $segments = array_values(array_filter(array_map('trim', explode('.', $field)), static fn(string $part): bool => $part !== ''));
                if ($segments !== []) {
                    $field = (string) end($segments);
                }
            }

            return $field;
        }

        return '';
    }

    private function evidenceRowSortableValue(array $row, string $field): mixed
    {
        $normalizedField = trim($field);
        if ($normalizedField === '') {
            return null;
        }

        $value = $row[$normalizedField] ?? null;
        if (($value === null || $value === '') && isset($row['mapped_payload']) && is_array($row['mapped_payload'])) {
            $value = $row['mapped_payload'][$normalizedField] ?? $value;
        }

        if ($normalizedField === 'sort_no') {
            $display = (string) ($value ?? $row['processing_display_path'] ?? '');
            if ($display !== '') {
                $parts = array_values(array_filter(array_map('trim', explode('-', $display)), static fn(string $part): bool => $part !== ''));
                if ($parts !== []) {
                    $numeric = 0.0;
                    foreach ($parts as $index => $part) {
                        $numeric += ((float) $part) / (1000 ** $index);
                    }
                    return $numeric;
                }
            }
        }

        if ($this->isEvidenceSortableNumericField($normalizedField)) {
            if ($value === null || $value === '') {
                return null;
            }
            return (float) preg_replace('/[^\d\.\-]/', '', (string) $value);
        }

        if ($this->isEvidenceSortableDateField($normalizedField)) {
            $timestamp = strtotime((string) $value);
            return $timestamp === false ? null : $timestamp;
        }

        return mb_strtolower(trim((string) $value));
    }

    private function isEvidenceSortableNumericField(string $field): bool
    {
        $normalized = strtolower(trim($field));
        if ($normalized === '') {
            return false;
        }

        return in_array($normalized, [
            'sort_no',
            'evidence_sort_no',
            'row_no',
            'deposit_amount',
            'withdraw_amount',
            'balance_amount',
            'raw_deposit_amount',
            'raw_withdraw_amount',
            'raw_balance_amount',
            'raw_total_amount',
            'raw_supply_amount',
            'raw_vat_amount',
            'supply_amount',
            'vat_amount',
            'total_amount',
            'amount',
        ], true);
    }

    private function isEvidenceSortableDateField(string $field): bool
    {
        $normalized = strtolower(trim($field));
        if ($normalized === '') {
            return false;
        }

        return in_array($normalized, [
            'transaction_datetime',
            'transaction_date',
            'transaction_time',
            'evidence_date',
            'raw_transaction_datetime',
            'raw_transaction_date',
            'raw_written_date',
            'raw_issue_date',
            'raw_transmit_date',
            'created_at',
            'updated_at',
            'deleted_at',
            'processed_at',
        ], true) || str_contains($normalized, '_date') || str_contains($normalized, '_at') || str_contains($normalized, '_datetime');
    }

    private function compareEvidenceRowValues(mixed $a, mixed $b): int
    {
        if ($a === $b) {
            return 0;
        }

        if ($a === null || $a === '') {
            return 1;
        }

        if ($b === null || $b === '') {
            return -1;
        }

        if ((is_int($a) || is_float($a)) && (is_int($b) || is_float($b))) {
            return $a <=> $b;
        }

        return strcmp((string) $a, (string) $b);
    }

    private function compareDefaultEvidenceRows(array $a, array $b, string $normalizedType): int
    {
        $aSort = max(0, (int) ($a['sort_no'] ?? 0));
        $bSort = max(0, (int) ($b['sort_no'] ?? 0));
        if ($aSort > 0 && $bSort > 0 && $aSort !== $bSort) {
            return $aSort <=> $bSort;
        }
        if ($aSort > 0 && $bSort < 1) {
            return -1;
        }
        if ($aSort < 1 && $bSort > 0) {
            return 1;
        }

        return strcmp(
            $this->evidenceTypeSortValue($b, $normalizedType),
            $this->evidenceTypeSortValue($a, $normalizedType)
        );
    }

    private function evidenceBodySortNoSql(string $typeColumn, string $idColumn, string $sortColumn): string
    {
        $escapedTypeColumn = trim($typeColumn);
        $escapedIdColumn = trim($idColumn);
        $escapedSortColumn = trim($sortColumn);
        $idCompareSql = "body.id COLLATE utf8mb4_general_ci = {$escapedIdColumn} COLLATE utf8mb4_general_ci";

        return "
            CASE
                WHEN {$escapedTypeColumn} = 'BANK_TRANSACTION' THEN (
                    SELECT body.{$escapedSortColumn}
                    FROM ledger_evidence_bank_transaction body
                    WHERE {$idCompareSql}
                    LIMIT 1
                )
                WHEN {$escapedTypeColumn} = 'TAX_INVOICE' THEN (
                    SELECT body.{$escapedSortColumn}
                    FROM ledger_evidence_tax_invoice body
                    WHERE {$idCompareSql}
                    LIMIT 1
                )
                WHEN {$escapedTypeColumn} IN ('CASH_RECEIPT', 'CASH_RECEIPT_PURCHASE', 'CASH_RECEIPT_SALES') THEN (
                    SELECT body.{$escapedSortColumn}
                    FROM ledger_evidence_cash_receipt body
                    WHERE {$idCompareSql}
                    LIMIT 1
                )
                WHEN {$escapedTypeColumn} = 'CARD_HOMETAX' THEN (
                    SELECT body.{$escapedSortColumn}
                    FROM ledger_evidence_card_hometax body
                    WHERE {$idCompareSql}
                    LIMIT 1
                )
                WHEN {$escapedTypeColumn} IN ('CARD', 'CARD_STATEMENT', 'CARD_APPROVAL') THEN (
                    SELECT body.{$escapedSortColumn}
                    FROM ledger_evidence_card_statement body
                    WHERE {$idCompareSql}
                    LIMIT 1
                )
                ELSE NULL
            END
        ";
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

    private function bankRowsFromBodyTable(string $status, string $requestedId): array
    {
        $where = [$status === 'DELETED' ? 'body.deleted_at IS NOT NULL' : 'body.deleted_at IS NULL'];
        $params = [];

        if ($requestedId !== '') {
            $where[] = 'body.id COLLATE utf8mb4_general_ci = :requested_id COLLATE utf8mb4_general_ci';
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

        $sql = "
            SELECT
                body.*,
                body.id AS id,
                'BANK' AS source_type,
                'BANK_TRANSACTION' AS import_type,
                st.code_name AS source_type_name,
                it.code_name AS import_type_name,
                body.sort_no AS sort_no,
                body.evidence_sort_no AS evidence_sort_no,
                NULL AS create_sort_no,
                NULL AS status_sort_no,
                0 AS row_no,
                COALESCE(p.format_id, '') AS format_id,
                COALESCE(p.raw_json, '') AS raw_json,
                COALESCE(p.mapped_payload_json, '') AS parsed_json,
                body.external_key AS source_key,
                body.raw_transaction_datetime AS evidence_date,
                CASE WHEN body.deleted_at IS NULL THEN 'ACTIVE' ELSE 'DELETED' END AS evidence_status,
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
                body.updated_at AS processed_at,
                body.created_at,
                body.updated_at,
                body.deleted_at,
                '' AS file_name,
                '' AS format_name
             FROM ledger_evidence_bank_transaction body
             LEFT JOIN ledger_evidence_payloads p
                 ON p.evidence_type COLLATE utf8mb4_general_ci = 'BANK_TRANSACTION' COLLATE utf8mb4_general_ci
                AND p.evidence_id COLLATE utf8mb4_general_ci = body.id COLLATE utf8mb4_general_ci
                AND p.deleted_at IS NULL
             LEFT JOIN ledger_evidence_processing pr
                 ON pr.evidence_type COLLATE utf8mb4_general_ci = 'BANK_TRANSACTION' COLLATE utf8mb4_general_ci
                AND pr.evidence_id COLLATE utf8mb4_general_ci = body.id COLLATE utf8mb4_general_ci
                AND pr.deleted_at IS NULL
             LEFT JOIN ledger_evidence_links tx
                 ON tx.evidence_type COLLATE utf8mb4_general_ci = 'BANK_TRANSACTION' COLLATE utf8mb4_general_ci
                AND tx.evidence_id COLLATE utf8mb4_general_ci = body.id COLLATE utf8mb4_general_ci
                AND tx.target_type = 'TRANSACTION'
                AND tx.deleted_at IS NULL
             LEFT JOIN ledger_evidence_links vx
                 ON vx.evidence_type COLLATE utf8mb4_general_ci = 'BANK_TRANSACTION' COLLATE utf8mb4_general_ci
                AND vx.evidence_id COLLATE utf8mb4_general_ci = body.id COLLATE utf8mb4_general_ci
                AND vx.target_type = 'VOUCHER'
                AND vx.deleted_at IS NULL
            LEFT JOIN system_codes st
                ON st.deleted_at IS NULL
               AND st.is_active = 1
               AND st.code_group IN ('IMPORT_SOURCE', 'SOURCE_TYPE')
               AND st.code = 'BANK'
            LEFT JOIN system_codes it
                ON it.deleted_at IS NULL
               AND it.is_active = 1
               AND it.code_group = 'IMPORT_TYPE'
               AND it.code = 'BANK_TRANSACTION'
            WHERE " . implode(' AND ', $where) . "
            ORDER BY body.sort_no ASC, body.updated_at DESC, body.created_at DESC
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    private function countBankRowsFromBodyTable(string $status, string $requestedId): int
    {
        $where = [$status === 'DELETED' ? 'body.deleted_at IS NOT NULL' : 'body.deleted_at IS NULL'];
        $params = [];

        if ($requestedId !== '') {
            $where[] = 'body.id COLLATE utf8mb4_general_ci = :requested_id COLLATE utf8mb4_general_ci';
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

        $stmt = $this->pdo->prepare("
            SELECT COUNT(*)
            FROM ledger_evidence_bank_transaction body
            LEFT JOIN ledger_evidence_processing pr
                ON pr.evidence_type COLLATE utf8mb4_general_ci = 'BANK_TRANSACTION' COLLATE utf8mb4_general_ci
               AND pr.evidence_id COLLATE utf8mb4_general_ci = body.id COLLATE utf8mb4_general_ci
               AND pr.deleted_at IS NULL
            WHERE " . implode(' AND ', $where) . "
        ");
        $stmt->execute($params);

        return (int) $stmt->fetchColumn();
    }

    private function taxRowsFromBodyTable(string $importType, string $status, string $requestedId): array
    {
        $normalizedType = $this->normalizeDataType($importType);
        $isManualTaxInvoice = $normalizedType === 'TAX_INVOICE_MANUAL';
        $taxTable = $isManualTaxInvoice ? 'ledger_evidence_tax_invoice_manual' : 'ledger_evidence_tax_invoice';
        $taxEvidenceType = $isManualTaxInvoice ? 'TAX_INVOICE_MANUAL' : 'TAX_INVOICE';
        $taxSourceTypeFallback = $isManualTaxInvoice ? "'MANUAL'" : "'HOMETAX'";
        $taxEvidenceDateExpr = $this->firstExistingColumnExpr(
            $taxTable,
            'body',
            ['transaction_date', 'issue_date', 'transmit_date'],
            'NULL'
        );
        $taxSourceKeyExpr = $this->firstExistingColumnExpr(
            $taxTable,
            'body',
            ['external_key', 'source_key', 'approval_number'],
            "''"
        );
        $taxClientIdExpr = $this->firstExistingColumnExpr(
            $taxTable,
            'body',
            ['client_id'],
            "''"
        );
        $taxProjectIdExpr = $this->firstExistingColumnExpr(
            $taxTable,
            'body',
            ['project_id'],
            "''"
        );
        $taxClientNameExpr = $this->firstExistingColumnExpr(
            $taxTable,
            'body',
            ['raw_client_name', 'customer_company_name', 'supplier_company_name', 'description'],
            "''"
        );
        $taxSourceTypeExpr = $this->columnExists($taxTable, 'source_type')
            ? "CASE WHEN body.source_type LIKE '%MANUAL%' THEN 'MANUAL' ELSE 'HOMETAX' END"
            : $taxSourceTypeFallback;
        $where = [$status === 'DELETED' ? 'body.deleted_at IS NOT NULL' : 'body.deleted_at IS NULL'];
        $params = [];

        if ($requestedId !== '') {
            $where[] = 'body.id COLLATE utf8mb4_general_ci = :requested_id COLLATE utf8mb4_general_ci';
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

        $sql = "
            SELECT
                body.*,
                body.id AS id,
                {$taxSourceTypeExpr} AS source_type,
                '{$taxEvidenceType}' AS import_type,
                '' AS source_type_name,
                '' AS import_type_name,
                body.sort_no AS sort_no,
                body.evidence_sort_no AS evidence_sort_no,
                NULL AS create_sort_no,
                NULL AS status_sort_no,
                0 AS row_no,
                COALESCE(p.format_id, '') AS format_id,
                COALESCE(p.raw_json, '') AS raw_json,
                COALESCE(p.mapped_payload_json, '') AS parsed_json,
                {$taxSourceKeyExpr} AS source_key,
                {$taxEvidenceDateExpr} AS evidence_date,
                {$taxClientIdExpr} AS client_id,
                {$taxProjectIdExpr} AS project_id,
                '' AS employee_id,
                '' AS bank_account_id,
                '' AS card_id,
                '' AS team_id,
                COALESCE({$taxClientNameExpr}, '') AS client_name,
                '' AS project_name,
                '' AS employee_name,
                '' AS bank_account_name,
                '' AS card_name,
                '' AS team_name,
                CASE WHEN body.deleted_at IS NULL THEN 'ACTIVE' ELSE 'DELETED' END AS evidence_status,
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
                body.updated_at AS processed_at,
                body.created_at,
                body.updated_at,
                body.deleted_at,
                '' AS file_name,
                '' AS format_name
            FROM {$taxTable} body
            LEFT JOIN ledger_evidence_payloads p
                ON p.evidence_type COLLATE utf8mb4_general_ci = '{$taxEvidenceType}' COLLATE utf8mb4_general_ci
               AND p.evidence_id COLLATE utf8mb4_general_ci = body.id COLLATE utf8mb4_general_ci
               AND p.deleted_at IS NULL
            LEFT JOIN ledger_evidence_processing pr
                ON pr.evidence_type COLLATE utf8mb4_general_ci = '{$taxEvidenceType}' COLLATE utf8mb4_general_ci
               AND pr.evidence_id COLLATE utf8mb4_general_ci = body.id COLLATE utf8mb4_general_ci
               AND pr.deleted_at IS NULL
            LEFT JOIN ledger_evidence_links tx
                ON tx.evidence_type COLLATE utf8mb4_general_ci = '{$taxEvidenceType}' COLLATE utf8mb4_general_ci
               AND tx.evidence_id COLLATE utf8mb4_general_ci = body.id COLLATE utf8mb4_general_ci
               AND tx.target_type = 'TRANSACTION'
               AND tx.deleted_at IS NULL
            LEFT JOIN ledger_evidence_links vx
                ON vx.evidence_type COLLATE utf8mb4_general_ci = '{$taxEvidenceType}' COLLATE utf8mb4_general_ci
               AND vx.evidence_id COLLATE utf8mb4_general_ci = body.id COLLATE utf8mb4_general_ci
               AND vx.target_type = 'VOUCHER'
               AND vx.deleted_at IS NULL
            WHERE " . implode(' AND ', $where) . "
            ORDER BY body.sort_no ASC, body.updated_at DESC, body.created_at DESC
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    private function countTaxRowsFromBodyTable(string $importType, string $status, string $requestedId): int
    {
        $normalizedType = $this->normalizeDataType($importType);
        $isManualTaxInvoice = $normalizedType === 'TAX_INVOICE_MANUAL';
        $taxTable = $isManualTaxInvoice ? 'ledger_evidence_tax_invoice_manual' : 'ledger_evidence_tax_invoice';
        $taxEvidenceType = $isManualTaxInvoice ? 'TAX_INVOICE_MANUAL' : 'TAX_INVOICE';
        $where = [$status === 'DELETED' ? 'body.deleted_at IS NOT NULL' : 'body.deleted_at IS NULL'];
        $params = [];

        if ($requestedId !== '') {
            $where[] = 'body.id COLLATE utf8mb4_general_ci = :requested_id COLLATE utf8mb4_general_ci';
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

        $stmt = $this->pdo->prepare("
            SELECT COUNT(*)
            FROM {$taxTable} body
            LEFT JOIN ledger_evidence_processing pr
                ON pr.evidence_type COLLATE utf8mb4_general_ci = '{$taxEvidenceType}' COLLATE utf8mb4_general_ci
               AND pr.evidence_id COLLATE utf8mb4_general_ci = body.id COLLATE utf8mb4_general_ci
               AND pr.deleted_at IS NULL
            WHERE " . implode(' AND ', $where) . "
        ");
        $stmt->execute($params);

        return (int) $stmt->fetchColumn();
    }

    private function cashRowsFromBodyTable(string $importType, string $status, string $requestedId): array
    {
        $normalizedType = $this->normalizeDataType($importType);
        $cashEvidenceType = in_array($normalizedType, ['CASH_RECEIPT', 'CASH_RECEIPT_PURCHASE', 'CASH_RECEIPT_SALES'], true)
            ? $normalizedType
            : 'CASH_RECEIPT';
        $cashEvidenceTypes = $this->cashEvidenceTypesForBodyQuery($cashEvidenceType);
        $cashEvidenceTypeList = $this->sqlStringList($cashEvidenceTypes);
        $cashTable = 'ledger_evidence_cash_receipt';
        $cashSortNoExpr = $this->firstExistingColumnExpr($cashTable, 'body', ['sort_no'], '0');
        $cashEvidenceSortNoExpr = $this->firstExistingColumnExpr($cashTable, 'body', ['evidence_sort_no', 'sort_no'], '0');
        $cashSourceKeyExpr = $this->firstExistingColumnExpr($cashTable, 'body', ['external_key', 'source_key', 'approval_number'], "''");
        $cashEvidenceDateExpr = $this->firstExistingColumnExpr($cashTable, 'body', ['evidence_date', 'transaction_date', 'purchase_date', 'write_date', 'created_at'], 'NULL');
        $cashPurchaseDateTimeExpr = $this->firstExistingColumnExpr($cashTable, 'body', ['write_date', 'purchase_datetime', 'purchase_at', 'transaction_datetime', 'evidence_date', 'created_at'], 'NULL');
        $cashClientIdExpr = $this->firstExistingColumnExpr($cashTable, 'body', ['client_id'], "''");
        $cashProjectIdExpr = $this->firstExistingColumnExpr($cashTable, 'body', ['project_id'], "''");
        $cashClientNameExpr = $this->firstExistingColumnExpr($cashTable, 'body', ['raw_client_name', 'client_name', 'merchant_company_name'], "''");
        $cashMerchantNameExpr = $this->firstExistingColumnExpr($cashTable, 'body', ['merchant_company_name', 'raw_client_name', 'client_name'], "''");
        $cashUpdatedAtExpr = $this->firstExistingColumnExpr($cashTable, 'body', ['updated_at', 'created_at'], 'NULL');
        $cashCreatedAtExpr = $this->firstExistingColumnExpr($cashTable, 'body', ['created_at', 'updated_at'], 'NULL');
        $cashDeletedAtExpr = $this->firstExistingColumnExpr($cashTable, 'body', ['deleted_at'], 'NULL');
        $where = [$status === 'DELETED' ? 'body.deleted_at IS NOT NULL' : 'body.deleted_at IS NULL'];
        $params = [];

        if ($requestedId !== '') {
            $where[] = 'body.id COLLATE utf8mb4_general_ci = :requested_id COLLATE utf8mb4_general_ci';
            $params[':requested_id'] = $requestedId;
        }

        if ($this->columnExists('ledger_evidence_cash_receipt', 'source_type')) {
            $where[] = $this->cashBodySourceWhereSql($cashEvidenceType);
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

        $sql = "
            SELECT
                body.*,
                body.id AS id,
                'HOMETAX' AS source_type,
                COALESCE(p.evidence_type, '{$cashEvidenceType}') AS import_type,
                '' AS source_type_name,
                '' AS import_type_name,
                {$cashSortNoExpr} AS sort_no,
                {$cashEvidenceSortNoExpr} AS evidence_sort_no,
                NULL AS create_sort_no,
                NULL AS status_sort_no,
                0 AS row_no,
                COALESCE(p.format_id, '') AS format_id,
                COALESCE(p.raw_json, '') AS raw_json,
                COALESCE(p.mapped_payload_json, '') AS parsed_json,
                {$cashSourceKeyExpr} AS source_key,
                {$cashEvidenceDateExpr} AS evidence_date,
                {$cashEvidenceDateExpr} AS transaction_date,
                {$cashPurchaseDateTimeExpr} AS purchase_datetime,
                {$cashClientIdExpr} AS client_id,
                {$cashProjectIdExpr} AS project_id,
                '' AS employee_id,
                '' AS bank_account_id,
                '' AS card_id,
                '' AS team_id,
                COALESCE({$cashClientNameExpr}, {$cashMerchantNameExpr}, '') AS client_name,
                '' AS project_name,
                '' AS employee_name,
                '' AS bank_account_name,
                '' AS card_name,
                '' AS team_name,
                CASE WHEN {$cashDeletedAtExpr} IS NULL THEN 'ACTIVE' ELSE 'DELETED' END AS evidence_status,
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
                {$cashUpdatedAtExpr} AS processed_at,
                {$cashCreatedAtExpr} AS created_at,
                {$cashUpdatedAtExpr} AS updated_at,
                {$cashDeletedAtExpr} AS deleted_at,
                '' AS file_name,
                '' AS format_name
            FROM {$cashTable} body
            LEFT JOIN ledger_evidence_payloads p
                ON p.evidence_type COLLATE utf8mb4_general_ci IN ({$cashEvidenceTypeList})
               AND p.evidence_id COLLATE utf8mb4_general_ci = body.id COLLATE utf8mb4_general_ci
               AND p.deleted_at IS NULL
            LEFT JOIN ledger_evidence_processing pr
                ON pr.evidence_type COLLATE utf8mb4_general_ci IN ({$cashEvidenceTypeList})
               AND pr.evidence_id COLLATE utf8mb4_general_ci = body.id COLLATE utf8mb4_general_ci
               AND pr.deleted_at IS NULL
            LEFT JOIN ledger_evidence_links tx
                ON tx.evidence_type COLLATE utf8mb4_general_ci IN ({$cashEvidenceTypeList})
               AND tx.evidence_id COLLATE utf8mb4_general_ci = body.id COLLATE utf8mb4_general_ci
               AND tx.target_type = 'TRANSACTION'
               AND tx.deleted_at IS NULL
            LEFT JOIN ledger_evidence_links vx
                ON vx.evidence_type COLLATE utf8mb4_general_ci IN ({$cashEvidenceTypeList})
               AND vx.evidence_id COLLATE utf8mb4_general_ci = body.id COLLATE utf8mb4_general_ci
               AND vx.target_type = 'VOUCHER'
               AND vx.deleted_at IS NULL
            WHERE " . implode(' AND ', $where) . "
            ORDER BY sort_no ASC, updated_at DESC, created_at DESC
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    private function countCashRowsFromBodyTable(string $importType, string $status, string $requestedId): int
    {
        $normalizedType = $this->normalizeDataType($importType);
        $cashEvidenceType = in_array($normalizedType, ['CASH_RECEIPT', 'CASH_RECEIPT_PURCHASE', 'CASH_RECEIPT_SALES'], true)
            ? $normalizedType
            : 'CASH_RECEIPT';
        $cashEvidenceTypes = $this->cashEvidenceTypesForBodyQuery($cashEvidenceType);
        $cashEvidenceTypeList = $this->sqlStringList($cashEvidenceTypes);
        $where = [$status === 'DELETED' ? 'body.deleted_at IS NOT NULL' : 'body.deleted_at IS NULL'];
        $params = [];

        if ($requestedId !== '') {
            $where[] = 'body.id COLLATE utf8mb4_general_ci = :requested_id COLLATE utf8mb4_general_ci';
            $params[':requested_id'] = $requestedId;
        }

        if ($this->columnExists('ledger_evidence_cash_receipt', 'source_type')) {
            $where[] = $this->cashBodySourceWhereSql($cashEvidenceType);
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

        $stmt = $this->pdo->prepare("
            SELECT COUNT(DISTINCT body.id)
            FROM ledger_evidence_cash_receipt body
            LEFT JOIN ledger_evidence_payloads p
                ON p.evidence_type COLLATE utf8mb4_general_ci IN ({$cashEvidenceTypeList})
               AND p.evidence_id COLLATE utf8mb4_general_ci = body.id COLLATE utf8mb4_general_ci
               AND p.deleted_at IS NULL
            LEFT JOIN ledger_evidence_processing pr
                ON pr.evidence_type COLLATE utf8mb4_general_ci IN ({$cashEvidenceTypeList})
               AND pr.evidence_id COLLATE utf8mb4_general_ci = body.id COLLATE utf8mb4_general_ci
               AND pr.deleted_at IS NULL
            WHERE " . implode(' AND ', $where) . "
        ");
        $stmt->execute($params);

        return (int) $stmt->fetchColumn();
    }

    private function cashEvidenceTypesForBodyQuery(string $importType): array
    {
        return match ($this->normalizeDataType($importType)) {
            'CASH_RECEIPT_SALES' => ['CASH_RECEIPT_SALES'],
            'CASH_RECEIPT_PURCHASE' => ['CASH_RECEIPT_PURCHASE', 'CASH_RECEIPT'],
            default => ['CASH_RECEIPT', 'CASH_RECEIPT_PURCHASE'],
        };
    }

    private function cashBodySourceWhereSql(string $importType): string
    {
        if ($this->normalizeDataType($importType) === 'CASH_RECEIPT_SALES') {
            return "UPPER(COALESCE(body.source_type, '')) IN ('CASH_RECEIPT_SALES', 'SALES')";
        }

        return "(
            body.source_type IS NULL
            OR TRIM(body.source_type) = ''
            OR UPPER(body.source_type) NOT IN ('CASH_RECEIPT_SALES', 'SALES')
        )";
    }

    private function sqlStringList(array $values): string
    {
        $quoted = [];
        foreach ($values as $value) {
            $value = trim((string) $value);
            if ($value === '') {
                continue;
            }
            $quoted[] = $this->pdo->quote($value);
        }

        return $quoted !== [] ? implode(', ', $quoted) : "''";
    }

    private function payloadEvidenceTypeCounts(): array
    {
        $counts = [];

        if (!$this->tableExists('ledger_evidence_payloads')) {
            return $counts;
        }

        $stmt = $this->pdo->query("
            SELECT evidence_type, COUNT(*) AS row_count
            FROM ledger_evidence_payloads
            WHERE deleted_at IS NULL
            GROUP BY evidence_type
        ");

        foreach (($stmt->fetchAll(PDO::FETCH_ASSOC) ?: []) as $row) {
            $type = $this->normalizeDataType((string) ($row['evidence_type'] ?? ''));
            if ($type === '') {
                continue;
            }

            $counts[$type] = (int) ($counts[$type] ?? 0) + (int) ($row['row_count'] ?? 0);
        }

        return $counts;
    }

    private function bodyEvidenceTypeCounts(): array
    {
        $counts = [];

        if ($this->tableExists('ledger_evidence_bank_transaction')) {
            $counts['BANK_TRANSACTION'] = $this->simpleTableCount('ledger_evidence_bank_transaction');
        }

        if ($this->tableExists('ledger_evidence_tax_invoice')) {
            $counts['TAX_INVOICE'] = $this->simpleTableCount('ledger_evidence_tax_invoice');
        }

        if ($this->tableExists('ledger_evidence_tax_invoice_manual')) {
            $counts['TAX_INVOICE_MANUAL'] = $this->simpleTableCount('ledger_evidence_tax_invoice_manual');
        }

        if ($this->tableExists('ledger_evidence_cash_receipt')) {
            if ($this->columnExists('ledger_evidence_cash_receipt', 'source_type')) {
                $purchaseCount = $this->conditionalTableCount(
                    'ledger_evidence_cash_receipt',
                    "(
                        source_type IS NULL
                        OR TRIM(source_type) = ''
                        OR UPPER(source_type) NOT IN ('CASH_RECEIPT_SALES', 'SALES')
                    )"
                );
                $salesCount = $this->conditionalTableCount(
                    'ledger_evidence_cash_receipt',
                    "UPPER(COALESCE(source_type, '')) IN ('CASH_RECEIPT_SALES', 'SALES')"
                );
                $counts['CASH_RECEIPT'] = $purchaseCount;
                $counts['CASH_RECEIPT_PURCHASE'] = $purchaseCount;
                $counts['CASH_RECEIPT_SALES'] = $salesCount;
            } else {
                $count = $this->simpleTableCount('ledger_evidence_cash_receipt');
                $counts['CASH_RECEIPT'] = $count;
                $counts['CASH_RECEIPT_PURCHASE'] = $count;
            }
        }

        if ($this->tableExists('ledger_evidence_card_hometax')) {
            $count = $this->columnExists('ledger_evidence_card_hometax', 'source_type')
                ? $this->conditionalTableCount('ledger_evidence_card_hometax', "UPPER(COALESCE(source_type, 'CARD_HOMETAX')) = 'CARD_HOMETAX'")
                : $this->simpleTableCount('ledger_evidence_card_hometax');
            $counts['CARD_HOMETAX'] = $count;
        }

        if ($this->tableExists('ledger_evidence_card_statement')) {
            if ($this->columnExists('ledger_evidence_card_statement', 'source_type')) {
                $counts['CARD_STATEMENT'] = $this->conditionalTableCount(
                    'ledger_evidence_card_statement',
                    "UPPER(COALESCE(source_type, 'CARD_STATEMENT')) IN ('CARD_STATEMENT', 'CARD', 'CARD_PURCHASE', 'CARD_COMPANY', 'CREDIT_CARD')"
                );
                $counts['CARD_APPROVAL'] = $this->conditionalTableCount(
                    'ledger_evidence_card_statement',
                    "UPPER(COALESCE(source_type, '')) = 'CARD_APPROVAL'"
                );
            } else {
                $counts['CARD_STATEMENT'] = $this->simpleTableCount('ledger_evidence_card_statement');
            }
        }

        return $counts;
    }

    private function simpleTableCount(string $tableName): int
    {
        $stmt = $this->pdo->query("SELECT COUNT(*) FROM `{$tableName}` WHERE deleted_at IS NULL");
        return (int) $stmt->fetchColumn();
    }

    private function conditionalTableCount(string $tableName, string $conditionSql): int
    {
        $stmt = $this->pdo->query("SELECT COUNT(*) FROM `{$tableName}` WHERE deleted_at IS NULL AND {$conditionSql}");
        return (int) $stmt->fetchColumn();
    }

    private function logTrashListQueryDiagnostics(string $status, string $importType, string $whereSql, array $params, array $rows): void
    {
        if (strtoupper($status) !== 'DELETED') {
            return;
        }

        $payload = [
            'request_import_type' => $importType,
            'resolved_evidence_types' => $importType !== '' ? [$importType] : [],
            'list_table' => 'ledger_evidence_payloads',
            'where_sql' => $whereSql,
            'binding_params' => $params,
            'row_count' => count($rows),
            'id_sample' => array_slice(array_values(array_filter(array_map(
                static fn(array $row): string => (string) ($row['id'] ?? ''),
                $rows
            ))), 0, 5),
        ];
        error_log('[EvidenceGenerationService] trash_list_query=' . json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        $sampleStmt = $this->pdo->prepare("
            SELECT
                r.*,
                r.evidence_type AS debug_import_type,
                r.evidence_type AS debug_evidence_type,
                JSON_UNQUOTE(JSON_EXTRACT(r.mapped_payload_json, '$.data_type')) AS debug_data_type,
                JSON_UNQUOTE(JSON_EXTRACT(r.mapped_payload_json, '$.import_type')) AS debug_payload_import_type,
                JSON_UNQUOTE(JSON_EXTRACT(r.mapped_payload_json, '$.source_type')) AS debug_source_type,
                COALESCE(pr.processing_status, 'READY') AS debug_status,
                CASE WHEN r.deleted_at IS NULL THEN 0 ELSE 1 END AS debug_is_deleted
            FROM ledger_evidence_payloads r
            LEFT JOIN ledger_evidence_processing pr
                ON pr.evidence_type COLLATE utf8mb4_general_ci = r.evidence_type COLLATE utf8mb4_general_ci
               AND pr.evidence_id COLLATE utf8mb4_general_ci = r.evidence_id COLLATE utf8mb4_general_ci
               AND pr.deleted_at IS NULL
            WHERE {$whereSql}
            ORDER BY r.deleted_at DESC, r.updated_at DESC, r.created_at DESC
            LIMIT 1
        ");
        $sampleStmt->execute($params);
        $sampleRow = $sampleStmt->fetch(\PDO::FETCH_ASSOC) ?: null;
        error_log('[EvidenceGenerationService] trash_list_sample_row=' . json_encode([
            'list_table' => 'ledger_evidence_payloads',
            'sample_row' => $sampleRow,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
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

    private function mergeMappedPayloadIntoRow(array &$row, array $payload): void
    {
        foreach ($payload as $key => $value) {
            if (!is_string($key) || $key === '') {
                continue;
            }

            $current = $row[$key] ?? null;
            $currentText = is_scalar($current) ? trim((string) $current) : '';
            if ($current !== null && $currentText !== '') {
                continue;
            }

            $row[$key] = $value;
        }
    }
}
