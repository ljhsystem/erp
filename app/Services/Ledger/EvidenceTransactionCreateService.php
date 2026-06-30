<?php

namespace App\Services\Ledger;

use Core\Helpers\ActorHelper;
use PDO;

class EvidenceTransactionCreateService
{
    /**
     * @param array<string, callable> $callbacks
     */
    public function __construct(
        private PDO $pdo,
        private array $callbacks
    ) {
    }

    public function createTransactions(array $payload): array
    {
        return $this->capture(fn() => $this->doCreateTransactions($payload));
    }

    private function capture(callable $handler): array
    {
        try {
            $handler();
        } catch (EvidenceTransactionCreateResponse $response) {
            return ['payload' => $response->payload, 'status' => $response->status];
        }

        return ['payload' => ['success' => false, 'message' => 'Response was not generated.'], 'status' => 500];
    }

    private function json(array $payload, int $status = 200): void
    {
        throw new EvidenceTransactionCreateResponse($payload, $status);
    }

    public function __call(string $name, array $arguments): mixed
    {
        if (!isset($this->callbacks[$name])) {
            throw new \BadMethodCallException('Undefined helper callback: ' . $name);
        }

        return ($this->callbacks[$name])(...$arguments);
    }

    private function doCreateTransactions(array $payload): void
    {
        $batchId = trim((string) ($payload['batch_id'] ?? ''));
        $rowIds = is_array($payload['seed_row_ids'] ?? null)
            ? array_values(array_filter(array_map('strval', $payload['seed_row_ids'])))
            : (is_array($payload['row_ids'] ?? null) ? array_values(array_filter(array_map('strval', $payload['row_ids']))) : []);
        $confirmExistingVoucher = !empty($payload['confirm_existing_voucher']);

        if ($batchId === '' && $rowIds === []) {
            $this->json(['success' => false, 'message' => 'Select Seed Data to create transactions.'], 400);
            return;
        }

        $batch = $batchId !== '' ? $this->uploadBatch($batchId) : null;
        if ($batchId !== '' && !$batch) {
            $this->json(['success' => false, 'message' => 'Seed batch was not found.'], 404);
            return;
        }

        if (!empty($payload['bundled_voucher'])) {
            $result = $this->createBundledVoucherFromEvidenceRows($rowIds);
            $this->json($result, !empty($result['success']) ? 200 : 422);
            return;
        }

        $dataType = $this->normalizeDataType((string) ($batch['data_type'] ?? 'TAX_INVOICE'));
        $existingVoucherRows = $rowIds !== [] ? $this->existingVoucherRowsForEvidenceIds($rowIds) : [];
        if ($existingVoucherRows !== [] && !$confirmExistingVoucher) {
            $this->json([
                'success' => false,
                'requires_confirmation' => true,
                'confirmation_code' => 'EXISTING_VOUCHER',
                'message' => 'Existing vouchers were found. Confirm whether to link them.',
                'existing_vouchers' => $existingVoucherRows,
            ], 409);
            return;
        }

        $linkedExistingVoucherCount = $confirmExistingVoucher
            ? $this->linkExistingVouchersForEvidenceRows($existingVoucherRows)
            : 0;
        $rows = $this->claimSeedRowsForTransactionCreate($batchId, $rowIds);

        if ($rows === []) {
            $this->json([
                'success' => $linkedExistingVoucherCount > 0,
                'batch_id' => $batchId !== '' ? $batchId : null,
                'target_count' => $linkedExistingVoucherCount > 0 ? count($rowIds) : 0,
                'created_count' => 0,
                'duplicate_count' => 0,
                'error_count' => 0,
                'transaction_line_count' => 0,
                'success_count' => $linkedExistingVoucherCount,
                'processed_ids' => $linkedExistingVoucherCount > 0 ? array_values(array_unique(array_column($existingVoucherRows, 'evidence_id'))) : [],
                'error_ids' => [],
                'updated_status' => [],
                'errors' => [],
                'message' => $linkedExistingVoucherCount > 0 ? 'Existing vouchers were linked.' : 'No READY Seed Data is available for transaction creation.',
            ], $linkedExistingVoucherCount > 0 ? 200 : 400);
            return;
        }

        $created = 0;
        $duplicates = 0;
        $errors = [];
        $createdTransactionIds = [];
        $processedIds = [];
        $errorIds = [];
        $updatedStatus = [];

        foreach ($rows as $row) {
            $rowId = (string) ($row['id'] ?? '');
            $rowNo = (int) ($row['row_no'] ?? 0);
            $mapped = $this->decodeMappedPayload($row['mapped_payload'] ?? null);
            if (!is_array($mapped)) {
                $message = 'Mapped payload JSON could not be read.';
                $this->updateUploadRowStatus($rowId, 'ERROR', $message);
                $errorIds[] = $rowId;
                $updatedStatus[$rowId] = 'ERROR';
                $errors[] = ['row_id' => $rowId, 'row' => $rowNo, 'message' => $message];
                continue;
            }

            try {
                $rowDataType = $this->normalizeDataType((string) ($row['source_type'] ?? $dataType));
                if (!$this->isTransactionProcessingType($rowDataType) && $rowDataType !== 'BANK_TRANSACTION') {
                    $plan = $this->processingPlanForDataType($rowDataType);
                    $message = 'This data type is not eligible for transaction creation. Processing target: ' . $plan['label'];
                    $this->updateUploadRowStatus($rowId, 'ERROR', $message);
                    $errorIds[] = $rowId;
                    $updatedStatus[$rowId] = 'ERROR';
                    $errors[] = ['row_id' => $rowId, 'row' => $rowNo, 'message' => $message];
                    continue;
                }

                $readiness = $this->readinessForEvidenceRow([
                    'source_type' => $rowDataType,
                    'import_type' => $rowDataType,
                    'source_key' => $row['source_key'] ?? '',
                    'evidence_date' => $row['evidence_date'] ?? '',
                ], $mapped);
                if (($readiness['status'] ?? '') !== 'READY') {
                    $message = 'Generation readiness validation failed: ' . implode(' / ', $readiness['errors'] ?? []);
                    $this->updateUploadRowStatus($rowId, 'ERROR', $message);
                    $errorIds[] = $rowId;
                    $updatedStatus[$rowId] = 'ERROR';
                    $errors[] = ['row_id' => $rowId, 'row' => $rowNo, 'message' => $message, 'missing_fields' => $readiness['missing_fields'] ?? []];
                    continue;
                }

                if ($rowDataType === 'BANK_TRANSACTION') {
                    $voucherId = $this->createVoucherFromBankPayload($rowId, $mapped, '', $confirmExistingVoucher);
                    $this->resetBankEvidenceTransactionClaim($rowId, ActorHelper::user());
                    if ($voucherId !== null) {
                        $created++;
                        $processedIds[] = $rowId;
                        $updatedStatus[$rowId] = 'PROCESSED';
                        continue;
                    }

                    $message = 'Bank transaction voucher creation failed. Check voucher correction data.';
                    $errorIds[] = $rowId;
                    $updatedStatus[$rowId] = 'ERROR';
                    $errors[] = ['row_id' => $rowId, 'row' => $rowNo, 'message' => $message];
                    continue;
                }

                if ($this->hasDuplicateTransaction($mapped, $rowId, $rowDataType)) {
                    $duplicates++;
                    $this->updateUploadRowStatus($rowId, 'DUPLICATE', 'Existing transaction found');
                    $updatedStatus[$rowId] = 'DUPLICATED';
                    continue;
                }

                $result = $this->createTransactionFromPayload($mapped, $rowDataType);
                if (!empty($result['success']) && !empty($result['id'])) {
                    $transactionId = (string) $result['id'];
                    $created++;
                    $createdTransactionIds[] = $transactionId;
                    $processedIds[] = $rowId;
                    $updatedStatus[$rowId] = 'PROCESSED';
                    $this->updateUploadRowStatus($rowId, 'CREATED', null, $transactionId);
                    $this->createVoucherFromBankPayload($rowId, $mapped, $transactionId, $confirmExistingVoucher);
                    continue;
                }

                if (!empty($result['fallback_transaction_created']) && !empty($result['id'])) {
                    $transactionId = (string) $result['id'];
                    $created++;
                    $createdTransactionIds[] = $transactionId;
                    $processedIds[] = $rowId;
                    $updatedStatus[$rowId] = 'PROCESSED';
                    $this->updateUploadRowStatus($rowId, 'CREATED', 'Transaction header created. Transaction lines need correction.', $transactionId);
                    $this->createVoucherFromBankPayload($rowId, $mapped, $transactionId, $confirmExistingVoucher);
                    $errors[] = [
                        'row_id' => $rowId,
                        'row' => $rowNo,
                        'message' => 'Transaction header was created, but transaction line storage needs correction.',
                        'transaction_id' => $transactionId,
                    ];
                    continue;
                }

                $message = $this->formatTransactionCreateError((string) ($result['message'] ?? 'Transaction creation failed'), $mapped, $rowNo);
                $this->updateUploadRowStatus($rowId, 'ERROR', $message);
                $errorIds[] = $rowId;
                $updatedStatus[$rowId] = 'ERROR';
                $errors[] = ['row_id' => $rowId, 'row' => $rowNo, 'message' => $message];
            } catch (\Throwable $e) {
                $message = $this->formatTransactionCreateError($e->getMessage(), $mapped, $rowNo);
                $this->updateUploadRowStatus($rowId, 'ERROR', $message);
                $errorIds[] = $rowId;
                $updatedStatus[$rowId] = 'ERROR';
                $errors[] = ['row_id' => $rowId, 'row' => $rowNo, 'message' => $message];
            }
        }

        if ($batchId !== '') {
            $this->refreshUploadBatchStatus($batchId);
        }

        $errorCount = count($errors);
        $transactionLineCount = $this->countTransactionLines($createdTransactionIds);
        $success = $errorCount === 0;
        $message = sprintf('ERP transaction creation completed: created %d, duplicate %d, error %d', $created, $duplicates, $errorCount);

        $this->json([
            'success' => $success,
            'batch_id' => $batchId !== '' ? $batchId : null,
            'target_count' => count($rows),
            'created_count' => $created,
            'duplicate_count' => $duplicates,
            'error_count' => $errorCount,
            'transaction_line_count' => $transactionLineCount,
            'success_count' => $created,
            'processed_ids' => $processedIds,
            'error_ids' => $errorIds,
            'updated_status' => $updatedStatus,
            'errors' => $errors,
            'message' => $message,
        ], $success ? 200 : 422);
        return;

        // Legacy fallback block kept during transaction create service 1st split. It is unreachable after the response above.
        if ($batchId === '') {
            $this->json(['success' => false, 'message' => 'Upload batch ID is missing.'], 400);
            return;
        }

        $batch = $this->uploadBatch($batchId);
        if (!$batch) {
            $this->json(['success' => false, 'message' => 'Upload batch was not found.'], 404);
            return;
        }

        $dataType = $this->normalizeDataType((string) ($batch['data_type'] ?? 'TAX_INVOICE'));
        $rowIds = is_array($payload['row_ids'] ?? null) ? array_values(array_filter(array_map('strval', $payload['row_ids']))) : [];
        $rows = $this->uploadRowsForTransactionCreate($batchId, $rowIds);
        if ($rows === []) {
            $this->json(['success' => false, 'message' => 'No READY Seed Data is available for transaction creation.'], 400);
            return;
        }

        $created = 0;
        $duplicates = 0;
        $errors = [];
        foreach ($rows as $row) {
            $mapped = $this->decodeMappedPayload($row['mapped_payload'] ?? null);
            if (!is_array($mapped)) {
                $message = 'Mapped payload JSON could not be read.';
                $this->updateUploadRowStatus((string) $row['id'], 'ERROR', $message);
                $errors[] = ['row' => (int) $row['row_no'], 'message' => $message];
                continue;
            }

            if ($this->hasDuplicateTransaction($mapped, (string) ($row['id'] ?? ''), $dataType)) {
                $duplicates++;
                $this->updateUploadRowStatus((string) $row['id'], 'DUPLICATE', 'Existing transaction found');
                continue;
            }

            $result = $this->createTransactionFromPayload($mapped, $dataType);
            if (!empty($result['success'])) {
                $created++;
                $this->updateUploadRowStatus((string) $row['id'], 'CREATED', null, (string) ($result['id'] ?? ''));
            } else {
                $message = $result['message'] ?? 'Transaction creation failed';
                $this->updateUploadRowStatus((string) $row['id'], 'ERROR', $message);
                $errors[] = ['row' => (int) $row['row_no'], 'message' => $message];
            }
        }
        $this->refreshUploadBatchStatus($batchId);

        $this->json([
            'success' => $errors === [],
            'created_count' => $created,
            'duplicate_count' => $duplicates,
            'errors' => $errors,
            'message' => $errors === [] ? "{$created} transactions were created." : 'Some transaction creation failed.',
        ], $errors === [] ? 200 : 422);
    }

