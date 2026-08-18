<?php

namespace App\Services\Ledger;

use App\Repositories\Ledger\EvidenceSourceRepository;
use Closure;
use Core\Helpers\ActorHelper;
use PDO;

class EvidenceGenerationService
{
    private array $query = [];

    private ?EvidenceBodyReadService $evidenceBodyReadService = null;
    private ?EvidenceSourceRepository $evidenceSourceRepository = null;
    private array $standardDateFieldCache = [];

    public function __construct(
        private PDO $pdo,
        private Closure $ensureEvidenceBusinessInfoColumns,
        private Closure $ensureEvidenceSortColumns,
        private Closure $isAllowedDataType,
        private Closure $normalizeDataType,
        private Closure $normalizeImportSourceType,
        private Closure $importTypesForSourceType,
        private Closure $queryDataTypes,
        private Closure $sourceTypeForDataType,
        private Closure $sourceTypeLabel,
        private Closure $importTypeLabel,
        private Closure $tableExists,
        private Closure $columns,
        private Closure $normalizeBankTransactionPayload,
        private Closure $normalizeEvidenceMappedPayloadForResponse,
        private Closure $mergeEvidenceBusinessInfoIntoPayload,
        private Closure $isUuid,
        private Closure $businessRefNameById,
        private Closure $applyReadinessToEvidenceRow,
        private Closure $evidencePayloadSortNo,
        private Closure $formatTransactionCreateError
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

    private function queryDataTypes(string $type): array
    {
        return ($this->queryDataTypes)($type);
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

    private function evidenceBodyReadService(): EvidenceBodyReadService
    {
        if ($this->evidenceBodyReadService === null) {
            $this->evidenceBodyReadService = new EvidenceBodyReadService(
                $this->pdo,
                new \App\Models\Ledger\EvidenceBodyStatusProjectionModel(),
                $this->normalizeDataType
            );
        }

        return $this->evidenceBodyReadService;
    }

    private function columns(string $formatId): array
    {
        return ($this->columns)($formatId);
    }

    private function standardDateField(string $importType): string
    {
        $importType = $this->normalizeDataType($importType);
        if ($importType === '') {
            return '';
        }
        if (!array_key_exists($importType, $this->standardDateFieldCache)) {
            $this->evidenceSourceRepository ??= new EvidenceSourceRepository($this->pdo);
            $this->standardDateFieldCache[$importType] = $this->evidenceSourceRepository->standardDateField($importType);
        }
        return $this->standardDateFieldCache[$importType];
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
        $this->ensureEvidenceSortColumns();
        if ((string) ($query['type_counts'] ?? '') === '1') {
            $bodyCounts = $this->evidenceBodyReadService()->bodyEvidenceTypeCounts();
            $knownTypes = array_values(array_unique(array_keys($bodyCounts)));
            sort($knownTypes);

            $countsByType = [];
            foreach ($knownTypes as $type) {
                $normalizedType = $this->normalizeDataType((string) $type);
                if ($normalizedType === '') {
                    continue;
                }

                if (!isset($countsByType[$normalizedType])) {
                    $countsByType[$normalizedType] = 0;
                }

                $countsByType[$normalizedType] += (int) ($bodyCounts[$type] ?? 0);
            }

            $data = [];
            foreach ($countsByType as $type => $count) {
                $data[] = [
                    'import_type' => $type,
                    'row_count' => $count,
                ];
            }

            return ['payload' => ['success' => true, 'data' => $data]];
        }
        $filters = $this->seedRowFiltersFromRequest();

        $requestedId = trim((string) ($query['id'] ?? ''));
        if ($importType !== '') {
        } elseif ($sourceType !== '') {
            $types = $this->importTypesForSourceType($sourceType);
            if ($types === []) {
                return ['payload' => ['success' => true, 'data' => []]];
            }
        }
        $isServerPaged = isset($query['draw']) || isset($query['start']) || isset($query['length']);
        $pageStart = max(0, (int) ($query['start'] ?? 0));
        $pageLength = (int) ($query['length'] ?? 0);
        if ($pageLength <= 0) {
            $pageLength = 100;
        }
        $pageLength = min($pageLength, 500);
        $recordsFiltered = null;
        $bodyTableTypes = $this->evidenceBodyReadService()->readyBodyImportTypes();
        $bodyQueryTypes = [];
        if ($importType !== '') {
            $bodyQueryTypes = in_array($importType, $bodyTableTypes, true) ? [$importType] : [];
        } elseif ($sourceType !== '') {
            $bodyQueryTypes = array_values(array_intersect($this->importTypesForSourceType($sourceType), $bodyTableTypes));
        } else {
            $bodyQueryTypes = $bodyTableTypes;
        }
        $metadataQueryTypes = array_values(array_unique(array_merge(...array_map(
            fn(string $type): array => $this->queryDataTypes($type),
            $bodyQueryTypes
        ))));
        if ($bodyQueryTypes === []) {
            return ['payload' => $isServerPaged
                ? [
                    'success' => true,
                    'draw' => (int) ($query['draw'] ?? 0),
                    'recordsTotal' => 0,
                    'recordsFiltered' => 0,
                    'data' => [],
                ]
                : ['success' => true, 'data' => []]];
        }
        $requestedOrderings = $this->requestedEvidenceRowOrderings();
        $requestedOrdering = $requestedOrderings[0] ?? [];
        $projectionFields = array_values(array_unique(array_filter(array_merge(
            array_map(static fn(array $filter): string => trim((string) ($filter['field'] ?? '')), $filters),
            [(string) ($requestedOrdering['field'] ?? '')]
        ))));
        $this->evidenceSourceRepository ??= new EvidenceSourceRepository($this->pdo);
        $page = $this->evidenceSourceRepository->pagedProjections([
            'import_types' => $metadataQueryTypes,
            'status' => in_array($status, ['READY', 'NOT_READY', 'REVIEW_REQUIRED', 'VERIFY_ONLY'], true) ? '' : $status,
            'id' => $requestedId,
            'filters' => $filters,
            'start' => $isServerPaged ? $pageStart : 0,
            'length' => $isServerPaged ? $pageLength : 5000,
            'order_field' => (string) ($requestedOrdering['field'] ?? ''),
            'order_direction' => (string) ($requestedOrdering['dir'] ?? 'asc'),
            'projection_fields' => $projectionFields,
        ]);
        $recordsTotal = (int) ($page['records_total'] ?? 0);
        $recordsFiltered = (int) ($page['records_filtered'] ?? 0);
        $rows = [];
        foreach ($page['projections'] ?? [] as $projection) {
            if (!is_array($projection) || !is_array($projection['body'] ?? null)) {
                continue;
            }
            $row = $projection['body'];
            $row['_evidence_links'] = is_array($projection['links'] ?? null) ? $projection['links'] : [];
            $rows[] = $row;
        }
        $this->logTrashListQueryDiagnostics($status, $importType, '', [], $rows);
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
            $row['source_type_name'] = array_key_exists('source_type_name', $row)
                ? (string) $row['source_type_name']
                : $this->sourceTypeLabel((string) ($row['source_type'] ?? ''));
            $row['import_type_name'] = array_key_exists('import_type_name', $row)
                ? (string) $row['import_type_name']
                : $this->importTypeLabel((string) ($row['import_type'] ?? $payloadDataType));
            $standardDateField = array_key_exists('standard_date_field', $row)
                ? (string) $row['standard_date_field']
                : $this->standardDateField((string) ($row['import_type'] ?? $payloadDataType));
            $row['standard_date_field'] = $standardDateField;
            $row['standard_date'] = $standardDateField !== ''
                ? ($mappedPayload[$standardDateField] ?? $row[$standardDateField] ?? null)
                : null;
            $this->evidenceSourceRepository ??= new EvidenceSourceRepository($this->pdo);
            $row['business_unit_name'] = array_key_exists('business_unit_name', $row)
                ? (string) $row['business_unit_name']
                : $this->evidenceSourceRepository->systemCodeName('BUSINESS_UNIT', (string) ($mappedPayload['business_unit'] ?? $row['business_unit'] ?? ''));
            $row['transaction_direction_name'] = array_key_exists('transaction_direction_name', $row)
                ? (string) $row['transaction_direction_name']
                : $this->evidenceSourceRepository->systemCodeName('TRANSACTION_DIRECTION', (string) ($mappedPayload['transaction_direction'] ?? $row['transaction_direction'] ?? ''));
            $row['operation_type_name'] = array_key_exists('operation_type_name', $row)
                ? (string) $row['operation_type_name']
                : $this->evidenceSourceRepository->systemCodeName('OPERATION_TYPE', (string) ($mappedPayload['operation_type'] ?? $row['operation_type'] ?? ''));
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
        $rows = ActorHelper::enrichActorNames($rows, [
            'created_by_name' => 'created_by',
            'updated_by_name' => 'updated_by',
            'deleted_by_name' => 'deleted_by',
        ]);
        $this->attachRequiredFormatColumnsToRows($rows);
        foreach ($rows as &$row) {
            $this->applyReadinessToEvidenceRow($row);
        }
        unset($row);
        if ($status === 'READY') {
            $rows = array_values(array_filter($rows, static fn(array $row): bool => ($row['readiness_status'] ?? '') === 'READY'));
        } elseif (in_array($status, ['NOT_READY', 'REVIEW_REQUIRED', 'VERIFY_ONLY'], true)) {
            $rows = array_values(array_filter($rows, static fn(array $row): bool => ($row['readiness_status'] ?? '') === $status));
        }
        foreach ($rows as $index => &$row) {
            $row['applied_sort_no'] = max(0, (int) ($row['sort_no'] ?? 0));
            $row['row_no'] = ($isServerPaged ? $pageStart : 0) + $index + 1;
            if (!empty($row['error_message'])) {
                $row['error_message'] = $this->formatTransactionCreateError(
                    (string) $row['error_message'],
                    is_array($row['mapped_payload']) ? $row['mapped_payload'] : [],
                    (int) ($row['row_no'] ?? 0)
                );
            }
        }
        unset($row);
        if ($isServerPaged) {
            $total = $recordsFiltered ?? count($rows);
            return ['payload' => [
                'success' => true,
                'draw' => (int) ($query['draw'] ?? 0),
                'recordsTotal' => $recordsTotal ?? $total,
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

    private function sortEvidenceRowsForResponse(array &$rows, string $importType, string $sortScope = ''): void
    {
        $normalizedType = $this->normalizeDataType($importType);
        $defaultSortKey = $this->evidenceSortKeyForScope($sortScope, $importType);
        $requestedOrder = $this->requestedEvidenceRowOrderings();

        if ($requestedOrder !== []) {
            usort($rows, function (array $a, array $b) use ($requestedOrder, $normalizedType, $defaultSortKey): int {
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

                return $this->compareDefaultEvidenceRows($a, $b, $normalizedType, $defaultSortKey);
            });
            return;
        }

        usort($rows, fn(array $a, array $b): int => $this->compareDefaultEvidenceRows($a, $b, $normalizedType, $defaultSortKey));
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
            $display = (string) ($value ?? '');
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

    private function compareDefaultEvidenceRows(array $a, array $b, string $normalizedType, string $sortKey = 'sort_no'): int
    {
        $aSort = max(0, (int) ($a[$sortKey] ?? 0));
        $bSort = max(0, (int) ($b[$sortKey] ?? 0));
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

    private function evidenceSortKeyForScope(string $sortScope, string $importType = ''): string
    {
        $scope = strtolower(trim($sortScope));
        if (in_array($scope, ['create', 'status'], true)) {
            return 'sort_no';
        }

        return 'sort_no';
    }

    private function evidenceSortColumnForScope(string $sortScope, string $importType = ''): string
    {
        return $this->evidenceSortKeyForScope($sortScope, $importType);
    }

    private function evidenceTypeSortValue(array $row, string $dataType): string
    {
        if ($dataType === 'BANK_TRANSACTION') {
            return $this->bankTransactionSortValue($row);
        }

        $payload = is_array($row['mapped_payload'] ?? null) ? $row['mapped_payload'] : [];
        $keys = match ($dataType) {
            'TAX_INVOICE' => ['write_date', 'written_date', 'transaction_date', 'evidence_date', 'issue_date'],
            'CASH_RECEIPT' => ['purchase_datetime', 'purchase_at', 'purchase_date', 'transaction_datetime', 'transaction_date', 'evidence_date'],
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

    private function logTrashListQueryDiagnostics(string $status, string $importType, string $whereSql, array $params, array $rows): void
    {
        if (strtoupper($status) !== 'DELETED') {
            return;
        }

        $payload = [
            'request_import_type' => $importType,
            'resolved_evidence_types' => $importType !== '' ? [$importType] : [],
            'list_table' => null,
            'where_sql' => $whereSql,
            'binding_params' => $params,
            'row_count' => count($rows),
            'id_sample' => array_slice(array_values(array_filter(array_map(
                static fn(array $row): string => (string) ($row['id'] ?? ''),
                $rows
            ))), 0, 5),
        ];
        error_log('[EvidenceGenerationService] trash_list_query=' . json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        error_log('[EvidenceGenerationService] trash_list_sample_row=' . json_encode([
            'list_table' => null,
            'sample_row' => null,
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
