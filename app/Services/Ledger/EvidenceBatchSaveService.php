<?php

namespace App\Services\Ledger;

use PDO;

class EvidenceBatchSaveService
{
    private const EVIDENCE_BODY_TABLES = [
        'ledger_evidence_bank_transaction',
        'ledger_evidence_tax_invoice',
        'ledger_evidence_cash_receipt',
        'ledger_evidence_card_hometax',
        'ledger_evidence_card_statement',
        'ledger_evidence_card_sales',
        'ledger_evidence_employee_expense',
        'ledger_evidence_payroll',
        'ledger_evidence_daily_worker',
        'ledger_evidence_business_income',
        'ledger_evidence_cash_sales',
    ];

    public function __construct(private PDO $pdo, private array $callbacks = [])
    {
    }

    public function createBatchCounters(): array
    {
        return [
            'new_count' => 0,
            'updated_count' => 0,
            'unchanged_count' => 0,
            'error_count' => 0,
            'protected_update_count' => 0,
            'protected_transaction_count' => 0,
            'protected_voucher_count' => 0,
        ];
    }

    public function commitUploadChunkIfNeeded(int $processedRows, int $chunkSize): void
    {
        if ($chunkSize < 1 || $processedRows % $chunkSize !== 0) {
            return;
        }

        if ($this->pdo->inTransaction()) {
            $this->pdo->commit();
        }
        $this->pdo->beginTransaction();
    }

    public function uploadStatusFromValidation(array $validation): string
    {
        $status = (string) ($validation['status'] ?? 'ok');
        if ($status === 'error') {
            return 'ERROR';
        }
        if ($status === 'warning') {
            return 'WARNING';
        }

        return 'VALID';
    }

    public function nextEvidenceJsonSortNo(string $key, string $sourceType = ''): int
    {
        $where = ['deleted_at IS NULL'];
        $params = [];
        $sourceType = self::normalizeDataType($sourceType);
        if ($sourceType !== '') {
            $where[] = 'evidence_type = :source_type';
            $params[':source_type'] = $sourceType;
        }

        $stmt = $this->pdo->prepare("
            SELECT mapped_payload_json
            FROM ledger_evidence_payloads
            WHERE " . implode(' AND ', $where)
        );
        $stmt->execute($params);

        $max = 0;
        while ($json = $stmt->fetchColumn()) {
            $payload = json_decode((string) $json, true);
            if (!is_array($payload)) {
                continue;
            }
            $value = $payload[$key] ?? 0;
            if (is_string($value)) {
                $value = str_replace(',', '', trim($value));
            }
            if (is_numeric($value)) {
                $max = max($max, (int) $value);
            }
        }

        if ($key === '_create_sort_no') {
            $max = max($max, $this->currentIssuedEvidenceSortNo());
        }

        return $max + 1;
    }

