<?php

namespace App\Services\Ledger;

use App\Models\Ledger\EvidenceBankModel;
use App\Models\Ledger\EvidenceBodyStorageModel;
use App\Models\Ledger\EvidenceCardHometaxModel;
use App\Models\Ledger\EvidenceCardPurchaseModel;
use App\Models\Ledger\EvidenceCashReceiptModel;
use App\Models\Ledger\EvidenceTaxInvoiceManualModel;
use App\Models\Ledger\EvidenceTaxInvoiceModel;
use Core\Helpers\SequenceHelper;
use PDO;

class EvidenceBodyWriteService
{
    private const BANK_TABLE = 'ledger_evidence_bank_transaction';
    private const TAX_TABLE = 'ledger_evidence_tax_invoice';
    private const TAX_MANUAL_TABLE = 'ledger_evidence_tax_invoice_manual';
    private const CASH_TABLE = 'ledger_evidence_cash_receipt';
    private const CARD_HOMETAX_TABLE = 'ledger_evidence_card_hometax';
    private const CARD_STATEMENT_TABLE = 'ledger_evidence_card_statement';

    private PDO $pdo;
    private EvidenceBankModel $bankModel;
    private EvidenceTaxInvoiceModel $taxInvoiceModel;
    private EvidenceTaxInvoiceManualModel $taxInvoiceManualModel;
    private EvidenceCashReceiptModel $cashReceiptModel;
    private EvidenceCardHometaxModel $cardHometaxModel;
    private EvidenceCardPurchaseModel $cardPurchaseModel;
    private EvidenceBodyStorageModel $bodyStorageModel;
    private array $tableExistsCache = [];
    private array $tableColumnsCache = [];
    private array $existingRowCache = [];
    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->bankModel = new EvidenceBankModel($pdo);
        $this->taxInvoiceModel = new EvidenceTaxInvoiceModel($pdo);
        $this->taxInvoiceManualModel = new EvidenceTaxInvoiceManualModel($pdo);
        $this->cashReceiptModel = new EvidenceCashReceiptModel($pdo);
        $this->cardHometaxModel = new EvidenceCardHometaxModel($pdo);
        $this->cardPurchaseModel = new EvidenceCardPurchaseModel($pdo);
        $this->bodyStorageModel = new EvidenceBodyStorageModel($pdo);
    }

    public function save(array $evidence): array
    {
        $sourceType = strtoupper(trim((string) ($evidence['source_type'] ?? '')));
        if ($sourceType === '') {
            return [
                'status' => 'failed',
                'target_table' => null,
                'message' => 'source_type is empty',
            ];
        }

        $target = $this->syncTarget($sourceType);
        if ($target !== null) {
            $payload = $this->buildBodyPayloadForTable($evidence, $target['table'], $sourceType);
            return $this->persistAndVerify($target['table'], $payload, $target['writer']);
        }

        return [
            'status' => 'failed',
            'target_table' => null,
            'message' => 'source_type not mapped for phase1',
        ];
    }

    /**
     * @return array{table:string,writer:callable}|null
     */
    private function syncTarget(string $sourceType): ?array
    {
        if ($this->isBankSource($sourceType)) {
            return [
                'table' => self::BANK_TABLE,
                'writer' => fn(array $payload): bool => $this->bankModel->upsertById($payload),
            ];
        }

        if ($this->isTaxInvoiceSource($sourceType)) {
            return $sourceType === 'TAX_INVOICE_MANUAL'
                ? [
                    'table' => self::TAX_MANUAL_TABLE,
                    'writer' => fn(array $payload): bool => $this->taxInvoiceManualModel->upsertById($payload),
                ]
                : [
                    'table' => self::TAX_TABLE,
                    'writer' => fn(array $payload): bool => $this->taxInvoiceModel->upsertById($payload),
                ];
        }

        if ($this->isCashReceiptSource($sourceType)) {
            return [
                'table' => self::CASH_TABLE,
                'writer' => fn(array $payload): bool => $this->cashReceiptModel->upsertById($payload),
            ];
        }

        if ($sourceType === 'CARD_HOMETAX') {
            return [
                'table' => self::CARD_HOMETAX_TABLE,
                'writer' => fn(array $payload): bool => $this->cardHometaxModel->upsertById($payload),
            ];
        }

        if ($this->isCardPurchaseSource($sourceType)) {
            return [
                'table' => self::CARD_STATEMENT_TABLE,
                'writer' => fn(array $payload): bool => $this->cardPurchaseModel->upsertById($payload),
            ];
        }

        return null;
    }

    private function persistAndVerify(string $targetTable, ?array $payload, callable $writer): array
    {
        if ($payload === null) {
            $result = [
                'status' => 'failed',
                'target_table' => $targetTable,
                'message' => 'payload build failed',
            ];
            error_log('[EvidenceBodyWriteService] failed target=' . $targetTable . ' reason=' . $result['message']);
            return $result;
        }

        if (!$this->tableExists($targetTable)) {
            $result = [
                'status' => 'failed',
                'target_table' => $targetTable,
                'message' => 'target table not exists',
            ];
            error_log('[EvidenceBodyWriteService] failed target=' . $targetTable . ' reason=' . $result['message']);
            return $result;
        }

        $payload = $this->filterPayloadForExistingColumns($targetTable, $payload);
        if (!isset($payload['id']) || trim((string) $payload['id']) === '') {
            $result = [
                'status' => 'failed',
                'target_table' => $targetTable,
                'message' => 'filtered payload missing id',
            ];
            error_log('[EvidenceBodyWriteService] failed target=' . $targetTable . ' reason=' . $result['message']);
            return $result;
        }

        $saved = (bool) $writer($payload);
        if (!$saved) {
            $result = [
                'status' => 'failed',
                'target_table' => $targetTable,
                'message' => 'upsert failed',
            ];
            error_log('[EvidenceBodyWriteService] failed target=' . $targetTable . ' id=' . (string) ($payload['id'] ?? '') . ' reason=' . $result['message']);
            return $result;
        }

        $exists = $this->bodyStorageModel->existsById($targetTable, (string) ($payload['id'] ?? ''));
        if (!$exists) {
            $result = [
                'status' => 'failed',
                'target_table' => $targetTable,
                'message' => 'post-save verify select not found',
            ];
            error_log('[EvidenceBodyWriteService] failed target=' . $targetTable . ' id=' . (string) ($payload['id'] ?? '') . ' reason=' . $result['message']);
            return $result;
        }

        $result = [
            'status' => 'success',
            'target_table' => $targetTable,
            'message' => 'verified',
        ];
        error_log('[EvidenceBodyWriteService] success target=' . $targetTable . ' id=' . (string) ($payload['id'] ?? ''));
        return $result;
    }

    private function buildBodyPayloadForTable(array $legacy, string $targetTable, string $sourceType): ?array
    {
        $id = trim((string) ($legacy['id'] ?? ''));
        if ($id === '') {
            return null;
        }

        $currentPayload = $this->mappedPayload($legacy);
        $existing = $this->existingRow($targetTable, $id);
        $columns = $this->tableColumns($targetTable);
        if ($columns === []) {
            return null;
        }

        $resolvedSourceType = $sourceType !== ''
            ? $sourceType
            : strtoupper(trim((string) ($currentPayload['source_type'] ?? $existing['source_type'] ?? '')));

        $payload = [];
        foreach ($columns as $column => $meta) {
            $payload[$column] = $this->resolveBodyColumnValue(
                $column,
                $meta,
                $legacy,
                $currentPayload,
                $existing,
                $targetTable,
                $resolvedSourceType
            );
        }

        $payload['id'] = $id;
        $payload['sort_no'] = $this->sortNo($legacy, $targetTable);
        $payload['source_type'] = $this->sourceTypeForBody($resolvedSourceType);
        $payload['import_type'] = $this->importTypeForBody($currentPayload, $resolvedSourceType);
        $payload['evidence_status'] = $this->evidenceStatus($legacy);
        $payload['external_key'] = $this->firstNonMissingValue(
            ['external_key', 'source_key'],
            $currentPayload,
            $legacy,
            $existing
        );

        return $this->withAuditColumns($payload, $legacy);
    }

    private function resolveBodyColumnValue(
        string $column,
        array $columnMeta,
        array $legacy,
        array $currentPayload,
        array $existing,
        string $targetTable,
        string $sourceType
    ): mixed {
        return match ($column) {
            'id' => (string) ($legacy['id'] ?? ''),
            'sort_no' => $this->sortNo($legacy, $targetTable),
            'source_type' => $this->sourceTypeForBody($sourceType),
            'import_type' => $this->importTypeForBody($currentPayload, $sourceType),
            'external_key' => $this->firstNonMissingValue(['external_key', 'source_key'], $currentPayload, $legacy, $existing),
            'evidence_status' => $this->evidenceStatus($legacy),
            default => $this->normalizeValueForColumn(
                $this->firstNonMissingValue(
                    array_merge([$column], $this->columnAliases($column)),
                    $currentPayload,
                    $legacy,
                    $existing
                ),
                $columnMeta
            ),
        };
    }

    /**
     * @return list<string>
     */
    private function columnAliases(string $column): array
    {
        return match ($column) {
            'external_key' => ['source_key'],
            'raw_client_name' => ['client_name', 'client_company_name'],
            'raw_card_name' => ['card_name', 'source_card_company_name'],
            'raw_purchase_datetime' => ['purchase_datetime', 'write_date'],
            'raw_description' => ['description'],
            'raw_memo' => ['memo'],
            'raw_counterparty_account_number' => ['counterparty_account_number', 'counterparty_account_no', 'account_number'],
            'raw_counterparty_bank_name' => ['counterparty_bank_name', 'counterparty_bank', 'bank_name'],
            'raw_cms_code' => ['bank_reference_no'],
            default => [],
        };
    }

    private function firstNonMissingValue(array $keys, array ...$sources): mixed
    {
        foreach ($sources as $source) {
            foreach ($keys as $key) {
                if (!array_key_exists($key, $source)) {
                    continue;
                }

                return $source[$key];
            }
        }

        return null;
    }

    private function mappedPayload(array $legacy): array
    {
        if (isset($legacy['current_payload']) && is_array($legacy['current_payload'])) {
            return $legacy['current_payload'];
        }

        $json = json_decode((string) ($legacy['mapped_payload_json'] ?? ''), true);
        return is_array($json) ? $json : [];
    }

    private function withAuditColumns(array $payload, array $legacy): array
    {
        $createdAt = $this->auditDateTime(
            $legacy['created_at'] ?? null,
            $legacy['updated_at'] ?? null,
            date('Y-m-d H:i:s')
        );
        $updatedAt = $this->auditDateTime(
            $legacy['updated_at'] ?? null,
            $legacy['created_at'] ?? null,
            $createdAt
        );
        $deletedAt = $this->dateTime($legacy['deleted_at'] ?? null);
        $createdBy = $this->auditActorValue(
            $legacy['created_by'] ?? null,
            $legacy['updated_by'] ?? null
        );
        $updatedBy = $this->auditActorValue(
            $legacy['updated_by'] ?? null,
            $legacy['created_by'] ?? null
        );
        $deletedBy = $deletedAt === null
            ? null
            : $this->auditActorValue($legacy['deleted_by'] ?? null, null);

        $payload['created_at'] = $createdAt;
        $payload['created_by'] = $createdBy;
        $payload['updated_at'] = $updatedAt;
        $payload['updated_by'] = $updatedBy;
        $payload['deleted_at'] = $deletedAt;
        $payload['deleted_by'] = $deletedBy;

        return $payload;
    }

    private function auditDateTime(mixed $primary, mixed $secondary = null, mixed $fallback = null): ?string
    {
        $normalized = $this->dateTime($primary);
        if ($normalized !== null) {
            return $normalized;
        }

        $normalized = $this->dateTime($secondary);
        if ($normalized !== null) {
            return $normalized;
        }

        return $this->dateTime($fallback);
    }

    private function auditActorValue(mixed $primary, mixed $secondary = null): ?string
    {
        $normalized = $this->actorColumnValue($primary);
        if ($normalized !== null && $normalized !== '') {
            return $normalized;
        }

        $normalized = $this->actorColumnValue($secondary);
        if ($normalized !== null && $normalized !== '') {
            return $normalized;
        }

        return null;
    }

    private function tableExists(string $table): bool
    {
        if (array_key_exists($table, $this->tableExistsCache)) {
            return $this->tableExistsCache[$table];
        }
        $this->tableExistsCache[$table] = $this->bodyStorageModel->tableExists($table);
        return $this->tableExistsCache[$table];
    }

    private function filterPayloadForExistingColumns(string $table, array $payload): array
    {
        $columns = $this->tableColumns($table);
        if ($columns === []) {
            return $payload;
        }

        $filtered = array_filter(
            $payload,
            static fn(string $column): bool => isset($columns[$column]),
            ARRAY_FILTER_USE_KEY
        );

        foreach ($filtered as $column => $value) {
            $filtered[$column] = $this->normalizeValueForColumn($value, $columns[$column] ?? []);
        }

        return $filtered;
    }

    private function tableColumns(string $table): array
    {
        if (array_key_exists($table, $this->tableColumnsCache)) {
            return $this->tableColumnsCache[$table];
        }

        $columns = [];
        foreach ($this->bodyStorageModel->columns($table) as $row) {
            $columnName = (string) ($row['COLUMN_NAME'] ?? '');
            if ($columnName === '') {
                continue;
            }
            $columns[$columnName] = [
                'data_type' => strtolower(trim((string) ($row['DATA_TYPE'] ?? ''))),
                'max_length' => isset($row['CHARACTER_MAXIMUM_LENGTH']) ? (int) $row['CHARACTER_MAXIMUM_LENGTH'] : null,
            ];
        }
        $this->tableColumnsCache[$table] = $columns;

        return $columns;
    }

    private function normalizeValueForColumn(mixed $value, array $columnMeta): mixed
    {
        $dataType = strtolower(trim((string) ($columnMeta['data_type'] ?? '')));
        if ($value === '') {
            return in_array($dataType, ['char', 'varchar', 'text', 'mediumtext', 'longtext', 'tinytext'], true) ? '' : null;
        }

        if ($value === null) {
            return null;
        }

        if (is_string($value)) {
            $maxLength = isset($columnMeta['max_length']) ? (int) $columnMeta['max_length'] : 0;
            if ($maxLength > 0 && strlen($value) > $maxLength) {
                $value = substr($value, 0, $maxLength);
            }
        }

        return match ($dataType) {
            'date' => $this->dateOnly($value),
            'datetime', 'timestamp' => $this->dateTime($value),
            'time' => $this->timeOnly($value),
            'tinyint', 'smallint', 'mediumint', 'int', 'bigint' => $this->integer($value),
            'decimal', 'numeric', 'float', 'double', 'real' => $this->decimal($value, 2),
            default => $value,
        };
    }

    private function sortNo(array $legacy, string $table): int
    {
        $existing = $this->existingRow($table, (string) ($legacy['id'] ?? ''));
        $existingSortNo = (int) ($existing['sort_no'] ?? 0);
        if ($existingSortNo > 0) {
            return $existingSortNo;
        }

        $value = (int) ($legacy['sort_no'] ?? 0);
        if ($value > 0) {
            return $value;
        }
        return SequenceHelper::next($table, 'sort_no');
    }

    private function existingRow(string $table, string $id): array
    {
        $id = trim($id);
        if ($id === '') {
            return [];
        }

        $cacheKey = $table . ':' . $id;
        if (array_key_exists($cacheKey, $this->existingRowCache)) {
            return $this->existingRowCache[$cacheKey];
        }

        if (!$this->tableExists($table)) {
            $this->existingRowCache[$cacheKey] = [];
            return [];
        }

        $row = $this->bodyStorageModel->findById($table, $id);

        $this->existingRowCache[$cacheKey] = is_array($row) ? $row : [];

        return $this->existingRowCache[$cacheKey];
    }

    private function evidenceStatus(array $legacy): string
    {
        $status = strtoupper(trim((string) ($legacy['evidence_status'] ?? '')));

        return match ($status) {
            'COMPLETED', 'READY', 'VERIFY_ONLY', '완료' => 'COMPLETED',
            'CORRECTION_REQUIRED', 'NOT_READY', 'REVIEW_REQUIRED', 'INVALID', 'ERROR', '보정필요' => 'CORRECTION_REQUIRED',
            default => 'CORRECTION_REQUIRED',
        };
    }

    private function storedEvidenceStatusForSync(string $sourceType, string $id): string
    {
        $id = trim($id);
        if ($id === '') {
            return 'CORRECTION_REQUIRED';
        }

        $table = match (true) {
            $this->isBankSource($sourceType) => self::BANK_TABLE,
            $this->isTaxInvoiceSource($sourceType) => $sourceType === 'TAX_INVOICE_MANUAL' ? self::TAX_MANUAL_TABLE : self::TAX_TABLE,
            $this->isCashReceiptSource($sourceType) => self::CASH_TABLE,
            $sourceType === 'CARD_HOMETAX' => self::CARD_HOMETAX_TABLE,
            $this->isCardPurchaseSource($sourceType) => self::CARD_STATEMENT_TABLE,
            default => '',
        };

        if ($table === '') {
            return 'CORRECTION_REQUIRED';
        }

        $existing = $this->existingRow($table, $id);
        $status = strtoupper(trim((string) ($existing['evidence_status'] ?? '')));

        return $status !== '' ? $this->evidenceStatus(['evidence_status' => $status]) : 'CORRECTION_REQUIRED';
    }

    private function firstValue(array $source, array $keys): mixed
    {
        foreach ($keys as $key) {
            if (!array_key_exists($key, $source)) {
                continue;
            }

            $value = $source[$key];
            if ($value === null) {
                continue;
            }

            if (is_string($value) && trim($value) === '') {
                continue;
            }

            return $value;
        }

        return null;
    }

    private function nullableString(mixed $value): ?string
    {
        $text = trim((string) ($value ?? ''));
        return $text === '' ? null : $text;
    }

    private function actorColumnValue(mixed $value): ?string
    {
        $text = $this->nullableString($value);
        if ($text === null) {
            return null;
        }

        if (preg_match('/^(?:USER|SYSTEM):([0-9a-f-]{36})$/i', $text, $matches) === 1) {
            return strtolower((string) $matches[1]);
        }

        return strlen($text) > 36 ? substr($text, 0, 36) : $text;
    }

    private function decimal(mixed $value, int $precision = 2): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        $normalized = str_replace(',', '', trim((string) $value));
        if (!is_numeric($normalized)) {
            return null;
        }
        return number_format((float) $normalized, $precision, '.', '');
    }

    private function integer(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        $normalized = str_replace(',', '', trim((string) $value));
        if (!is_numeric($normalized)) {
            return null;
        }

        return (int) round((float) $normalized);
    }

    private function dateOnly(mixed $value): ?string
    {
        $raw = trim((string) ($value ?? ''));
        if ($raw === '') {
            return null;
        }
        $ts = strtotime($raw);
        return $ts ? date('Y-m-d', $ts) : null;
    }

    private function dateTime(mixed $value): ?string
    {
        $raw = trim((string) ($value ?? ''));
        if ($raw === '') {
            return null;
        }
        $ts = strtotime($raw);
        return $ts ? date('Y-m-d H:i:s', $ts) : null;
    }

    private function timeOnly(mixed $value): ?string
    {
        $raw = trim((string) ($value ?? ''));
        if ($raw === '') {
            return null;
        }
        if (preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $raw) === 1) {
            return strlen($raw) === 5 ? ($raw . ':00') : $raw;
        }
        $ts = strtotime($raw);
        return $ts ? date('H:i:s', $ts) : null;
    }

    private function isBankSource(string $sourceType): bool
    {
        return in_array($sourceType, ['BANK_TRANSACTION', 'BANK'], true);
    }

    private function normalizeBodyImportType(string $value): string
    {
        $normalized = strtoupper(trim($value));

        return match ($normalized) {
            'BANK' => 'BANK_TRANSACTION',
            'HOMETAX' => 'TAX_INVOICE',
            'CARD_COMPANY', 'CARD', 'CARD_PURCHASE', 'CREDIT_CARD' => 'CARD_STATEMENT',
            'CASH_RECEIPT_PURCHASE', 'CASH_RECEIPT_PURCHAS', 'CASH_RECEIPT_BUY',
            'CASH_RECEIPT_SALES', 'CASH_RECEIPT_SALE', 'CASH_RECEIPT_SELL' => 'CASH_RECEIPT',
            default => $normalized,
        };
    }

    private function sourceTypeForBody(string $value): string
    {
        $importType = $this->normalizeBodyImportType($value);

        return match ($importType) {
            'BANK_TRANSACTION' => 'BANK',
            'TAX_INVOICE_MANUAL' => 'MANUAL',
            'TAX_INVOICE', 'CASH_RECEIPT', 'CARD_HOMETAX' => 'HOMETAX',
            'CARD_STATEMENT', 'CARD_APPROVAL' => 'CARD_COMPANY',
            'SHOPPING_ORDER' => 'SHOPPING',
            'IMPORT_INVOICE' => 'TRADE',
            default => strtoupper(trim($value)),
        };
    }

    private function importTypeForBody(array $payload, string $fallbackSourceType): ?string
    {
        $canonicalFallback = $this->normalizeBodyImportType($fallbackSourceType);
        if ($canonicalFallback === 'TAX_INVOICE_MANUAL') {
            return 'TAX_INVOICE_MANUAL';
        }

        $candidate = trim((string) ($payload['import_type'] ?? $payload['data_type'] ?? $fallbackSourceType));
        $normalized = $this->normalizeBodyImportType($candidate);

        return $normalized === '' ? null : $normalized;
    }

    private function isTaxInvoiceSource(string $sourceType): bool
    {
        return in_array($sourceType, ['TAX_INVOICE', 'TAX_INVOICE_MANUAL'], true);
    }

    private function isCashReceiptSource(string $sourceType): bool
    {
        return in_array($sourceType, ['CASH_RECEIPT', 'CASH_RECEIPT_PURCHASE', 'CASH_RECEIPT_SALES'], true);
    }

    private function isCardPurchaseSource(string $sourceType): bool
    {
        return in_array($sourceType, ['CARD', 'CARD_HOMETAX', 'CARD_STATEMENT', 'CARD_APPROVAL', 'CARD_PURCHASE', 'CARD_COMPANY', 'CREDIT_CARD'], true);
    }
}
