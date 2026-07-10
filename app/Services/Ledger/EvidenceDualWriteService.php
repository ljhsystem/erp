<?php

namespace App\Services\Ledger;

use App\Models\Ledger\EvidenceBankModel;
use App\Models\Ledger\EvidenceCardHometaxModel;
use App\Models\Ledger\EvidenceCardPurchaseModel;
use App\Models\Ledger\EvidenceCashReceiptModel;
use App\Models\Ledger\EvidenceTaxInvoiceManualModel;
use App\Models\Ledger\EvidenceTaxInvoiceModel;
use Core\Helpers\SequenceHelper;
use PDO;

class EvidenceDualWriteService
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
    }

    public function syncByEvidenceId(string $evidenceId): array
    {
        $evidenceId = trim($evidenceId);
        if ($evidenceId === '' || !$this->tableExists('ledger_data_evidences')) {
            return [
                'dual_write_status' => 'failed',
                'dual_write_target_table' => null,
                'message' => 'evidence source not available',
            ];
        }

        $processingJoin = '';
        $transactionStatusSelect = "'READY'";
        $reviewStatusSelect = "'NORMAL'";
        $errorMessageSelect = 'NULL';
        if ($this->tableExists('ledger_evidence_processing')) {
            $processingJoin = "
            LEFT JOIN ledger_evidence_processing pr
                ON pr.evidence_type = e.source_type
               AND pr.evidence_id = e.id
               AND pr.deleted_at IS NULL";
            $transactionStatusSelect = "COALESCE(pr.processing_status, 'READY')";
            $reviewStatusSelect = "COALESCE(pr.review_status, 'NORMAL')";
            $errorMessageSelect = 'pr.last_error_message';
        }

        $stmt = $this->pdo->prepare("
            SELECT
                e.id,
                e.source_type,
                e.source_key,
                e.format_id,
                e.raw_json,
                e.mapped_payload_json,
                e.created_at,
                e.created_by,
                e.updated_at,
                e.updated_by,
                e.deleted_at,
                e.deleted_by,
                {$transactionStatusSelect} AS transaction_status,
                {$reviewStatusSelect} AS review_status,
                {$errorMessageSelect} AS error_message
            FROM ledger_data_evidences e
            {$processingJoin}
            WHERE e.id = :id
              AND e.deleted_at IS NULL
            LIMIT 1
        ");
        $stmt->execute([':id' => $evidenceId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        if (!is_array($row)) {
            return [
                'dual_write_status' => 'failed',
                'dual_write_target_table' => null,
                'message' => 'evidence source row not found',
            ];
        }

        $payload = $this->mappedPayload($row);
        $row['evidence_date'] = $payload['evidence_date']
            ?? $payload['transaction_date']
            ?? $payload['purchase_date']
            ?? $payload['approval_date']
            ?? $payload['issue_date']
            ?? null;
        $row['client_id'] = $payload['client_id'] ?? null;
        $row['project_id'] = $payload['project_id'] ?? null;
        $row['employee_id'] = $payload['employee_id'] ?? null;
        $row['bank_account_id'] = $payload['bank_account_id'] ?? null;
        $row['card_id'] = $payload['card_id'] ?? null;
        $row['client_name'] = $payload['client_name'] ?? ($payload['client_company_name'] ?? null);
        $row['project_name'] = $payload['project_name'] ?? null;
        $row['employee_name'] = $payload['employee_name'] ?? null;
        $row['bank_account_name'] = $payload['bank_account_name'] ?? null;
        $row['card_name'] = $payload['card_name'] ?? null;
        $row['currency'] = $payload['currency'] ?? ($payload['currency_code'] ?? 'KRW');
        $row['supply_amount'] = $payload['supply_amount'] ?? null;
        $row['vat_amount'] = $payload['vat_amount'] ?? null;
        $row['total_amount'] = $payload['total_amount'] ?? null;
        $row['evidence_status'] = $this->storedEvidenceStatusForSync((string) ($row['source_type'] ?? ''), $evidenceId);

        return $this->syncFromLegacyRow($row);
    }

    public function syncFromLegacyRow(array $legacy): array
    {
        $sourceType = strtoupper(trim((string) ($legacy['source_type'] ?? '')));
        if ($sourceType === '') {
            return [
                'dual_write_status' => 'failed',
                'dual_write_target_table' => null,
                'message' => 'source_type is empty',
            ];
        }

        $target = $this->syncTarget($sourceType);
        if ($target !== null) {
            $payload = $this->buildBodyPayloadForTable($legacy, $target['table'], $sourceType);
            return $this->persistAndVerify($target['table'], $payload, $target['writer']);
        }

        return [
            'dual_write_status' => 'failed',
            'dual_write_target_table' => null,
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
                'dual_write_status' => 'failed',
                'dual_write_target_table' => $targetTable,
                'message' => 'payload build failed',
            ];
            error_log('[EvidenceDualWriteService] failed target=' . $targetTable . ' reason=' . $result['message']);
            return $result;
        }

        if (!$this->tableExists($targetTable)) {
            $result = [
                'dual_write_status' => 'failed',
                'dual_write_target_table' => $targetTable,
                'message' => 'target table not exists',
            ];
            error_log('[EvidenceDualWriteService] failed target=' . $targetTable . ' reason=' . $result['message']);
            return $result;
        }

        $payload = $this->filterPayloadForExistingColumns($targetTable, $payload);
        if (!isset($payload['id']) || trim((string) $payload['id']) === '') {
            $result = [
                'dual_write_status' => 'failed',
                'dual_write_target_table' => $targetTable,
                'message' => 'filtered payload missing id',
            ];
            error_log('[EvidenceDualWriteService] failed target=' . $targetTable . ' reason=' . $result['message']);
            return $result;
        }

        $saved = (bool) $writer($payload);
        if (!$saved) {
            $result = [
                'dual_write_status' => 'failed',
                'dual_write_target_table' => $targetTable,
                'message' => 'upsert failed',
            ];
            error_log('[EvidenceDualWriteService] failed target=' . $targetTable . ' id=' . (string) ($payload['id'] ?? '') . ' reason=' . $result['message']);
            return $result;
        }

        $stmt = $this->pdo->prepare("SELECT id FROM `{$targetTable}` WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => (string) ($payload['id'] ?? '')]);
        $exists = (string) ($stmt->fetchColumn() ?: '') !== '';
        if (!$exists) {
            $result = [
                'dual_write_status' => 'failed',
                'dual_write_target_table' => $targetTable,
                'message' => 'post-save verify select not found',
            ];
            error_log('[EvidenceDualWriteService] failed target=' . $targetTable . ' id=' . (string) ($payload['id'] ?? '') . ' reason=' . $result['message']);
            return $result;
        }

        $result = [
            'dual_write_status' => 'success',
            'dual_write_target_table' => $targetTable,
            'message' => 'verified',
        ];
        error_log('[EvidenceDualWriteService] success target=' . $targetTable . ' id=' . (string) ($payload['id'] ?? ''));
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
        $payload['evidence_sort_no'] = $this->evidenceSortNo($legacy, $targetTable);
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
            'evidence_sort_no' => $this->evidenceSortNo($legacy, $targetTable),
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
        $stmt = $this->pdo->prepare("
            SELECT 1
            FROM information_schema.TABLES
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = :table_name
            LIMIT 1
        ");
        $stmt->execute([':table_name' => $table]);
        $this->tableExistsCache[$table] = (bool) $stmt->fetchColumn();
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

        $stmt = $this->pdo->prepare("
            SELECT COLUMN_NAME, DATA_TYPE, CHARACTER_MAXIMUM_LENGTH
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = :table_name
        ");
        $stmt->execute([':table_name' => $table]);
        $columns = [];
        foreach (($stmt->fetchAll(PDO::FETCH_ASSOC) ?: []) as $row) {
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

    private function evidenceSortNo(array $legacy, string $table): int
    {
        $existing = $this->existingRow($table, (string) ($legacy['id'] ?? ''));
        $existingEvidenceSortNo = (int) ($existing['evidence_sort_no'] ?? 0);
        if ($existingEvidenceSortNo > 0) {
            return $existingEvidenceSortNo;
        }

        $value = (int) ($legacy['evidence_sort_no'] ?? 0);
        if ($value > 0) {
            return $value;
        }
        return $this->sortNo($legacy, $table);
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

        $stmt = $this->pdo->prepare("
            SELECT *
            FROM `{$table}`
            WHERE id = :id
            LIMIT 1
        ");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

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
            'TAX_INVOICE', 'CASH_RECEIPT', 'CARD_HOMETAX' => 'HOMETAX',
            'CARD_STATEMENT', 'CARD_APPROVAL' => 'CARD_COMPANY',
            'SHOPPING_ORDER' => 'SHOPPING',
            'IMPORT_INVOICE' => 'TRADE',
            default => strtoupper(trim($value)),
        };
    }

    private function importTypeForBody(array $payload, string $fallbackSourceType): ?string
    {
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