    private function currentIssuedEvidenceSortNo(): int
    {
        $max = 0;

        if ($this->tableExists('ledger_evidence_number_sequences')) {
            $stmt = $this->pdo->prepare("
                SELECT COALESCE(MAX(last_evidence_sort_no), 0)
                FROM ledger_evidence_number_sequences
                WHERE scope_code = 'EVIDENCE_GLOBAL'
            ");
            $stmt->execute();
            $max = max($max, (int) ($stmt->fetchColumn() ?: 0));
        }

        foreach (self::EVIDENCE_BODY_TABLES as $table) {
            if (!$this->tableExists($table)) {
                continue;
            }

            $stmt = $this->pdo->query("SELECT COALESCE(MAX(evidence_sort_no), 0) FROM `{$table}`");
            $max = max($max, (int) ($stmt->fetchColumn() ?: 0));
        }

        return $max;
    }

    private function tableExists(string $table): bool
    {
        static $cache = [];

        if (array_key_exists($table, $cache)) {
            return $cache[$table];
        }

        $stmt = $this->pdo->prepare("
            SELECT 1
            FROM information_schema.TABLES
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = :table_name
            LIMIT 1
        ");
        $stmt->execute([':table_name' => $table]);
        $cache[$table] = (bool) $stmt->fetchColumn();

        return $cache[$table];
    }

    public function assignEvidenceJsonSortNo(array &$payload, array $existingPayload, string $key, int &$nextSortNo): void
    {
        $existing = $existingPayload[$key] ?? null;
        if (is_string($existing)) {
            $existing = str_replace(',', '', trim($existing));
        }
        if (is_numeric($existing) && (int) $existing > 0) {
            $payload[$key] = (int) $existing;
            return;
        }

        $current = $payload[$key] ?? null;
        if (is_string($current)) {
            $current = str_replace(',', '', trim($current));
        }
        if (is_numeric($current) && (int) $current > 0) {
            $payload[$key] = (int) $current;
            return;
        }

        $payload[$key] = $nextSortNo++;
    }

    public function buildUploadRowState(array $row, string $dataType): array
    {
        $validation = is_array($row['_validation'] ?? null) ? $row['_validation'] : [];
        $status = $this->uploadStatusFromValidation($validation);
        $processStatus = $status === 'ERROR' ? 'ERROR' : 'READY';
        $messages = is_array($validation['messages'] ?? null) ? $validation['messages'] : [];

        $parsedPayload = $this->call('mappedPayloadForStorage', $row);
        if (isset($row['_row_no']) && !isset($parsedPayload['_upload_row_no'])) {
            $parsedPayload['_upload_row_no'] = (int) $row['_row_no'];
        }
        if (self::normalizeDataType($dataType) === 'BANK_TRANSACTION') {
            $parsedPayload = $this->call('normalizeBankTransactionPayload', $parsedPayload);
        }

        $voucherStatus = $this->call('uploadVoucherStatus', $dataType, $parsedPayload, $processStatus);
        $voucherErrorMessage = self::normalizeDataType($dataType) === 'BANK_TRANSACTION'
            ? $this->call('bankVoucherValidationMessage', $parsedPayload)
            : null;

        $sourceKey = $this->call('seedSourceKey', $parsedPayload, $dataType);
        $rawPayload = is_array($row['_raw_payload'] ?? null) ? $row['_raw_payload'] : [];
        if ($sourceKey === null) {
            $sourceKey = hash('sha256', $dataType . '|' . $this->call('jsonEncodeForStorage', $rawPayload !== [] ? $rawPayload : $parsedPayload));
        }

        $rawJson = $this->call('jsonEncodeForStorage', $rawPayload);
        $errorMessages = $messages;
        if ($voucherErrorMessage !== null) {
            $errorMessages[] = $voucherErrorMessage;
        }

        $requiredMissingMessages = is_array($validation['required_missing_messages'] ?? null)
            ? array_values(array_filter(array_map('strval', $validation['required_missing_messages'])))
            : [];
        $evidenceStatus = $this->call('evidenceStatusFromRequiredMissingMessages', $requiredMissingMessages);

        return [
            'validation' => $validation,
            'status' => $status,
            'process_status' => $processStatus,
            'parsed_payload' => $parsedPayload,
            'evidence_status' => $evidenceStatus,
            'voucher_status' => $voucherStatus,
            'source_key' => $sourceKey,
            'raw_json' => $rawJson,
            'error_message' => $errorMessages !== [] ? implode(', ', $errorMessages) : null,
        ];
    }

    public function findExistingUploadSeed(string $dataType, ?string $sourceKey, array $parsedPayload): ?array
    {
        $existingSeed = $sourceKey !== null ? $this->call('findExistingSeedRow', $dataType, $sourceKey) : null;
        if (!$existingSeed && $this->call('usesFingerprintSourceKey', $dataType)) {
            $existingSeed = $this->call('findExistingSeedRowByFingerprint', $dataType, $parsedPayload);
        }

        return is_array($existingSeed) ? $existingSeed : null;
    }

    public function existingMappedPayload(?array $existingSeed): array
    {
        $existingMappedPayload = json_decode((string) ($existingSeed['mapped_payload_json'] ?? ''), true);

        return is_array($existingMappedPayload) ? $existingMappedPayload : [];
    }

    public function isUnchangedExistingSeed(?array $existingSeed, string $rawJson, string $parsedJson): bool
    {
        return $existingSeed
            && (string) ($existingSeed['raw_json'] ?? '') === $rawJson
            && (string) ($existingSeed['mapped_payload_json'] ?? '') === $parsedJson;
    }

    public function protectedExistingSeedInfo(?array $existingSeed): array
    {
        if (!$existingSeed || !$this->call('isUploadProtectedExistingSeed', $existingSeed)) {
            return [
                'is_protected' => false,
                'has_transaction' => false,
                'has_voucher' => false,
            ];
        }

        return [
            'is_protected' => true,
            'has_transaction' => (bool) $this->call('existingSeedHasCreatedTransaction', $existingSeed),
            'has_voucher' => (bool) $this->call('existingSeedHasCreatedVoucher', $existingSeed),
        ];
    }

    public function buildPersistParams(
        string $evidenceId,
        string $dataType,
        string $formatId,
        ?string $sourceKey,
        array $parsedPayload,
        string $processStatus,
        string $evidenceStatus,
        string $voucherStatus,
        ?string $errorMessage,
        string $rawJson,
        string $parsedJson,
        string $actor
    ): array {
        return [
            ':id' => $evidenceId,
            ':source_type' => $dataType,
            ':source_key' => $sourceKey,
            ':format_id' => $formatId,
            ':evidence_date' => $this->call('dateValue', $parsedPayload['raw_transaction_datetime'] ?? $parsedPayload['evidence_date'] ?? $parsedPayload['transaction_date'] ?? $parsedPayload['issue_date'] ?? '') ?: null,
            ':client_id' => $this->call('businessRefIdForStorage', 'CLIENT', $parsedPayload),
            ':project_id' => $this->call('businessRefIdForStorage', 'PROJECT', $parsedPayload),
            ':employee_id' => $this->call('businessRefIdForStorage', 'EMPLOYEE', $parsedPayload),
            ':bank_account_id' => $this->call('businessRefIdForStorage', 'ACCOUNT', $parsedPayload),
            ':card_id' => $this->call('businessRefIdForStorage', 'CARD', $parsedPayload),
            ':client_name' => $this->call('businessRefNameForStorage', 'CLIENT', $parsedPayload),
            ':project_name' => $this->call('businessRefNameForStorage', 'PROJECT', $parsedPayload),
            ':employee_name' => $this->call('businessRefNameForStorage', 'EMPLOYEE', $parsedPayload),
            ':bank_account_name' => $this->call('businessRefNameForStorage', 'ACCOUNT', $parsedPayload),
            ':card_name' => $this->call('businessRefNameForStorage', 'CARD', $parsedPayload),
            ':currency' => (string) ($parsedPayload['currency'] ?? 'KRW'),
            ':supply_amount' => $this->call('number', $parsedPayload['supply_amount'] ?? null),
            ':vat_amount' => $this->call('number', $parsedPayload['vat_amount'] ?? null),
            ':total_amount' => $this->call('evidenceTotalAmountForStorage', $parsedPayload, $dataType),
            ':create_sort_no' => (int) ($parsedPayload['_create_sort_no'] ?? 0) ?: null,
            ':status_sort_no' => (int) ($parsedPayload['_status_sort_no'] ?? 0) ?: null,
            ':evidence_status' => strtoupper(trim($evidenceStatus)) !== ''
                ? strtoupper(trim($evidenceStatus))
                : 'COMPLETED',
            ':transaction_status' => $processStatus === 'ERROR' ? 'ERROR' : 'NONE',
            ':voucher_status' => $voucherStatus,
            ':error_message' => $errorMessage,
            ':raw_json' => $rawJson,
            ':mapped_payload_json' => $parsedJson,
            ':created_by' => $actor,
            ':updated_by' => $actor,
        ];
    }

    public function incrementUnchanged(array &$counters): void
    {
        $counters['unchanged_count']++;
    }

    public function incrementProtectedSkip(array &$counters, array $protectedSeedInfo): void
    {
        $counters['protected_update_count']++;
        if (!empty($protectedSeedInfo['has_transaction'])) {
            $counters['protected_transaction_count']++;
        }
        if (!empty($protectedSeedInfo['has_voucher'])) {
            $counters['protected_voucher_count']++;
        }
    }

    public function incrementPersisted(array &$counters, bool $isUpdate): void
    {
        if ($isUpdate) {
            $counters['updated_count']++;
            return;
        }

        $counters['new_count']++;
    }

    public function incrementErrorIfNeeded(array &$counters, string $processStatus): void
    {
        if ($processStatus === 'ERROR') {
            $counters['error_count']++;
        }
    }

    public function buildCachedSeed(
        string $evidenceId,
        ?string $sourceKey,
        string $rawJson,
        string $parsedJson,
        string $processStatus,
        string $evidenceStatus,
        string $voucherStatus
    ): ?array {
        if ($sourceKey === null || $sourceKey === '') {
            return null;
        }

        return [
            'id' => $evidenceId,
            'source_key' => $sourceKey,
            'raw_json' => $rawJson,
            'mapped_payload_json' => $parsedJson,
            'evidence_status' => strtoupper(trim((string) $evidenceStatus)) !== ''
                ? strtoupper(trim((string) $evidenceStatus))
                : 'COMPLETED',
            'transaction_status' => $processStatus === 'ERROR' ? 'ERROR' : 'NONE',
            'voucher_status' => $voucherStatus,
        ];
    }

    public function buildBatchResult(
        array $counters,
        string $batchId,
        string $fileName,
        string $dataType,
        string $formatId,
        int $totalRows
    ): array {
        $processedCount = (int) $counters['new_count']
            + (int) $counters['updated_count']
            + (int) $counters['unchanged_count']
            + (int) $counters['error_count'];

        return [
            'id' => $batchId,
            'file_name' => $fileName,
            'data_type' => $dataType,
            'format_id' => $formatId,
            'total_rows' => $totalRows,
            'processed_count' => $processedCount,
            'new_count' => (int) $counters['new_count'],
            'updated_count' => (int) $counters['updated_count'],
            'unchanged_count' => (int) $counters['unchanged_count'],
            'error_count' => (int) $counters['error_count'],
            'skipped_count' => max(0, $totalRows - $processedCount),
            'protected_update_count' => (int) $counters['protected_update_count'],
            'protected_transaction_count' => (int) $counters['protected_transaction_count'],
            'protected_voucher_count' => (int) $counters['protected_voucher_count'],
        ];
    }

    private function call(string $name, mixed ...$args): mixed
    {
        $callback = $this->callbacks[$name] ?? null;
        if (!is_callable($callback)) {
            throw new \RuntimeException('Missing EvidenceBatchSaveService callback: ' . $name);
        }

        return $callback(...$args);
    }

    private static function normalizeDataType(string $type): string
    {
        $type = strtoupper(trim($type));
        if ($type === 'CARD') {
            return 'CARD_APPROVAL';
        }

        return $type;
    }
}
