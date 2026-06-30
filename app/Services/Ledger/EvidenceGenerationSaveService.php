<?php

namespace App\Services\Ledger;

use Core\Helpers\ActorHelper;
use Core\Helpers\UuidHelper;
use PDO;

class EvidenceGenerationSaveService
{
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

    private function doSeedRowSave(array $payload): void
    {
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
                COALESCE(pr.processing_status, 'READY') AS transaction_status,
                pr.review_status,
                pr.last_error_message AS error_message,
                tx.target_id AS transaction_id,
                vx.target_id AS voucher_id
             FROM ledger_evidence_payloads p
             LEFT JOIN ledger_evidence_processing pr
                ON pr.evidence_type COLLATE utf8mb4_unicode_ci = p.evidence_type COLLATE utf8mb4_unicode_ci
               AND pr.evidence_id COLLATE utf8mb4_unicode_ci = p.evidence_id COLLATE utf8mb4_unicode_ci
                AND pr.deleted_at IS NULL
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

        $currentMapped = json_decode((string) ($current['mapped_payload_json'] ?? ''), true);
        $currentMapped = is_array($currentMapped) ? $currentMapped : [];
        foreach (['_status_sort_no', '_create_sort_no'] as $sortKey) {
            if (isset($currentMapped[$sortKey])) {
                $parsed[$sortKey] = $currentMapped[$sortKey];
            }
        }
        $createSortNo = (int) ($parsed['_create_sort_no'] ?? 0);
        $statusSortNo = (int) ($parsed['_status_sort_no'] ?? 0);
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
        $processingUpsert = $this->pdo->prepare("
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
        ");

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

            $processingUpsert->execute([
                ':processing_id' => UuidHelper::generate(),
                ':id' => $seedRowId,
                ':source_type' => (string) ($current['source_type'] ?? ''),
                ':processing_status' => $processingStatus,
                ':review_status' => 'NORMAL',
                ':error_message' => $voucherErrorMessage,
            ]);

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
                'currency' => (string) ($parsed['currency'] ?? $parsed['currency_code'] ?? 'KRW'),
                'supply_amount' => $this->amountOrNull($parsed['supply_amount'] ?? null),
                'vat_amount' => $this->amountOrNull($parsed['vat_amount'] ?? null),
                'total_amount' => $this->amountOrNull($parsed['total_amount'] ?? null),
                'evidence_status' => 'ACTIVE',
                'transaction_status' => 'NONE',
                'voucher_status' => $voucherStatus,
                'error_message' => $voucherErrorMessage,
                'raw_json' => $encodedRaw,
                'mapped_payload_json' => $encodedPayload,
                'created_at' => $current['created_at'] ?? null,
                'created_by' => $current['created_by'] ?? null,
                'updated_at' => date('Y-m-d H:i:s'),
                'updated_by' => $actor,
                'deleted_at' => null,
            ];
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
            'create_sort_no' => $createSortNo,
            'status_sort_no' => $statusSortNo,
            'raw_payload' => is_array($raw) ? $raw : [],
            'mapped_payload' => $this->normalizeEvidenceMappedPayloadForResponse($parsed),
            'evidence_status' => 'ACTIVE',
            'transaction_status' => 'NONE',
            'voucher_status' => $voucherStatus,
            'review_status' => 'NORMAL',
            'error_message' => $voucherErrorMessage,
            'process_status' => 'READY',
            'status' => 'READY',
        ];
        ($this->callbacks['applyReadinessToEvidenceRow'])($responseRow);

        $this->json(['success' => true, 'message' => json_decode('"Seed Data\uAC00 \uC218\uC815\uB418\uC5C8\uC2B5\uB2C8\uB2E4."'), 'data' => $responseRow]);
    }

    private function doEvidenceCreate(array $payload): void
    {
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
        $nextStatusSortNo = $this->nextEvidenceJsonSortNo('_status_sort_no', $dataType);
        $nextCreateSortNo = $this->nextEvidenceJsonSortNo('_create_sort_no');
        $parsed['_status_sort_no'] = $nextStatusSortNo;
        $parsed['_create_sort_no'] = $nextCreateSortNo;

        $rawJson = $this->jsonEncodeForStorage($raw);
        $mappedPayloadJson = $this->jsonEncodeForStorage($parsed);
        $processingStatus = $voucherErrorMessage === null || trim((string) $voucherErrorMessage) === ''
            ? 'READY'
            : 'REVIEW_REQUIRED';
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
            'currency' => (string) ($parsed['currency'] ?? $parsed['currency_code'] ?? 'KRW'),
            'supply_amount' => $this->number($parsed['supply_amount'] ?? null),
            'vat_amount' => $this->number($parsed['vat_amount'] ?? null),
            'total_amount' => $this->evidenceTotalAmountForStorage($parsed, $dataType),
            'create_sort_no' => $nextCreateSortNo,
            'status_sort_no' => $nextStatusSortNo,
            'evidence_status' => 'ACTIVE',
            'transaction_status' => 'NONE',
            'voucher_status' => $voucherStatus,
            'error_message' => $voucherErrorMessage,
            'raw_json' => $rawJson,
            'mapped_payload_json' => $mappedPayloadJson,
            'created_by' => $actor,
            'updated_by' => $actor,
            'deleted_at' => null,
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
                ':source_type' => $dataType,
                ':id' => $evidenceId,
                ':source_key' => $sourceKey,
                ':format_id' => $formatId,
                ':raw_json' => $rawJson,
                ':mapped_payload_json' => $mappedPayloadJson,
                ':mapped_payload_json_hash' => $mappedPayloadJson,
                ':created_by' => $actor,
                ':updated_by' => $actor,
            ]);

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
                COALESCE(pr.processing_status, 'READY') AS transaction_status,
                pr.review_status,
                pr.last_error_message AS error_message,
                tx.target_id AS transaction_id,
                vx.target_id AS voucher_id
             FROM ledger_evidence_payloads p
             LEFT JOIN ledger_evidence_processing pr
                ON pr.evidence_type COLLATE utf8mb4_unicode_ci = p.evidence_type COLLATE utf8mb4_unicode_ci
               AND pr.evidence_id COLLATE utf8mb4_unicode_ci = p.evidence_id COLLATE utf8mb4_unicode_ci
                AND pr.deleted_at IS NULL
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
        $processingUpsert = $this->pdo->prepare("
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
        ");

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

                $processingUpsert->execute([
                    ':processing_id' => UuidHelper::generate(),
                    ':id' => (string) $row['id'],
                    ':source_type' => $sourceType,
                    ':processing_status' => $processingStatus,
                    ':review_status' => 'NORMAL',
                    ':error_message' => $voucherErrorMessage,
                ]);

                $legacyForEvidence = [
                    'id' => (string) $row['id'],
                    'source_type' => $sourceType,
                    'source_key' => (string) ($row['source_key'] ?? ''),
                    'evidence_date' => $this->dateValueOrNull($next['evidence_date'] ?? $next['transaction_date'] ?? $next['purchase_date'] ?? $next['approval_date'] ?? $next['issue_date'] ?? null),
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
                    'evidence_status' => 'ACTIVE',
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