    private function claimSeedRowsForTransactionCreate(string $batchId = '', array $rowIds = []): array
    {
        $this->ensureEvidenceBusinessInfoColumns();
        $params = [];
        $claimableStatuses = ["'READY'", "'ERROR'", "'DUPLICATED'"];
        $where = [
            'COALESCE(pr.processing_status, \'READY\') IN (' . implode(', ', $claimableStatuses) . ')',
            'p.deleted_at IS NULL',
        ];
        $transactionTypes = $this->transactionProcessingDataTypes();
        $typePlaceholders = [];
        foreach ($transactionTypes as $index => $type) {
            $key = ':transaction_type_' . $index;
            $typePlaceholders[] = $key;
            $params[$key] = $type;
        }
        $where[] = 'p.evidence_type IN (' . implode(', ', $typePlaceholders) . ')';
        $where[] = 'tx.target_id IS NULL';
        if ($batchId !== '') {
            $where[] = 'DATE(p.latest_imported_at) = :batch_id';
            $params[':batch_id'] = $batchId;
        }
        if ($rowIds !== []) {
            $placeholders = [];
            foreach ($rowIds as $index => $rowId) {
                $key = ':row_id_' . $index;
                $placeholders[] = $key;
                $params[$key] = $rowId;
            }
            $where[] = 'p.evidence_id IN (' . implode(', ', $placeholders) . ')';
        }

        $this->pdo->beginTransaction();
        try {
            $select = $this->pdo->prepare("
                SELECT p.evidence_id AS id, p.evidence_type AS source_type, p.source_key,
                       JSON_UNQUOTE(JSON_EXTRACT(p.mapped_payload_json, '$.evidence_date')) AS evidence_date,
                       p.mapped_payload_json
                FROM ledger_evidence_payloads p
                LEFT JOIN ledger_evidence_processing pr
                    ON pr.evidence_type = p.evidence_type
                   AND pr.evidence_id = p.evidence_id
                   AND pr.deleted_at IS NULL
                LEFT JOIN ledger_evidence_links tx
                    ON tx.evidence_type = p.evidence_type
                   AND tx.evidence_id = p.evidence_id
                   AND tx.target_type = 'TRANSACTION'
                   AND tx.deleted_at IS NULL
                WHERE " . implode(' AND ', $where) . "
                ORDER BY p.latest_imported_at DESC, p.created_at DESC
                FOR UPDATE
            ");
            $select->execute($params);
            $ids = [];
            foreach (($select->fetchAll(PDO::FETCH_ASSOC) ?: []) as $candidateRow) {
                $mapped = json_decode((string) ($candidateRow['mapped_payload_json'] ?? ''), true);
                $mapped = is_array($mapped) ? $this->normalizeEvidenceMappedPayloadForResponse($mapped) : [];
                ($this->callbacks['mergeEvidenceBusinessInfoIntoPayload'])($candidateRow, $mapped);
                $readiness = $this->readinessForEvidenceRow([
                    'source_type' => $candidateRow['source_type'] ?? '',
                    'import_type' => $candidateRow['source_type'] ?? '',
                    'source_key' => $candidateRow['source_key'] ?? '',
                    'evidence_date' => $candidateRow['evidence_date'] ?? '',
                ], $mapped);
                if (($readiness['status'] ?? '') === 'READY') {
                    $ids[] = (string) ($candidateRow['id'] ?? '');
                }
            }
            $ids = array_values(array_filter(array_unique($ids)));
            if ($ids === []) {
                $this->pdo->commit();
                return [];
            }

            $idParams = [];
            $idPlaceholders = [];
            foreach ($ids as $index => $id) {
                $key = ':claim_id_' . $index;
                $idPlaceholders[] = $key;
                $idParams[$key] = $id;
            }
            $update = $this->pdo->prepare("
                UPDATE ledger_evidence_processing pr
                LEFT JOIN ledger_evidence_links tx
                    ON tx.evidence_type = pr.evidence_type
                   AND tx.evidence_id = pr.evidence_id
                   AND tx.target_type = 'TRANSACTION'
                   AND tx.deleted_at IS NULL
                SET pr.processing_status = 'PROCESSING',
                    pr.last_error_message = NULL,
                    pr.updated_at = NOW()
                WHERE pr.evidence_id IN (" . implode(', ', $idPlaceholders) . ")
                  AND pr.processing_status IN (" . implode(', ', $claimableStatuses) . ")
                  AND pr.deleted_at IS NULL
                  AND tx.target_id IS NULL
            ");
            $update->execute($idParams);
            $this->pdo->commit();
            return $this->seedRowsForTransactionCreate('', $ids, 'PROCESSING');
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    private function seedRowsForTransactionCreate(string $batchId = '', array $rowIds = [], string $status = 'READY'): array
    {
        $this->ensureEvidenceBusinessInfoColumns();
        $params = [];
        $where = ['p.deleted_at IS NULL', 'tx.target_id IS NULL'];
        $where[] = $status === 'PROCESSING'
            ? "COALESCE(pr.processing_status, 'READY') = 'PROCESSING'"
            : "COALESCE(pr.processing_status, 'READY') = 'READY'";
        $typePlaceholders = [];
        foreach ($this->transactionProcessingDataTypes() as $index => $type) {
            $key = ':transaction_type_' . $index;
            $typePlaceholders[] = $key;
            $params[$key] = $type;
        }
        $where[] = 'p.evidence_type IN (' . implode(', ', $typePlaceholders) . ')';
        if ($batchId !== '') {
            $where[] = 'DATE(p.latest_imported_at) = :batch_id';
            $params[':batch_id'] = $batchId;
        }
        if ($rowIds !== []) {
            $placeholders = [];
            foreach ($rowIds as $index => $rowId) {
                $key = ':row_id_' . $index;
                $placeholders[] = $key;
                $params[$key] = $rowId;
            }
            $where[] = 'p.evidence_id IN (' . implode(', ', $placeholders) . ')';
        }

        $stmt = $this->pdo->prepare("
            SELECT p.evidence_id AS id, NULL AS batch_id, p.evidence_type AS source_type, p.source_key,
                   JSON_UNQUOTE(JSON_EXTRACT(p.mapped_payload_json, '$.evidence_date')) AS evidence_date,
                   0 AS row_no, p.raw_json AS raw_payload, p.mapped_payload_json AS mapped_payload,
                   COALESCE(pr.processing_status, 'READY') AS status,
                   pr.last_error_message AS error_message,
                   CASE WHEN vx.target_id IS NULL THEN 'WAITING' ELSE 'LINKED' END AS voucher_status,
                   tx.target_id AS transaction_id
            FROM ledger_evidence_payloads p
            LEFT JOIN ledger_evidence_processing pr
                ON pr.evidence_type = p.evidence_type
               AND pr.evidence_id = p.evidence_id
               AND pr.deleted_at IS NULL
            LEFT JOIN ledger_evidence_links tx
                ON tx.evidence_type = p.evidence_type
               AND tx.evidence_id = p.evidence_id
               AND tx.target_type = 'TRANSACTION'
               AND tx.deleted_at IS NULL
            LEFT JOIN ledger_evidence_links vx
                ON vx.evidence_type = p.evidence_type
               AND vx.evidence_id = p.evidence_id
               AND vx.target_type = 'VOUCHER'
               AND vx.deleted_at IS NULL
            WHERE " . implode(' AND ', $where) . "
            ORDER BY p.latest_imported_at DESC, p.created_at DESC
        ");
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        foreach ($rows as $index => &$row) {
            $row['row_no'] = $index + 1;
            $mappedPayload = json_decode((string) ($row['mapped_payload'] ?? ''), true);
            $row['mapped_payload'] = is_array($mappedPayload) ? $this->normalizeEvidenceMappedPayloadForResponse($mappedPayload) : [];
            ($this->callbacks['mergeEvidenceBusinessInfoIntoPayload'])($row, $row['mapped_payload']);
            $readiness = $this->readinessForEvidenceRow([
                'source_type' => $row['source_type'] ?? '',
                'import_type' => $row['source_type'] ?? '',
                'source_key' => $row['source_key'] ?? '',
                'evidence_date' => $row['evidence_date'] ?? '',
            ], $row['mapped_payload']);
            $row['readiness_status'] = $readiness['status'];
            $row['readiness_errors'] = $readiness['errors'];
            $row['missing_fields'] = $readiness['missing_fields'];
            $row['processing_type'] = $readiness['processing_type'];
            $row['processing_objects'] = $readiness['processing_objects'];
            $row['processing_label'] = $readiness['processing_label'];
        }
        unset($row);
        return $rows;
    }

    private function uploadRowsForTransactionCreate(string $batchId, array $rowIds = []): array
    {
        return $this->seedRowsForTransactionCreate($batchId, $rowIds);
    }

    private function countTransactionLines(array $transactionIds): int
    {
        $transactionIds = array_values(array_filter(array_map('strval', $transactionIds)));
        if ($transactionIds === []) {
            return 0;
        }
        $placeholders = [];
        $params = [];
        foreach ($transactionIds as $index => $transactionId) {
            $key = ':transaction_id_' . $index;
            $placeholders[] = $key;
            $params[$key] = $transactionId;
        }
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM ledger_transaction_items WHERE transaction_id IN (' . implode(', ', $placeholders) . ') AND deleted_at IS NULL');
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    private function decodeMappedPayload(mixed $payload): ?array
    {
        if (is_array($payload)) {
            return $payload;
        }
        if (!is_string($payload) || trim($payload) === '') {
            return null;
        }
        $decoded = json_decode($payload, true);
        return is_array($decoded) ? $decoded : null;
    }

    private function createTransactionFromPayload(array $row, string $dataType): array
    {
        $payload = $this->buildTransactionCreatePayload($row, $dataType);
        $result = $this->transactionService()->save($payload);
        if (!empty($result['success']) || !empty($payload['_header_only_retry'])) {
            return $result;
        }
        $message = (string) ($result['message'] ?? '');
        if (!$this->shouldRetryTransactionHeaderOnly($message, $payload)) {
            return $result;
        }
        $payload['_header_only_retry'] = true;
        $payload['_original_items'] = $payload['items'] ?? [];
        $payload['items'] = [];
        $retry = $this->transactionService()->save($payload);
        if (!empty($retry['success']) && !empty($retry['id'])) {
            $retry['fallback_transaction_created'] = true;
            $retry['message'] = trim(($retry['message'] ?? '') . ' / Transaction lines need correction');
            $retry['original_error'] = $message;
        }
        return $retry;
    }

    private function hasDuplicateTransaction(array $row, string $uploadRowId, string $dataType): bool
    {
        $approvalNo = trim((string) ($row['approval_number'] ?? $row['approval_no'] ?? ''));
        if ($approvalNo !== '') {
            $stmt = $this->pdo->prepare("
                SELECT 1
                FROM ledger_evidence_payloads p
                LEFT JOIN ledger_evidence_processing pr
                    ON pr.evidence_type = p.evidence_type
                   AND pr.evidence_id = p.evidence_id
                   AND pr.deleted_at IS NULL
                LEFT JOIN ledger_evidence_links tx
                    ON tx.evidence_type = p.evidence_type
                   AND tx.evidence_id = p.evidence_id
                   AND tx.target_type = 'TRANSACTION'
                   AND tx.deleted_at IS NULL
                WHERE p.evidence_id <> :row_id
                  AND p.deleted_at IS NULL
                  AND (
                    COALESCE(pr.processing_status, 'READY') IN ('CREATED', 'PROCESSED', 'DONE', 'COMPLETED', 'POSTED')
                    OR (tx.target_id IS NOT NULL AND tx.target_id <> '')
                  )
                  AND p.mapped_payload_json LIKE :approval_no
                LIMIT 1
            ");
            $stmt->execute([':row_id' => $uploadRowId, ':approval_no' => '%' . $approvalNo . '%']);
            if ($stmt->fetchColumn()) {
                return true;
            }
        }
        $rawDate = trim((string) ($row['transaction_date'] ?? ''));
        if ($rawDate === '') {
            return false;
        }
        $date = $this->dateValue($rawDate);
        $total = $this->number($row['total_amount'] ?? $row['supply_amount'] ?? 0);
        if ($date === '' || abs($total) <= 0) {
            return false;
        }
        $context = $this->resolveUploadTransactionContext($row, $dataType);
        $businessNumber = $this->normalizeBusinessNumber((string) ($context['client_business_number'] ?? $row['client_business_number'] ?? $row['business_number'] ?? ''));
        $companyName = $this->cleanCompanyName((string) ($context['client_company_name'] ?? $row['client_company_name'] ?? $row['company_name'] ?? ''));
        $clientId = null;
        if ($businessNumber !== '') {
            $stmt = $this->pdo->prepare("SELECT id FROM system_clients WHERE business_number = :business_number AND deleted_at IS NULL LIMIT 1");
            $stmt->execute([':business_number' => $businessNumber]);
            $clientId = $stmt->fetchColumn() ?: null;
        }
        if ($clientId === null && $companyName !== '') {
            $clientId = $this->findClientId($companyName);
        }
        $sql = "SELECT 1 FROM ledger_transactions WHERE deleted_at IS NULL AND transaction_date = :transaction_date AND transaction_total_amount = :transaction_total_amount";
        $params = [':transaction_date' => $date, ':transaction_total_amount' => $total];
        if ($clientId !== null) {
            $sql .= ' AND client_id = :client_id';
            $params[':client_id'] = $clientId;
        } elseif ($companyName !== '') {
            $sql .= ' AND transaction_description LIKE :company_name';
            $params[':company_name'] = '%' . $companyName . '%';
        } else {
            return false;
        }
        $stmt = $this->pdo->prepare($sql . ' LIMIT 1');
        $stmt->execute($params);
        return (bool) $stmt->fetchColumn();
    }

    private function existingVoucherRowsForEvidenceIds(array $evidenceIds): array
    {
        $evidenceIds = array_values(array_filter(array_unique(array_map('strval', $evidenceIds))));
        if ($evidenceIds === [] || !$this->tableExists('ledger_vouchers')) {
            return [];
        }
        $rowsByEvidenceId = [];
        foreach ($this->evidenceRowsForExistingVoucherCheck($evidenceIds) as $evidenceRow) {
            $evidenceId = (string) ($evidenceRow['id'] ?? '');
            $mapped = json_decode((string) ($evidenceRow['mapped_payload_json'] ?? ''), true);
            $mapped = is_array($mapped) ? $this->normalizeEvidenceMappedPayloadForResponse($mapped) : [];
            ($this->callbacks['mergeEvidenceBusinessInfoIntoPayload'])($evidenceRow, $mapped);
            $existing = $this->existingVoucherForBankPayload($evidenceId, $mapped);
            if ($existing) {
                $rowsByEvidenceId[$evidenceId] = [
                    'evidence_id' => $evidenceId,
                    'evidence_source_type' => $evidenceRow['source_type'] ?? null,
                    'evidence_transaction_id' => $evidenceRow['transaction_id'] ?? null,
                    'voucher_id' => $existing['id'] ?? null,
                    'voucher_no' => $existing['voucher_no'] ?? null,
                    'voucher_date' => $existing['voucher_date'] ?? null,
                    'source_type' => $existing['source_type'] ?? null,
                    'import_type' => $existing['import_type'] ?? null,
                    'voucher_transaction_id' => $existing['transaction_id'] ?? null,
                    'summary_text' => $existing['summary_text'] ?? null,
                ];
            }
        }
        if (!$this->tableExists('ledger_evidence_links')) {
            return array_values($rowsByEvidenceId);
        }
        [$inSql, $params] = $this->placeholdersForIds($evidenceIds, 'existing_voucher_evidence');
        $sourceTypeSelect = $this->tableColumnExists('ledger_vouchers', 'source_type') ? 'v.source_type,' : 'NULL AS source_type,';
        $importSelect = $this->tableColumnExists('ledger_vouchers', 'import_type') ? 'v.import_type,' : 'NULL AS import_type,';
        $voucherTransactionSelect = $this->tableColumnExists('ledger_vouchers', 'transaction_id') ? 'v.transaction_id AS voucher_transaction_id,' : 'NULL AS voucher_transaction_id,';
        $where = ["v.deleted_at IS NULL", "l.deleted_at IS NULL", "l.target_type = 'VOUCHER'", "l.evidence_id IN ({$inSql})"];
        $stmt = $this->pdo->prepare("
            SELECT l.evidence_id AS evidence_id, l.evidence_type AS evidence_source_type,
                   NULL AS evidence_transaction_id, v.id AS voucher_id, v.voucher_no, v.voucher_date,
                   {$sourceTypeSelect} {$importSelect} {$voucherTransactionSelect} v.summary_text
            FROM ledger_evidence_links l
            INNER JOIN ledger_vouchers v ON v.id = l.target_id
            WHERE " . implode(' AND ', $where) . "
            ORDER BY v.created_at DESC, v.sort_no DESC
        ");
        $stmt->execute($params);
        foreach (($stmt->fetchAll(PDO::FETCH_ASSOC) ?: []) as $row) {
            $rowsByEvidenceId[(string) ($row['evidence_id'] ?? '')] = $row;
        }
        return array_values($rowsByEvidenceId);
    }

    private function linkExistingVouchersForEvidenceRows(array $rows): int
    {
        $actor = ActorHelper::user();
        $count = 0;
        foreach ($rows as $row) {
            $voucherId = (string) ($row['voucher_id'] ?? '');
            $evidenceId = (string) ($row['evidence_id'] ?? '');
            $transactionId = (string) (($row['evidence_transaction_id'] ?? '') ?: ($row['voucher_transaction_id'] ?? ''));
            $isBankEvidence = $this->normalizeDataType((string) ($row['evidence_source_type'] ?? '')) === 'BANK_TRANSACTION';
            if ($voucherId === '' || $evidenceId === '' || (!$isBankEvidence && $transactionId === '')) {
                continue;
            }
            $this->tagCreatedVoucher($voucherId, $evidenceId, $isBankEvidence ? '' : $transactionId, $actor);
            $this->linkVoucherToEvidence($evidenceId, $voucherId, $isBankEvidence ? '' : $transactionId, $actor);
            if (!$isBankEvidence && $transactionId !== '') {
                $this->linkVoucherToTransaction($voucherId, $transactionId, null, 'AUTO', $actor);
            }
            $this->updateEvidenceVoucherStatus($evidenceId, 'CREATED', $actor);
            $count++;
        }
        return $count;
    }
}

class EvidenceTransactionCreateResponse extends \RuntimeException
{
    public function __construct(
        public array $payload,
        public int $status = 200
    ) {
        parent::__construct('Evidence transaction create response', $status);
    }
}
