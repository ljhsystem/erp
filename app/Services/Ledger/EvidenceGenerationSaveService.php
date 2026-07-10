<?php

namespace App\Services\Ledger;

use Core\Helpers\ActorHelper;
use Core\Helpers\SequenceHelper;
use Core\Helpers\UuidHelper;
use PDO;

class EvidenceGenerationSaveService
{
    /** @var array<string,bool> */
    private array $tableExistsCache = [];
    /** @var array<string,array<string,array{data_type:string,max_length:int|null}>> */
    private array $tableColumnsCache = [];

    /**
     * @param array<string, callable> $callbacks
     */
    public function __construct(
        private PDO $pdo,
        private array $callbacks
    ) {
    }

    public function seedRowSave(array $payload): array
    {
        return $this->capture(fn() => $this->doSeedRowSave($payload));
    }

    public function evidenceCreate(array $payload): array
    {
        return $this->capture(fn() => $this->doEvidenceCreate($payload));
    }

    public function evidenceBulkSave(array $payload): array
    {
        return $this->capture(fn() => $this->doEvidenceBulkSave($payload));
    }

    private function capture(callable $handler): array
    {
        try {
            $handler();
        } catch (EvidenceGenerationSaveResponse $response) {
            return ['payload' => $response->payload, 'status' => $response->status];
        }

        return ['payload' => ['success' => false, 'message' => '응답이 생성되지 않았습니다.'], 'status' => 500];
    }

    private function json(array $payload, int $status = 200): void
    {
        throw new EvidenceGenerationSaveResponse($payload, $status);
    }

