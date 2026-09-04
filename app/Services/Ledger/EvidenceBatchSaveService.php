<?php

namespace App\Services\Ledger;

use Core\Helpers\SequenceHelper;
use PDO;

class EvidenceBatchSaveService
{
    public function __construct(private PDO $pdo, private array $callbacks = [])
    {}

    public function createBatchCounters(): array
    {
        return [
            'new_count' => 0,
            'duplicate_count' => 0,
            'deleted_duplicate_count' => 0,
            'conflict_count' => 0,
            'error_count' => 0,
            'details' => [],
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

    public function nextBodySortNo(string $table): int
    {
        return SequenceHelper::next($table, 'sort_no');
    }

    public function buildUploadRowState(
        array $row,
        string $dataType
    ): array
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

        $rawJson = $this->call('jsonEncodeForStorage', $rawPayload);
        $errorMessages = $messages;
        if ($voucherErrorMessage !== null) {
            $errorMessages[] = $voucherErrorMessage;
        }

        $requestedEvidenceStatus = strtoupper(trim((string) ($parsedPayload['evidence_status'] ?? '')));
        $evidenceStatus = in_array($requestedEvidenceStatus, ['COMPLETED', 'CORRECTION_REQUIRED'], true)
            ? $requestedEvidenceStatus
            : 'CORRECTION_REQUIRED';

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

    public function isUnchangedExistingSeed(?array $existingSeed, string $rawJson, string $parsedJson, string $evidenceStatus = ''): bool
    {
        return $existingSeed
            && (string) ($existingSeed['raw_json'] ?? '') === $rawJson
            && (string) ($existingSeed['mapped_payload_json'] ?? '') === $parsedJson
            && ($evidenceStatus === '' || strtoupper(trim((string) ($existingSeed['evidence_status'] ?? ''))) === strtoupper(trim($evidenceStatus)));
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
        int $sortNo,
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
            ':sort_no' => $sortNo > 0 ? $sortNo : null,
            ':evidence_status' => strtoupper(trim($evidenceStatus)) !== ''
                ? strtoupper(trim($evidenceStatus))
                : 'CORRECTION_REQUIRED',
            ':transaction_status' => $processStatus === 'ERROR' ? 'ERROR' : 'NONE',
            ':voucher_status' => $voucherStatus,
            ':error_message' => $errorMessage,
            ':raw_json' => $rawJson,
            ':mapped_payload_json' => $parsedJson,
            ':created_by' => $actor,
            ':updated_by' => $actor,
        ];
    }

    public function incrementDuplicate(
        array &$counters,
        array $row,
        string $reason,
        bool $deleted = false,
        bool $conflict = false
    ): void {
        $counters['duplicate_count']++;
        if ($deleted) $counters['deleted_duplicate_count']++;
        if ($conflict) $counters['conflict_count']++;
        $counters['details'][] = [
            'row_no' => (int) ($row['_row_no'] ?? $row['_upload_row_no'] ?? 0),
            'transaction_datetime' => (string) ($row['raw_transaction_datetime'] ?? $row['transaction_datetime'] ?? $row['transaction_at'] ?? ''),
            'transaction_direction' => (string) ($row['raw_transaction_type'] ?? $row['transaction_direction'] ?? $row['bank_direction'] ?? ''),
            'amount' => $row['raw_deposit_amount'] ?? $row['deposit_amount'] ?? $row['raw_withdraw_amount'] ?? $row['withdraw_amount'] ?? $row['total_amount'] ?? null,
            'description' => (string) ($row['raw_description'] ?? $row['description'] ?? ''),
            'result' => 'DUPLICATE_SKIPPED',
            'reason' => $reason,
        ];
    }
    public function incrementPersisted(array &$counters): void
    {
        $counters['new_count']++;
    }
    public function incrementError(array &$counters, array $row, string $reason): void
    {
        $counters['error_count']++;
        $counters['details'][] = [
            'row_no' => (int) ($row['_row_no'] ?? $row['_upload_row_no'] ?? 0),
            'transaction_datetime' => (string) ($row['raw_transaction_datetime'] ?? $row['transaction_datetime'] ?? $row['transaction_at'] ?? ''),
            'transaction_direction' => (string) ($row['raw_transaction_type'] ?? $row['transaction_direction'] ?? $row['bank_direction'] ?? ''),
            'amount' => $row['raw_deposit_amount'] ?? $row['deposit_amount'] ?? $row['raw_withdraw_amount'] ?? $row['withdraw_amount'] ?? $row['total_amount'] ?? null,
            'description' => (string) ($row['raw_description'] ?? $row['description'] ?? ''),
            'result' => 'ERROR',
            'reason' => $reason,
        ];
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
                : 'CORRECTION_REQUIRED',
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
        $inserted = (int) ($counters['new_count'] ?? 0);
        $duplicates = (int) ($counters['duplicate_count'] ?? 0);
        $errors = (int) ($counters['error_count'] ?? 0);
        return [
            'id' => $batchId,
            'file_name' => $fileName,
            'data_type' => $dataType,
            'format_id' => $formatId,
            'total_count' => $totalRows,
            'inserted_count' => $inserted,
            'duplicate_count' => $duplicates,
            'deleted_duplicate_count' => (int) ($counters['deleted_duplicate_count'] ?? 0),
            'conflict_count' => (int) ($counters['conflict_count'] ?? 0),
            'error_count' => $errors,
            'details' => array_values($counters['details'] ?? []),
            'total_rows' => $totalRows,
            'processed_count' => $totalRows,
            'new_count' => $inserted,
            'updated_count' => 0,
            'unchanged_count' => $duplicates,
            'skipped_count' => $duplicates,
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