    public function __call(string $name, array $arguments): mixed
    {
        if (!isset($this->callbacks[$name])) {
            throw new \BadMethodCallException('Undefined helper callback: ' . $name);
        }

        return ($this->callbacks[$name])(...$arguments);
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

    private function hasEvidenceProcessingTable(): bool
    {
        return $this->tableExists('ledger_evidence_processing');
    }

    private function hasEvidencePayloadTable(): bool
    {
        return $this->tableExists('ledger_evidence_payloads');
    }

    private function evidenceProcessingJoin(string $payloadAlias = 'p', string $processingAlias = 'pr'): string
    {
        if (!$this->hasEvidenceProcessingTable()) {
            return '';
        }

        return "
             LEFT JOIN ledger_evidence_processing {$processingAlias}
                ON {$processingAlias}.evidence_type COLLATE utf8mb4_unicode_ci = {$payloadAlias}.evidence_type COLLATE utf8mb4_unicode_ci
               AND {$processingAlias}.evidence_id COLLATE utf8mb4_unicode_ci = {$payloadAlias}.evidence_id COLLATE utf8mb4_unicode_ci
               AND {$processingAlias}.deleted_at IS NULL
        ";
    }

    private function evidenceProcessingStatusSelect(string $processingAlias = 'pr', string $default = 'READY'): string
    {
        if (!$this->hasEvidenceProcessingTable()) {
            return "'" . addslashes($default) . "'";
        }

        return "COALESCE({$processingAlias}.processing_status, '" . addslashes($default) . "')";
    }

    private function evidenceProcessingReviewStatusSelect(string $processingAlias = 'pr', string $default = 'NORMAL'): string
    {
        if (!$this->hasEvidenceProcessingTable()) {
            return "'" . addslashes($default) . "'";
        }

        return "COALESCE({$processingAlias}.review_status, '" . addslashes($default) . "')";
    }

    private function evidenceProcessingErrorMessageSelect(string $processingAlias = 'pr'): string
    {
        if (!$this->hasEvidenceProcessingTable()) {
            return 'NULL';
        }

        return "{$processingAlias}.last_error_message";
    }

    private function doSeedRowSave(array $payload): void
    {
        if (!$this->hasEvidencePayloadTable()) {
            $this->doSeedRowSaveFromBodyTables($payload);
            return;
        }

        $seedRowId = trim((string) ($payload['id'] ?? ''));
        $parsed = $payload['parsed_json'] ?? null;
        $raw = $payload['raw_json'] ?? null;
        if ($seedRowId === '' || !is_array($parsed)) {
            $this->json(['success' => false, 'message' => json_decode('"\uC218\uC815\uD560 Seed \uD589\uACFC \uC785\uB825\uAC12\uC774 \uD544\uC694\uD569\uB2C8\uB2E4."')], 400);
            return;
        }

        $stmt = $this->pdo->prepare("
            SELECT
                p.evidence_id AS id,
                p.evidence_type AS source_type,
                p.format_id,
                p.source_key,
                p.mapped_payload_json,
                p.raw_json,
                p.latest_imported_at,
                p.created_at,
                p.created_by,
                p.updated_at,
                p.updated_by,
                " . $this->evidenceProcessingStatusSelect('pr') . " AS transaction_status,
                " . $this->evidenceProcessingReviewStatusSelect('pr') . " AS review_status,
                " . $this->evidenceProcessingErrorMessageSelect('pr') . " AS error_message,
                tx.target_id AS transaction_id,
                vx.target_id AS voucher_id
             FROM ledger_evidence_payloads p
             " . $this->evidenceProcessingJoin('p', 'pr') . "
             LEFT JOIN ledger_evidence_links tx
                ON tx.evidence_type COLLATE utf8mb4_unicode_ci = p.evidence_type COLLATE utf8mb4_unicode_ci
               AND tx.evidence_id COLLATE utf8mb4_unicode_ci = p.evidence_id COLLATE utf8mb4_unicode_ci
                AND tx.target_type = 'TRANSACTION'
                AND tx.deleted_at IS NULL
             LEFT JOIN ledger_evidence_links vx
                ON vx.evidence_type COLLATE utf8mb4_unicode_ci = p.evidence_type COLLATE utf8mb4_unicode_ci
               AND vx.evidence_id COLLATE utf8mb4_unicode_ci = p.evidence_id COLLATE utf8mb4_unicode_ci
                AND vx.target_type = 'VOUCHER'
                AND vx.deleted_at IS NULL
            WHERE p.evidence_id COLLATE utf8mb4_unicode_ci = :id COLLATE utf8mb4_unicode_ci
              AND p.deleted_at IS NULL
            LIMIT 1
        ");
        $stmt->execute([':id' => $seedRowId]);
        $current = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        if (!$current) {
            $this->json(['success' => false, 'message' => json_decode('"Seed \uD589\uC744 \uCC3E\uC744 \uC218 \uC5C6\uC2B5\uB2C8\uB2E4."')], 404);
            return;
        }

        $transactionStatus = strtoupper(trim((string) ($current['transaction_status'] ?? 'NONE')));
        $transactionId = trim((string) ($current['transaction_id'] ?? ''));
        $voucherId = trim((string) ($current['voucher_id'] ?? ''));
        if (
            $transactionId !== ''
            || $voucherId !== ''
            || in_array($transactionStatus, ['CREATED', 'PROCESSED', 'DONE', 'COMPLETED', 'POSTED'], true)
        ) {
            $this->json(['success' => false, 'message' => json_decode('"\uAC70\uB798 \uB610\uB294 \uC804\uD45C \uC0DD\uC131\uC774 \uC644\uB8CC\uB41C \uC99D\uBE59\uC740 \uC218\uC815\uD560 \uC218 \uC5C6\uC2B5\uB2C8\uB2E4."')], 400);
            return;
        }

        $currentMapped = json_decode((string) ($current['mapped_payload_json'] ?? ''), true);
        $currentMapped = is_array($currentMapped) ? $currentMapped : [];
        $parsed = $this->mappedPayloadForStorage($parsed);
        $parsed['_column_display_name'] = is_array($payload['column_display_name'] ?? null) ? $payload['column_display_name'] : ($payload['column_display_name'] ?? []);
        $parsed['_column_requirement_policy'] = is_array($payload['column_requirement_policy'] ?? null) ? $payload['column_requirement_policy'] : ($payload['column_requirement_policy'] ?? []);
        if ($this->normalizeDataType((string) ($current['source_type'] ?? '')) === 'BANK_TRANSACTION') {
            $parsed = $this->normalizeBankTransactionPayload($parsed);
        }
        $businessProjectMessages = $this->businessProjectRuleMessages($parsed);
        if ($businessProjectMessages !== []) {
            $this->json(['success' => false, 'message' => implode(' ', $businessProjectMessages)], 400);
            return;
        }
        $format = $this->formatWithColumns(trim((string) ($payload['format_id'] ?? $current['format_id'] ?? '')));
        $missingMessages = $this->requiredFormatMissingMessages($parsed, is_array($format['columns'] ?? null) ? $format['columns'] : []);
        if ($missingMessages !== []) {
            $this->json(['success' => false, 'message' => json_decode('"\uD544\uC218 \uD56D\uBAA9\uC744 \uC785\uB825\uD574\uC57C \uC800\uC7A5\uD560 \uC218 \uC788\uC2B5\uB2C8\uB2E4. "') . implode(', ', $missingMessages)], 400);
            return;
        }
        unset($parsed['_column_display_name'], $parsed['_column_requirement_policy']);

        unset($parsed['_column_display_name'], $parsed['_column_requirement_policy']);
        ($this->callbacks['normalizeUploadAmountFields'])($parsed);
        $dataType = $this->normalizeDataType((string) ($current['source_type'] ?? ''));
        if ($this->shouldSyncTaxInvoiceEvidenceClients($dataType)) {
            $clientSync = $this->syncTaxInvoiceEvidenceClientsFromSource($parsed, $seedRowId, $dataType);
            if (trim((string) ($parsed['client_id'] ?? '')) === '' && trim((string) ($clientSync['primary_client_id'] ?? '')) !== '') {
                $parsed['client_id'] = (string) $clientSync['primary_client_id'];
            }
            if (trim((string) ($parsed['client_id'] ?? '')) !== '') {
                $clientName = $this->businessRefNameById('CLIENT', (string) $parsed['client_id']);
                if ($clientName !== null && $clientName !== '') {
                    $parsed['client_name'] = $clientName;
                }
            }
        }

        $evidenceDate = null;
        foreach (['transaction_date', 'evidence_date', 'purchase_datetime', 'purchase_date', 'approval_datetime', 'approval_date', 'write_date', 'written_date', 'issue_date'] as $dateKey) {
            $evidenceDate = $this->dateValueOrNull($parsed[$dateKey] ?? null);
            if ($evidenceDate !== null) {
                break;
            }
        }

        $actor = $this->actorForStorage();
        $encodedPayload = $this->jsonEncodeForStorage($parsed);
        $encodedRaw = is_array($raw) ? $this->jsonEncodeForStorage($raw) : (string) ($current['raw_json'] ?? '');
        $voucherErrorMessage = $dataType === 'BANK_TRANSACTION'
            ? $this->bankVoucherValidationMessage($parsed)
            : null;
        $processingStatus = $voucherErrorMessage === null || trim((string) $voucherErrorMessage) === ''
            ? 'READY'
            : 'REVIEW_REQUIRED';
        $evidenceStatus = $this->evidenceStatusFromRequiredMissingMessages($missingMessages);
        $voucherStatus = $this->uploadVoucherStatus((string) ($current['source_type'] ?? ''), $parsed, 'READY');

        $payloadUpdate = $this->pdo->prepare("
            UPDATE ledger_evidence_payloads
            SET mapped_payload_json = :parsed_json,
                raw_json = :raw_json,
                payload_hash = SHA2(COALESCE(:parsed_json_hash, ''), 256),
                format_id = :format_id,
                updated_at = NOW(),
                updated_by = :actor
            WHERE evidence_type = :source_type
              AND evidence_id = :id
              AND deleted_at IS NULL
        ");
        $processingUpsert = $this->hasEvidenceProcessingTable()
            ? $this->pdo->prepare("
                INSERT INTO ledger_evidence_processing
                    (id, evidence_type, evidence_id, processing_status, review_status, last_error_message, created_at, updated_at)
                VALUES
                    (:processing_id, :source_type, :id, :processing_status, :review_status, :error_message, NOW(), NOW())
                ON DUPLICATE KEY UPDATE
                    processing_status = VALUES(processing_status),
                    review_status = VALUES(review_status),
                    last_error_message = VALUES(last_error_message),
                    updated_at = NOW(),
                    deleted_at = NULL
            ")
            : null;

        $startedTransaction = !$this->pdo->inTransaction();
        try {
            if ($startedTransaction) {
                $this->pdo->beginTransaction();
            }

            $payloadUpdate->execute([
                ':id' => $seedRowId,
                ':source_type' => (string) ($current['source_type'] ?? ''),
                ':parsed_json' => $encodedPayload,
                ':parsed_json_hash' => $encodedPayload,
                ':raw_json' => $encodedRaw,
                ':format_id' => trim((string) ($payload['format_id'] ?? $current['format_id'] ?? '')),
                ':actor' => $actor,
            ]);

            if ($processingUpsert instanceof \PDOStatement) {
                $processingUpsert->execute([
                    ':processing_id' => UuidHelper::generate(),
                    ':id' => $seedRowId,
                    ':source_type' => (string) ($current['source_type'] ?? ''),
                    ':processing_status' => $processingStatus,
                    ':review_status' => 'NORMAL',
                    ':error_message' => $voucherErrorMessage,
                ]);
            }

            $legacyForEvidence = [
                'id' => $seedRowId,
                'source_type' => (string) ($current['source_type'] ?? ''),
                'source_key' => (string) ($current['source_key'] ?? ''),
                'evidence_date' => $evidenceDate,
                'client_id' => $this->businessRefIdForStorage('CLIENT', $parsed),
                'project_id' => $this->businessRefIdForStorage('PROJECT', $parsed),
                'employee_id' => $this->businessRefIdForStorage('EMPLOYEE', $parsed),
                'bank_account_id' => $this->businessRefIdForStorage('ACCOUNT', $parsed),
                'card_id' => $this->businessRefIdForStorage('CARD', $parsed),
                'client_name' => $this->businessRefNameForStorage('CLIENT', $parsed),
                'project_name' => $this->businessRefNameForStorage('PROJECT', $parsed),
                'employee_name' => $this->businessRefNameForStorage('EMPLOYEE', $parsed),
                'bank_account_name' => $this->businessRefNameForStorage('ACCOUNT', $parsed),
                'card_name' => $this->businessRefNameForStorage('CARD', $parsed),
                'sort_no' => (int) ($current['sort_no'] ?? 0),
                'evidence_sort_no' => (int) ($current['evidence_sort_no'] ?? 0),
                'currency' => (string) ($parsed['currency'] ?? $parsed['currency_code'] ?? 'KRW'),
                'supply_amount' => $this->amountOrNull($parsed['supply_amount'] ?? null),
                'vat_amount' => $this->amountOrNull($parsed['vat_amount'] ?? null),
                'total_amount' => $this->amountOrNull($parsed['total_amount'] ?? null),
                'evidence_status' => $evidenceStatus,
                'transaction_status' => 'NONE',
                'voucher_status' => $voucherStatus,
                'error_message' => $voucherErrorMessage,
                'raw_json' => $encodedRaw,
                'mapped_payload_json' => $encodedPayload,
                'current_payload' => $parsed,
                'created_at' => $current['created_at'] ?? null,
                'created_by' => $current['created_by'] ?? null,
                'updated_at' => date('Y-m-d H:i:s'),
                'updated_by' => $actor,
                'deleted_at' => null,
                'deleted_by' => null,
            ];
            $dualWriteResult = (new \App\Services\Ledger\EvidenceDualWriteService($this->pdo))->syncFromLegacyRow($legacyForEvidence);
            if (($dualWriteResult['dual_write_status'] ?? '') !== 'success') {
                throw new \RuntimeException('evidence body update failed: ' . (string) ($dualWriteResult['message'] ?? 'unknown'));
            }
            if (!$this->bodyRowMatchesPayload($seedRowId, $dataType, $parsed)) {
                throw new \RuntimeException('evidence body verify failed: persisted values do not match request');
            }

            if ($startedTransaction && $this->pdo->inTransaction()) {
                $this->pdo->commit();
            }
        } catch (\Throwable $e) {
            if ($startedTransaction && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            error_log('[EvidenceGenerationSaveService::seedRowSave] ' . $e->getMessage());
            $this->json(['success' => false, 'message' => '수정 중 오류가 발생했습니다.'], 500);
            return;
        }

        $responseRow = [
            'id' => $seedRowId,
            'source_type' => $this->sourceTypeForDataType((string) ($current['source_type'] ?? '')),
            'import_type' => $this->normalizeDataType((string) ($current['source_type'] ?? '')),
            'format_id' => trim((string) ($payload['format_id'] ?? $current['format_id'] ?? '')),
            'evidence_date' => $evidenceDate,
            'client_id' => $legacyForEvidence['client_id'],
            'project_id' => $legacyForEvidence['project_id'],
            'employee_id' => $legacyForEvidence['employee_id'],
            'bank_account_id' => $legacyForEvidence['bank_account_id'],
            'card_id' => $legacyForEvidence['card_id'],
            'client_name' => $legacyForEvidence['client_name'],
            'project_name' => $legacyForEvidence['project_name'],
            'employee_name' => $legacyForEvidence['employee_name'],
            'bank_account_name' => $legacyForEvidence['bank_account_name'],
            'card_name' => $legacyForEvidence['card_name'],
            'sort_no' => (int) ($current['sort_no'] ?? 0),
            'evidence_sort_no' => (int) ($current['evidence_sort_no'] ?? 0),
            'raw_payload' => is_array($raw) ? $raw : [],
            'mapped_payload' => $this->normalizeEvidenceMappedPayloadForResponse($parsed),
            'evidence_status' => $evidenceStatus,
            'transaction_status' => 'NONE',
            'voucher_status' => $voucherStatus,
            'review_status' => 'NORMAL',
            'error_message' => $voucherErrorMessage,
            'process_status' => 'READY',
            'status' => 'READY',
        ];
        $this->json(['success' => true, 'message' => json_decode('"Seed Data\uAC00 \uC218\uC815\uB418\uC5C8\uC2B5\uB2C8\uB2E4."'), 'data' => $responseRow]);
    }

    private function doEvidenceCreate(array $payload): void
    {
        if (!$this->hasEvidencePayloadTable()) {
            $this->doEvidenceCreateFromBodyTables($payload);
            return;
        }

        $formatId = trim((string) ($payload['format_id'] ?? ''));
        $parsed = $payload['parsed_json'] ?? null;
        if ($formatId === '' || !is_array($parsed)) {
            $this->json(['success' => false, 'message' => '새 증빙을 생성할 양식과 입력값이 필요합니다.'], 400);
            return;
        }

        $format = $this->formatWithColumns($formatId);
        if (!$format) {
            $this->json(['success' => false, 'message' => '양식을 찾을 수 없습니다.'], 404);
            return;
        }

        $actor = $this->actorForStorage();
        $dataType = $this->normalizeDataType((string) ($format['data_type'] ?? ($payload['import_type'] ?? 'ETC')));
        $parsed = $this->mappedPayloadForStorage($parsed);
        $parsed['import_type'] = $parsed['import_type'] ?? $dataType;
        $parsed['data_type'] = $parsed['data_type'] ?? $dataType;
        $parsed['_column_display_name'] = is_array($payload['column_display_name'] ?? null) ? $payload['column_display_name'] : ($payload['column_display_name'] ?? []);
        $parsed['_column_requirement_policy'] = is_array($payload['column_requirement_policy'] ?? null) ? $payload['column_requirement_policy'] : ($payload['column_requirement_policy'] ?? []);
        if ($dataType === 'BANK_TRANSACTION') {
            $parsed = $this->normalizeBankTransactionPayload($parsed);
        }
        $businessProjectMessages = $this->businessProjectRuleMessages($parsed);
        if ($businessProjectMessages !== []) {
            $this->json(['success' => false, 'message' => implode(' ', $businessProjectMessages)], 400);
            return;
        }
        $missingMessages = $this->requiredFormatMissingMessages($parsed, is_array($format['columns'] ?? null) ? $format['columns'] : []);
        if ($missingMessages !== []) {
            $this->json(['success' => false, 'message' => '필수 항목을 입력해야 저장할 수 있습니다. ' . implode(', ', $missingMessages)], 400);
            return;
        }
        ($this->callbacks['normalizeUploadAmountFields'])($parsed);

        $evidenceId = UuidHelper::generate();
        $sourceKey = $this->seedSourceKey($parsed, $dataType);
        if ($sourceKey === null || $sourceKey === '') {
            $sourceKey = 'MANUAL-' . $evidenceId;
        }

        $raw = [];
        foreach ($this->formatColumnsInOrder($format['columns'] ?? []) as $column) {
            $index = (string) ($column['excel_column_index'] ?? $column['column_order'] ?? count($raw) + 1);
            $systemField = trim((string) ($column['system_field_name'] ?? ''));
            $columnName = trim((string) ($column['excel_column_name'] ?? $systemField ?? $index));
            $raw[$index] = [
                'column_index' => is_numeric($index) ? (int) $index : null,
                'column_name' => $columnName,
                'system_field_name' => $systemField,
                'is_required' => (int) ($column['is_required'] ?? 0),
                'is_reference_column' => (int) ($column['is_reference_column'] ?? 0),
                'value' => $systemField !== '' ? ($parsed[$systemField] ?? '') : ($parsed[$columnName] ?? ''),
            ];
        }

        $evidenceDate = null;
        foreach (['transaction_date', 'evidence_date', 'purchase_datetime', 'purchase_date', 'approval_datetime', 'approval_date', 'write_date', 'written_date', 'issue_date'] as $dateKey) {
            $evidenceDate = $this->dateValueOrNull($parsed[$dateKey] ?? null);
            if ($evidenceDate !== null) {
                break;
            }
        }
        $voucherStatus = $this->uploadVoucherStatus($dataType, $parsed, 'READY');
        $voucherErrorMessage = $dataType === 'BANK_TRANSACTION'
            ? $this->bankVoucherValidationMessage($parsed)
            : null;
        $this->ensureEvidenceBusinessInfoColumns();
        $this->ensureEvidenceSortColumns();
        $bodyTable = $this->bodyTableMap()[$dataType] ?? '';
        if ($bodyTable === '') {
            $this->json(['success' => false, 'message' => '증빙 저장 대상 테이블을 찾을 수 없습니다.'], 500);
            return;
        }
        $sortNo = SequenceHelper::next($bodyTable, 'sort_no');
        $evidenceSortNo = $this->nextGlobalEvidenceSortNo($actor);

        $rawJson = $this->jsonEncodeForStorage($raw);
        $mappedPayloadJson = $this->jsonEncodeForStorage($parsed);
        $processingStatus = $voucherErrorMessage === null || trim((string) $voucherErrorMessage) === ''
            ? 'READY'
            : 'REVIEW_REQUIRED';
        $evidenceStatus = $this->evidenceStatusFromRequiredMissingMessages($missingMessages);
        $timestamp = date('Y-m-d H:i:s');
        $legacyForEvidence = [
            'id' => $evidenceId,
            'source_type' => $dataType,
            'source_key' => $sourceKey,
            'format_id' => $formatId,
            'evidence_date' => $evidenceDate,
            'client_id' => $this->businessRefIdForStorage('CLIENT', $parsed),
            'project_id' => $this->businessRefIdForStorage('PROJECT', $parsed),
            'employee_id' => $this->businessRefIdForStorage('EMPLOYEE', $parsed),
            'bank_account_id' => $this->businessRefIdForStorage('ACCOUNT', $parsed),
            'card_id' => $this->businessRefIdForStorage('CARD', $parsed),
            'client_name' => $this->businessRefNameForStorage('CLIENT', $parsed),
            'project_name' => $this->businessRefNameForStorage('PROJECT', $parsed),
            'employee_name' => $this->businessRefNameForStorage('EMPLOYEE', $parsed),
            'bank_account_name' => $this->businessRefNameForStorage('ACCOUNT', $parsed),
            'card_name' => $this->businessRefNameForStorage('CARD', $parsed),
            'sort_no' => $sortNo,
            'evidence_sort_no' => $evidenceSortNo,
            'currency' => (string) ($parsed['currency'] ?? $parsed['currency_code'] ?? 'KRW'),
            'supply_amount' => $this->number($parsed['supply_amount'] ?? null),
            'vat_amount' => $this->number($parsed['vat_amount'] ?? null),
            'total_amount' => $this->evidenceTotalAmountForStorage($parsed, $dataType),
            'evidence_status' => $evidenceStatus,
            'transaction_status' => 'NONE',
            'voucher_status' => $voucherStatus,
            'error_message' => $voucherErrorMessage,
            'raw_json' => $rawJson,
            'mapped_payload_json' => $mappedPayloadJson,
            'created_at' => $timestamp,
            'created_by' => $actor,
            'updated_at' => $timestamp,
            'updated_by' => $actor,
            'deleted_at' => null,
            'deleted_by' => null,
        ];

        $startedTransaction = !$this->pdo->inTransaction();
        try {
            if ($startedTransaction) {
                $this->pdo->beginTransaction();
            }

            $this->pdo->prepare("
                INSERT INTO ledger_evidence_payloads
                    (id, evidence_type, evidence_id, source_key, format_id, raw_json, mapped_payload_json, payload_hash, latest_imported_at, created_at, updated_at, created_by, updated_by)
                VALUES
                    (:payload_id, :source_type, :id, :source_key, :format_id, :raw_json, :mapped_payload_json, SHA2(COALESCE(:mapped_payload_json_hash, ''), 256), NOW(), NOW(), NOW(), :created_by, :updated_by)
            ")->execute([
                ':payload_id' => UuidHelper::generate(),
                ':id' => $evidenceId,
                ':source_type' => $dataType,
                ':source_key' => $sourceKey,
                ':format_id' => $formatId,
                ':raw_json' => $rawJson,
                ':mapped_payload_json' => $mappedPayloadJson,
                ':mapped_payload_json_hash' => $mappedPayloadJson,
                ':created_by' => $actor,
                ':updated_by' => $actor,
            ]);

            if ($this->hasEvidenceProcessingTable()) {
                $this->pdo->prepare("
                    INSERT INTO ledger_evidence_processing
                        (id, evidence_type, evidence_id, processing_status, review_status, last_error_message, created_at, updated_at)
                    VALUES
                        (:processing_id, :source_type, :id, :processing_status, :review_status, :error_message, NOW(), NOW())
                ")->execute([
                    ':processing_id' => UuidHelper::generate(),
                    ':source_type' => $dataType,
                    ':id' => $evidenceId,
                    ':processing_status' => $processingStatus,
                    ':review_status' => 'NORMAL',
                    ':error_message' => $voucherErrorMessage,
                ]);
            }

            $dualWriteResult = (new \App\Services\Ledger\EvidenceDualWriteService($this->pdo))->syncFromLegacyRow($legacyForEvidence);
            if (($dualWriteResult['dual_write_status'] ?? '') !== 'success') {
                throw new \RuntimeException('evidence body update failed: ' . (string) ($dualWriteResult['message'] ?? 'unknown'));
            }

            if ($startedTransaction && $this->pdo->inTransaction()) {
                $this->pdo->commit();
            }
        } catch (\Throwable $e) {
            if ($startedTransaction && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            error_log('[EvidenceGenerationSaveService::evidenceCreate] ' . $e->getMessage());
            $this->json(['success' => false, 'message' => '저장 중 오류가 발생했습니다.'], 500);
            return;
        }

        $this->json(['success' => true, 'id' => $evidenceId, 'message' => '새 증빙원본이 생성되었습니다.']);
    }

    private function doEvidenceBulkSave(array $payload): void
    {
        if (!$this->hasEvidencePayloadTable()) {
            $this->doEvidenceBulkSaveFromBodyTables($payload);
            return;
        }

        $ids = $this->seedRowIdsFromPayload($payload);
        $patch = $payload['parsed_patch'] ?? [];
        $mode = strtolower(trim((string) ($payload['mode'] ?? 'fill_blank')));

        if ($ids === [] || !is_array($patch) || $patch === []) {
            $this->json(['success' => false, 'message' => '일괄보정 대상과 항목을 선택하세요.'], 400);
            return;
        }
        if (!in_array($mode, ['fill_blank', 'overwrite'], true)) {
            $mode = 'fill_blank';
        }

        [$inSql, $params] = $this->placeholdersForIds($ids, 'bulk_seed');
        $stmt = $this->pdo->prepare("
            SELECT
                p.evidence_id AS id,
                p.evidence_type AS source_type,
                p.source_key,
                p.mapped_payload_json,
                p.raw_json,
                p.latest_imported_at,
                p.created_at,
                p.created_by,
                p.updated_at,
                p.updated_by,
                " . $this->evidenceProcessingStatusSelect('pr') . " AS transaction_status,
                " . $this->evidenceProcessingReviewStatusSelect('pr') . " AS review_status,
                " . $this->evidenceProcessingErrorMessageSelect('pr') . " AS error_message,
                tx.target_id AS transaction_id,
                vx.target_id AS voucher_id
             FROM ledger_evidence_payloads p
             " . $this->evidenceProcessingJoin('p', 'pr') . "
             LEFT JOIN ledger_evidence_links tx
                ON tx.evidence_type COLLATE utf8mb4_unicode_ci = p.evidence_type COLLATE utf8mb4_unicode_ci
               AND tx.evidence_id COLLATE utf8mb4_unicode_ci = p.evidence_id COLLATE utf8mb4_unicode_ci
                AND tx.target_type = 'TRANSACTION'
                AND tx.deleted_at IS NULL
             LEFT JOIN ledger_evidence_links vx
                ON vx.evidence_type COLLATE utf8mb4_unicode_ci = p.evidence_type COLLATE utf8mb4_unicode_ci
               AND vx.evidence_id COLLATE utf8mb4_unicode_ci = p.evidence_id COLLATE utf8mb4_unicode_ci
                AND vx.target_type = 'VOUCHER'
                AND vx.deleted_at IS NULL
            WHERE p.evidence_id IN ({$inSql})
              AND p.deleted_at IS NULL
        ");
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $payloadUpdate = $this->pdo->prepare("
            UPDATE ledger_evidence_payloads
            SET mapped_payload_json = :parsed_json,
                payload_hash = SHA2(COALESCE(:parsed_json_hash, ''), 256),
                updated_at = NOW(),
                updated_by = :actor
            WHERE evidence_type = :source_type
              AND evidence_id = :id
              AND deleted_at IS NULL
        ");
        $processingUpsert = $this->hasEvidenceProcessingTable()
            ? $this->pdo->prepare("
                INSERT INTO ledger_evidence_processing
                    (id, evidence_type, evidence_id, processing_status, review_status, last_error_message, created_at, updated_at)
                VALUES
                    (:processing_id, :source_type, :id, :processing_status, :review_status, :error_message, NOW(), NOW())
                ON DUPLICATE KEY UPDATE
                    processing_status = VALUES(processing_status),
                    review_status = VALUES(review_status),
                    last_error_message = VALUES(last_error_message),
                    updated_at = NOW(),
                    deleted_at = NULL
            ")
            : null;

        $actor = $this->actorForStorage();
        $dualWrite = new \App\Services\Ledger\EvidenceDualWriteService($this->pdo);
        $updated = 0;
        $locked = 0;
        $unchanged = 0;

        $startedTransaction = !$this->pdo->inTransaction();
        try {
            if ($startedTransaction) {
                $this->pdo->beginTransaction();
            }

            foreach ($rows as $row) {
                $transactionStatus = strtoupper(trim((string) ($row['transaction_status'] ?? 'NONE')));
                $transactionId = trim((string) ($row['transaction_id'] ?? ''));
                $voucherId = trim((string) ($row['voucher_id'] ?? ''));
                if (
                    $transactionId !== ''
                    || $voucherId !== ''
                    || in_array($transactionStatus, ['CREATED', 'PROCESSED', 'DONE', 'COMPLETED', 'POSTED'], true)
                ) {
                    $locked++;
                    continue;
                }

                $mapped = json_decode((string) ($row['mapped_payload_json'] ?? ''), true);
                $mapped = is_array($mapped) ? $mapped : [];
                $next = $mapped;
                foreach ($patch as $key => $value) {
                    $field = trim((string) $key);
                    if ($field === '') {
                        continue;
                    }
                    if ($mode === 'fill_blank' && !$this->isBlankValue($next[$field] ?? null)) {
                        continue;
                    }
                    $next[$field] = is_scalar($value) || $value === null ? $value : '';
                }

                if ($next === $mapped) {
                    $unchanged++;
                    continue;
                }

                $next = $this->mappedPayloadForStorage($next);
                if ($this->normalizeDataType((string) ($row['source_type'] ?? '')) === 'BANK_TRANSACTION') {
                    $next = $this->normalizeBankTransactionPayload($next);
                }
                ($this->callbacks['normalizeUploadAmountFields'])($next);
                $sourceType = (string) ($row['source_type'] ?? '');
                $encodedPayload = $this->jsonEncodeForStorage($next);
                $voucherErrorMessage = $this->normalizeDataType($sourceType) === 'BANK_TRANSACTION'
                    ? $this->bankVoucherValidationMessage($next)
                    : null;
                $processingStatus = $voucherErrorMessage === null || trim((string) $voucherErrorMessage) === ''
                    ? 'READY'
                    : 'REVIEW_REQUIRED';
                $evidenceDate = $this->dateValueOrNull($next['evidence_date'] ?? $next['transaction_date'] ?? $next['purchase_date'] ?? $next['approval_date'] ?? $next['issue_date'] ?? null);
                $evidenceStatus = $this->businessEvidenceStatusForStorage();

                $payloadUpdate->execute([
                    ':id' => (string) $row['id'],
                    ':source_type' => $sourceType,
                    ':parsed_json' => $encodedPayload,
                    ':parsed_json_hash' => $encodedPayload,
                    ':actor' => $actor,
                ]);
                if ($payloadUpdate->rowCount() !== 1) {
                    throw new \RuntimeException('payload update failed: ' . (string) $row['id']);
                }

                if ($processingUpsert instanceof \PDOStatement) {
                    $processingUpsert->execute([
                        ':processing_id' => UuidHelper::generate(),
                        ':id' => (string) $row['id'],
                        ':source_type' => $sourceType,
                        ':processing_status' => $processingStatus,
                        ':review_status' => 'NORMAL',
                        ':error_message' => $voucherErrorMessage,
                    ]);
                }

                $legacyForEvidence = [
                    'id' => (string) $row['id'],
                    'source_type' => $sourceType,
                    'source_key' => (string) ($row['source_key'] ?? ''),
                    'evidence_date' => $evidenceDate,
                    'client_id' => $this->businessRefIdForStorage('CLIENT', $next),
                    'project_id' => $this->businessRefIdForStorage('PROJECT', $next),
                    'employee_id' => $this->businessRefIdForStorage('EMPLOYEE', $next),
                    'bank_account_id' => $this->businessRefIdForStorage('ACCOUNT', $next),
                    'card_id' => $this->businessRefIdForStorage('CARD', $next),
                    'client_name' => $this->businessRefNameForStorage('CLIENT', $next),
                    'project_name' => $this->businessRefNameForStorage('PROJECT', $next),
                    'employee_name' => $this->businessRefNameForStorage('EMPLOYEE', $next),
                    'bank_account_name' => $this->businessRefNameForStorage('ACCOUNT', $next),
                    'card_name' => $this->businessRefNameForStorage('CARD', $next),
                    'currency' => (string) ($next['currency'] ?? $next['currency_code'] ?? 'KRW'),
                    'supply_amount' => $this->amountOrNull($next['supply_amount'] ?? null),
                    'vat_amount' => $this->amountOrNull($next['vat_amount'] ?? null),
                    'total_amount' => $this->amountOrNull($next['total_amount'] ?? null),
                    'evidence_status' => $evidenceStatus,
                    'transaction_status' => 'NONE',
                    'voucher_status' => $this->uploadVoucherStatus($sourceType, $next, 'READY'),
                    'error_message' => $voucherErrorMessage,
                    'raw_json' => (string) ($row['raw_json'] ?? ''),
                    'mapped_payload_json' => $encodedPayload,
                    'created_at' => $row['created_at'] ?? null,
                    'created_by' => $row['created_by'] ?? null,
                    'updated_at' => date('Y-m-d H:i:s'),
                    'updated_by' => $actor,
                    'deleted_at' => null,
                    'deleted_by' => null,
                ];
                $dualWriteResult = $dualWrite->syncFromLegacyRow($legacyForEvidence);
                if (($dualWriteResult['dual_write_status'] ?? '') !== 'success') {
                    throw new \RuntimeException('evidence body update failed: ' . (string) ($dualWriteResult['message'] ?? 'unknown'));
                }

                $updated++;
            }

            if ($startedTransaction && $this->pdo->inTransaction()) {
                $this->pdo->commit();
            }
        } catch (\Throwable $e) {
            if ($startedTransaction && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            error_log('[EvidenceGenerationSaveService::evidenceBulkSave] ' . $e->getMessage());
            $this->json(['success' => false, 'message' => '수정 중 오류가 발생했습니다.'], 500);
            return;
        }

        $message = "일괄보정 완료: 변경 {$updated}건, 유지 {$unchanged}건";
        if ($locked > 0) {
            $message .= ", 생성완료 잠금 {$locked}건";
        }
        $this->json([
            'success' => true,
            'message' => $message,
            'updated_count' => $updated,
            'unchanged_count' => $unchanged,
            'locked_count' => $locked,
        ]);
    }

    private function doSeedRowSaveFromBodyTables(array $payload): void
    {
        $seedRowId = trim((string) ($payload['id'] ?? ''));
        $parsed = $payload['parsed_json'] ?? null;
        $raw = $payload['raw_json'] ?? null;
        if ($seedRowId === '' || !is_array($parsed)) {
            $this->json(['success' => false, 'message' => '수정할 증빙과 입력값이 필요합니다.'], 400);
            return;
        }

        [$current, $dataType] = $this->findEvidenceBodyRow($seedRowId, (string) ($payload['import_type'] ?? ($parsed['import_type'] ?? '')));
        if (!is_array($current) || $dataType === '') {
            $this->json(['success' => false, 'message' => '수정할 증빙을 찾을 수 없습니다.'], 404);
            return;
        }

        if ($this->isEvidenceLocked($seedRowId, $dataType)) {
            $this->json(['success' => false, 'message' => '거래 또는 전표 생성이 완료된 증빙은 수정할 수 없습니다.'], 400);
            return;
        }

        $basePayload = $this->bodyRowPayload($current, $dataType);
        $parsed = array_merge($basePayload, $parsed);
        $parsed['import_type'] = $parsed['import_type'] ?? $dataType;
        $parsed = $this->mappedPayloadForStorage($parsed);
        if ($dataType === 'BANK_TRANSACTION') {
            $parsed = $this->normalizeBankTransactionPayload($parsed);
        }

        $businessProjectMessages = $this->businessProjectRuleMessages($parsed);
        if ($businessProjectMessages !== []) {
            $this->json(['success' => false, 'message' => implode(' ', $businessProjectMessages)], 400);
            return;
        }

        $format = $this->formatWithColumns(trim((string) ($payload['format_id'] ?? '')));
        $missingMessages = $this->requiredFormatMissingMessages($parsed, is_array($format['columns'] ?? null) ? $format['columns'] : []);
        if ($missingMessages !== []) {
            $this->json(['success' => false, 'message' => '필수 항목을 입력해야 저장할 수 있습니다. ' . implode(', ', $missingMessages)], 400);
            return;
        }

        ($this->callbacks['normalizeUploadAmountFields'])($parsed);
        $actor = $this->actorForStorage();
        $legacyForEvidence = $this->legacyEvidenceForBodySync($seedRowId, $dataType, $current, $parsed, $actor, is_array($raw) ? $raw : null, $missingMessages);

        $startedTransaction = !$this->pdo->inTransaction();
        try {
            if ($startedTransaction) {
                $this->pdo->beginTransaction();
            }

            $dualWriteResult = (new \App\Services\Ledger\EvidenceDualWriteService($this->pdo))->syncFromLegacyRow($legacyForEvidence);
            if (($dualWriteResult['dual_write_status'] ?? '') !== 'success') {
                throw new \RuntimeException('evidence body update failed: ' . (string) ($dualWriteResult['message'] ?? 'unknown'));
            }

            if ($startedTransaction && $this->pdo->inTransaction()) {
                $this->pdo->commit();
            }
        } catch (\Throwable $e) {
            if ($startedTransaction && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            error_log('[EvidenceGenerationSaveService::seedRowSaveFromBodyTables] ' . $e->getMessage());
            $this->json(['success' => false, 'message' => '저장 중 오류가 발생했습니다.'], 500);
            return;
        }

        $this->json(['success' => true, 'id' => $seedRowId, 'message' => '증빙을 수정했습니다.']);
    }

    private function doEvidenceCreateFromBodyTables(array $payload): void
    {
        $parsed = $payload['parsed_json'] ?? null;
        if (!is_array($parsed)) {
            $this->json(['success' => false, 'message' => '생성할 증빙 입력값이 필요합니다.'], 400);
            return;
        }

        $dataType = $this->normalizeDataType((string) ($parsed['import_type'] ?? ($payload['import_type'] ?? '')));
        if ($dataType === '') {
            $this->json(['success' => false, 'message' => '증빙 유형이 필요합니다.'], 400);
            return;
        }

        $parsed['import_type'] = $parsed['import_type'] ?? $dataType;
        $parsed = $this->mappedPayloadForStorage($parsed);
        if ($dataType === 'BANK_TRANSACTION') {
            $parsed = $this->normalizeBankTransactionPayload($parsed);
        }

        $businessProjectMessages = $this->businessProjectRuleMessages($parsed);
        if ($businessProjectMessages !== []) {
            $this->json(['success' => false, 'message' => implode(' ', $businessProjectMessages)], 400);
            return;
        }

        $format = $this->formatWithColumns(trim((string) ($payload['format_id'] ?? '')));
        $missingMessages = $this->requiredFormatMissingMessages($parsed, is_array($format['columns'] ?? null) ? $format['columns'] : []);
        if ($missingMessages !== []) {
            $this->json(['success' => false, 'message' => '필수 항목을 입력해야 저장할 수 있습니다. ' . implode(', ', $missingMessages)], 400);
            return;
        }

        ($this->callbacks['normalizeUploadAmountFields'])($parsed);

        $evidenceId = UuidHelper::generate();
        $sourceKey = $this->seedSourceKey($parsed, $dataType);
        if ($sourceKey === null || $sourceKey === '') {
            $sourceKey = 'MANUAL-' . $evidenceId;
        }

        $actor = $this->actorForStorage();
        $timestamp = date('Y-m-d H:i:s');
        $legacyForEvidence = $this->legacyEvidenceForBodySync($evidenceId, $dataType, [
            'external_key' => $sourceKey,
            'created_at' => $timestamp,
            'created_by' => $actor,
            'updated_at' => $timestamp,
            'updated_by' => $actor,
            'deleted_at' => null,
            'deleted_by' => null,
        ], $parsed, $actor, null, $missingMessages);

        $startedTransaction = !$this->pdo->inTransaction();
        try {
            if ($startedTransaction) {
                $this->pdo->beginTransaction();
            }

            $dualWriteResult = (new \App\Services\Ledger\EvidenceDualWriteService($this->pdo))->syncFromLegacyRow($legacyForEvidence);
            if (($dualWriteResult['dual_write_status'] ?? '') !== 'success') {
                throw new \RuntimeException('evidence body create failed: ' . (string) ($dualWriteResult['message'] ?? 'unknown'));
            }
            if (!$this->bodyRowMatchesPayload($evidenceId, $dataType, $parsed)) {
                throw new \RuntimeException('evidence body create verify failed: persisted values do not match request');
            }

            if ($startedTransaction && $this->pdo->inTransaction()) {
                $this->pdo->commit();
            }
        } catch (\Throwable $e) {
            if ($startedTransaction && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            error_log('[EvidenceGenerationSaveService::evidenceCreateFromBodyTables] ' . $e->getMessage());
            $this->json(['success' => false, 'message' => '저장 중 오류가 발생했습니다.'], 500);
            return;
        }

        $this->json(['success' => true, 'id' => $evidenceId, 'message' => '증빙을 생성했습니다.']);
    }

    private function doEvidenceBulkSaveFromBodyTables(array $payload): void
    {
        $ids = $this->seedRowIdsFromPayload($payload);
        $patch = $payload['parsed_patch'] ?? [];
        $mode = strtolower(trim((string) ($payload['mode'] ?? 'fill_blank')));
        if ($ids === [] || !is_array($patch) || $patch === []) {
            $this->json(['success' => false, 'message' => '일괄 보정 대상과 항목을 선택해 주세요.'], 400);
            return;
        }
        if (!in_array($mode, ['fill_blank', 'overwrite'], true)) {
            $mode = 'fill_blank';
        }

        $actor = $this->actorForStorage();
        $dualWrite = new \App\Services\Ledger\EvidenceDualWriteService($this->pdo);
        $updated = 0;
        $locked = 0;
        $unchanged = 0;

        $startedTransaction = !$this->pdo->inTransaction();
        try {
            if ($startedTransaction) {
                $this->pdo->beginTransaction();
            }

            foreach ($ids as $id) {
                [$current, $dataType] = $this->findEvidenceBodyRow((string) $id, (string) ($payload['import_type'] ?? ''));
                if (!is_array($current) || $dataType === '') {
                    continue;
                }
                if ($this->isEvidenceLocked((string) $id, $dataType)) {
                    $locked++;
                    continue;
                }

                $mapped = $this->bodyRowPayload($current, $dataType);
                $next = $mapped;
                foreach ($patch as $key => $value) {
                    $field = trim((string) $key);
                    if ($field === '') {
                        continue;
                    }
                    if ($mode === 'fill_blank' && !$this->isBlankValue($next[$field] ?? null)) {
                        continue;
                    }
                    $next[$field] = is_scalar($value) || $value === null ? $value : '';
                }

                if ($next === $mapped) {
                    $unchanged++;
                    continue;
                }

                $next['import_type'] = $next['import_type'] ?? $dataType;
                $next = $this->mappedPayloadForStorage($next);
                if ($dataType === 'BANK_TRANSACTION') {
                    $next = $this->normalizeBankTransactionPayload($next);
                }
                ($this->callbacks['normalizeUploadAmountFields'])($next);

                $legacyForEvidence = $this->legacyEvidenceForBodySync((string) $id, $dataType, $current, $next, $actor, null, []);
                $dualWriteResult = $dualWrite->syncFromLegacyRow($legacyForEvidence);
                if (($dualWriteResult['dual_write_status'] ?? '') !== 'success') {
                    throw new \RuntimeException('evidence body bulk update failed: ' . (string) ($dualWriteResult['message'] ?? 'unknown'));
                }
                if (!$this->bodyRowMatchesPayload((string) $id, $dataType, $next)) {
                    throw new \RuntimeException('evidence body bulk verify failed: persisted values do not match request');
                }
                $updated++;
            }

            if ($startedTransaction && $this->pdo->inTransaction()) {
                $this->pdo->commit();
            }
        } catch (\Throwable $e) {
            if ($startedTransaction && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            error_log('[EvidenceGenerationSaveService::evidenceBulkSaveFromBodyTables] ' . $e->getMessage());
            $this->json(['success' => false, 'message' => '수정 중 오류가 발생했습니다.'], 500);
            return;
        }

        $message = "일괄 보정 완료: 변경 {$updated}건, 유지 {$unchanged}건";
        if ($locked > 0) {
            $message .= ", 잠금 {$locked}건";
        }
        $this->json([
            'success' => true,
            'message' => $message,
            'updated_count' => $updated,
            'unchanged_count' => $unchanged,
            'locked_count' => $locked,
        ]);
    }

    /**
     * @return array{0:?array,1:string}
     */
    private function findEvidenceBodyRow(string $evidenceId, string $importTypeHint = ''): array
    {
        $evidenceId = trim($evidenceId);
        if ($evidenceId === '') {
            return [null, ''];
        }

        $normalizedHint = $this->normalizeDataType($importTypeHint);
        $tableMap = $this->bodyTableMap();
        $candidates = [];
        if ($normalizedHint !== '' && isset($tableMap[$normalizedHint])) {
            $candidates[] = [$normalizedHint, $tableMap[$normalizedHint]];
        }
        foreach ($tableMap as $dataType => $tableName) {
            if ($normalizedHint !== '' && $dataType === $normalizedHint) {
                continue;
            }
            $candidates[] = [$dataType, $tableName];
        }

        foreach ($candidates as [$dataType, $tableName]) {
            if (!$this->tableExists($tableName)) {
                continue;
            }
            $stmt = $this->pdo->prepare("SELECT * FROM `{$tableName}` WHERE id = :id LIMIT 1");
            $stmt->execute([':id' => $evidenceId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
            if (is_array($row)) {
                return [$row, $dataType];
            }
        }

        return [null, ''];
    }

    /**
     * @return array<string,string>
     */
    private function bodyTableMap(): array
    {
        return [
            'BANK_TRANSACTION' => 'ledger_evidence_bank_transaction',
            'TAX_INVOICE' => 'ledger_evidence_tax_invoice',
            'TAX_INVOICE_MANUAL' => 'ledger_evidence_tax_invoice_manual',
            'CASH_RECEIPT' => 'ledger_evidence_cash_receipt',
            'CARD_HOMETAX' => 'ledger_evidence_card_hometax',
            'CARD_STATEMENT' => 'ledger_evidence_card_statement',
            'CARD_APPROVAL' => 'ledger_evidence_card_statement',
        ];
    }

    /**
     * @return list<string>
     */
    private function evidenceTypesForDataType(string $dataType): array
    {
        return match ($dataType) {
            'CARD_STATEMENT', 'CARD_APPROVAL' => ['CARD_STATEMENT', 'CARD_APPROVAL', 'CARD'],
            default => [$dataType],
        };
    }

    private function isEvidenceLocked(string $evidenceId, string $dataType): bool
    {
        if ($this->tableExists('ledger_evidence_links')) {
            $types = $this->evidenceTypesForDataType($dataType);
            $placeholders = [];
            $params = [':evidence_id' => $evidenceId];
            foreach ($types as $index => $type) {
                $key = ':evidence_type_' . $index;
                $placeholders[] = $key;
                $params[$key] = $type;
            }

            $stmt = $this->pdo->prepare("
                SELECT COUNT(*)
                FROM ledger_evidence_links
                WHERE evidence_id = :evidence_id
                  AND evidence_type IN (" . implode(', ', $placeholders) . ")
                  AND target_type IN ('TRANSACTION', 'VOUCHER')
                  AND deleted_at IS NULL
            ");
            $stmt->execute($params);
            if ((int) $stmt->fetchColumn() > 0) {
                return true;
            }
        }

        if ($this->hasEvidenceProcessingTable()) {
            $types = $this->evidenceTypesForDataType($dataType);
            $placeholders = [];
            $params = [':evidence_id' => $evidenceId];
            foreach ($types as $index => $type) {
                $key = ':processing_type_' . $index;
                $placeholders[] = $key;
                $params[$key] = $type;
            }

            $stmt = $this->pdo->prepare("
                SELECT processing_status
                FROM ledger_evidence_processing
                WHERE evidence_id = :evidence_id
                  AND evidence_type IN (" . implode(', ', $placeholders) . ")
                  AND deleted_at IS NULL
                LIMIT 1
            ");
            $stmt->execute($params);
            $processingStatus = strtoupper(trim((string) ($stmt->fetchColumn() ?: '')));
            if (in_array($processingStatus, ['CREATED', 'PROCESSED', 'DONE', 'COMPLETED', 'POSTED'], true)) {
                return true;
            }
        }

        return false;
    }

    private function bodyRowPayload(array $row, string $dataType): array
    {
        $payload = $row;
        $payload['import_type'] = $dataType;
        return $payload;
    }

    private function legacyEvidenceForBodySync(
        string $evidenceId,
        string $dataType,
        array $currentRow,
        array $parsed,
        string $actor,
        ?array $rawPayload = null,
        array $missingMessages = []
    ): array {
        $voucherErrorMessage = $dataType === 'BANK_TRANSACTION'
            ? $this->bankVoucherValidationMessage($parsed)
            : null;
        $evidenceDate = null;
        foreach (['transaction_date', 'evidence_date', 'purchase_datetime', 'purchase_date', 'approval_datetime', 'approval_date', 'write_date', 'written_date', 'issue_date', 'raw_transaction_datetime', 'raw_written_date', 'raw_approval_date', 'raw_purchase_datetime'] as $dateKey) {
            $evidenceDate = $this->dateValueOrNull($parsed[$dateKey] ?? null);
            if ($evidenceDate !== null) {
                break;
            }
        }

        return [
            'id' => $evidenceId,
            'source_type' => $dataType,
            'source_key' => (string) ($currentRow['external_key'] ?? $currentRow['source_key'] ?? ''),
            'evidence_date' => $evidenceDate,
            'client_id' => $this->businessRefIdForStorage('CLIENT', $parsed),
            'project_id' => $this->businessRefIdForStorage('PROJECT', $parsed),
            'employee_id' => $this->businessRefIdForStorage('EMPLOYEE', $parsed),
            'bank_account_id' => $this->businessRefIdForStorage('ACCOUNT', $parsed),
            'card_id' => $this->businessRefIdForStorage('CARD', $parsed),
            'client_name' => $this->businessRefNameForStorage('CLIENT', $parsed),
            'project_name' => $this->businessRefNameForStorage('PROJECT', $parsed),
            'employee_name' => $this->businessRefNameForStorage('EMPLOYEE', $parsed),
            'bank_account_name' => $this->businessRefNameForStorage('ACCOUNT', $parsed),
            'card_name' => $this->businessRefNameForStorage('CARD', $parsed),
            'currency' => (string) ($parsed['currency'] ?? $parsed['currency_code'] ?? 'KRW'),
            'supply_amount' => $this->amountOrNull($parsed['supply_amount'] ?? null),
            'vat_amount' => $this->amountOrNull($parsed['vat_amount'] ?? null),
            'total_amount' => $this->amountOrNull($parsed['total_amount'] ?? null),
            'evidence_status' => $this->businessEvidenceStatusForStorage($missingMessages),
            'transaction_status' => 'NONE',
            'voucher_status' => $this->uploadVoucherStatus($dataType, $parsed, 'READY'),
            'error_message' => $voucherErrorMessage,
            'raw_json' => $rawPayload === null ? '' : $this->jsonEncodeForStorage($rawPayload),
            'mapped_payload_json' => $this->jsonEncodeForStorage($parsed),
            'current_payload' => $parsed,
            'created_at' => $currentRow['created_at'] ?? date('Y-m-d H:i:s'),
            'created_by' => $currentRow['created_by'] ?? $actor,
            'updated_at' => date('Y-m-d H:i:s'),
            'updated_by' => $actor,
            'deleted_at' => $currentRow['deleted_at'] ?? null,
            'deleted_by' => $currentRow['deleted_by'] ?? null,
        ];
    }

    private function bodyRowMatchesPayload(string $evidenceId, string $dataType, array $payload): bool
    {
        [$row, $resolvedType] = $this->findEvidenceBodyRow($evidenceId, $dataType);
        if (!is_array($row) || $resolvedType === '') {
            return false;
        }

        $tableName = $this->bodyTableMap()[$resolvedType] ?? '';
        if ($tableName === '') {
            return false;
        }

        $columns = $this->tableColumns($tableName);
        if ($columns === []) {
            return false;
        }

        foreach ($payload as $payloadKey => $expectedValue) {
            if (!isset($columns[$payloadKey])) {
                continue;
            }

            $expected = $this->normalizeValueForColumn($expectedValue, $columns[$payloadKey] ?? []);
            $actual = $this->normalizeValueForColumn($row[$payloadKey] ?? null, $columns[$payloadKey] ?? []);
            if ($this->stringifyComparableValue($expected) !== $this->stringifyComparableValue($actual)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return array<string,array{data_type:string,max_length:int|null}>
     */
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
            'date' => $this->dateValueOrNull($value),
            'datetime', 'timestamp' => $this->dateTimeValueOrNull($value),
            'time' => $this->timeValueOrNull($value),
            'tinyint', 'smallint', 'mediumint', 'int', 'bigint' => $this->integerValueOrNull($value),
            'decimal', 'numeric', 'float', 'double', 'real' => $this->decimalValueOrNull($value),
            default => $value,
        };
    }

    private function stringifyComparableValue(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        return trim((string) $value);
    }

    private function dateTimeValueOrNull(mixed $value): ?string
    {
        $raw = trim((string) ($value ?? ''));
        if ($raw === '') {
            return null;
        }

        $ts = strtotime($raw);
        return $ts ? date('Y-m-d H:i:s', $ts) : null;
    }

    private function timeValueOrNull(mixed $value): ?string
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

    private function integerValueOrNull(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        $normalized = str_replace(',', '', trim((string) $value));
        return is_numeric($normalized) ? (int) round((float) $normalized) : null;
    }

    private function decimalValueOrNull(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $normalized = str_replace(',', '', trim((string) $value));
        return is_numeric($normalized) ? (string) $normalized : null;
    }

    private function nextGlobalEvidenceSortNo(string $actor): int
    {
        $actor = trim($actor);
        if ($actor === '') {
            $actor = 'SYSTEM';
        }

        if ($this->tableExists('ledger_evidence_number_sequences')) {
            $this->pdo->prepare("
                INSERT INTO ledger_evidence_number_sequences
                    (scope_code, last_evidence_sort_no, updated_at, updated_by)
                VALUES
                    ('EVIDENCE_GLOBAL', 0, NOW(), :actor)
                ON DUPLICATE KEY UPDATE
                    updated_at = updated_at
            ")->execute([':actor' => $actor]);

            $this->pdo->prepare("
                UPDATE ledger_evidence_number_sequences
                SET last_evidence_sort_no = LAST_INSERT_ID(last_evidence_sort_no + 1),
                    updated_at = NOW(),
                    updated_by = :actor
                WHERE scope_code = 'EVIDENCE_GLOBAL'
            ")->execute([':actor' => $actor]);

            return (int) $this->pdo->query("SELECT LAST_INSERT_ID()")->fetchColumn();
        }

        $max = 0;
        foreach ($this->bodyTableMap() as $table) {
            if (!$this->tableExists($table)) {
                continue;
            }

            $stmt = $this->pdo->query("SELECT COALESCE(MAX(evidence_sort_no), 0) FROM `{$table}`");
            $max = max($max, (int) ($stmt->fetchColumn() ?: 0));
        }

        return $max + 1;
    }

    private function actorForStorage(): string
    {
        $actor = trim(ActorHelper::user());
        if ($actor === '') {
            return '';
        }

        $parsed = ActorHelper::parse($actor);
        if (($parsed['type'] ?? '') === 'USER') {
            $userId = trim((string) ($parsed['id'] ?? ''));
            if ($userId !== '') {
                return strlen($userId) > 36 ? substr($userId, 0, 36) : $userId;
            }
        }

        if (($parsed['type'] ?? '') === 'SYSTEM') {
            $context = trim((string) ($parsed['context'] ?? ''));
            $systemActor = $context === '' ? 'SYSTEM' : 'SYSTEM:' . $context;
            return strlen($systemActor) > 36 ? substr($systemActor, 0, 36) : $systemActor;
        }

        return strlen($actor) > 36 ? substr($actor, 0, 36) : $actor;
    }

    private function businessEvidenceStatusForStorage(array $missingMessages = []): string
    {
        return $this->evidenceStatusFromRequiredMissingMessages($missingMessages);
    }

}

class EvidenceGenerationSaveResponse extends \RuntimeException
{
    public function __construct(
        public array $payload,
        public int $status = 200
    ) {
        parent::__construct('Evidence generation save response', $status);
    }
}
