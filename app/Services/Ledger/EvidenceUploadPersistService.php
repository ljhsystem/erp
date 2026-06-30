<?php

namespace App\Services\Ledger;

use Core\Helpers\ActorHelper;
use Core\Helpers\AuthHelper;
use Core\Helpers\UuidHelper;
use PDO;

class EvidenceUploadPersistService
{
    public function __construct(
        private PDO $pdo,
        private EvidenceUploadService $evidenceUploadService,
        private EvidenceBatchSaveService $evidenceBatchSaveService,
        private EvidencePayloadHelperService $evidencePayloadHelperService,
        private $ensureEvidenceBusinessInfoColumns,
        private EvidenceSortHelperService $evidenceSortHelperService,
        private $normalizeDataType,
        private int $chunkSize = 500
    ) {
    }

    public function storeUploadBatch(array $format, array $file, array $rows, string $cancelToken = ''): array
    {
        $actor = AuthHelper::userId() ?? ActorHelper::user();
        $batchId = 'EV-' . date('YmdHis') . '-' . bin2hex(random_bytes(3));
        $fileName = trim((string) ($file['name'] ?? 'upload'));
        $dataType = $this->normalizeDataType((string) ($format['data_type'] ?? 'ETC'));

        ($this->ensureEvidenceBusinessInfoColumns)();
        $this->evidenceSortHelperService->ensureEvidenceSortColumns();

        try {
            $upsertPayload = $this->pdo->prepare("
                INSERT INTO ledger_evidence_payloads
                    (id, evidence_type, evidence_id, source_key, format_id, raw_json, mapped_payload_json, payload_hash, latest_imported_at, created_at, updated_at, created_by, updated_by)
                VALUES
                    (:payload_id, :source_type, :id, :source_key, :format_id, :raw_json, :mapped_payload_json, SHA2(COALESCE(:mapped_payload_json_hash, ''), 256), NOW(), NOW(), NOW(), :created_by, :updated_by)
                ON DUPLICATE KEY UPDATE
                    source_key = VALUES(source_key),
                    format_id = VALUES(format_id),
                    raw_json = VALUES(raw_json),
                    mapped_payload_json = VALUES(mapped_payload_json),
                    payload_hash = SHA2(COALESCE(VALUES(mapped_payload_json), ''), 256),
                    latest_imported_at = NOW(),
                    deleted_at = NULL,
                    deleted_by = NULL,
                    updated_at = NOW(),
                    updated_by = VALUES(updated_by)
            ");
            $upsertProcessing = $this->pdo->prepare("
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
            $dualWrite = new EvidenceDualWriteService($this->pdo);
            $counters = $this->evidenceBatchSaveService->createBatchCounters();
            $nextStatusSortNo = $this->evidenceBatchSaveService->nextEvidenceJsonSortNo('_status_sort_no', $dataType);
            $nextCreateSortNo = $this->evidenceBatchSaveService->nextEvidenceJsonSortNo('_create_sort_no');
            $processedRows = 0;
            $this->evidenceUploadService->preloadExistingSeedRowsForUploadRows($rows, $dataType);
            $this->pdo->beginTransaction();

            foreach ($rows as $row) {
                $this->evidenceUploadService->assertUploadNotCanceled($cancelToken);
                if (connection_aborted()) {
                    throw new \RuntimeException('???????? ???????????????');
                }

                $rowState = $this->evidenceBatchSaveService->buildUploadRowState($row, $dataType);
                $parsedPayload = $rowState['parsed_payload'];
                $processStatus = $rowState['process_status'];
                $voucherStatus = $rowState['voucher_status'];
                $sourceKey = $rowState['source_key'];
                $rawJson = $rowState['raw_json'];
                $errorMessage = $rowState['error_message'];
                $existingSeed = $this->evidenceBatchSaveService->findExistingUploadSeed($dataType, $sourceKey, $parsedPayload);
                $existingMappedPayload = $this->evidenceBatchSaveService->existingMappedPayload($existingSeed);
                $this->evidenceBatchSaveService->assignEvidenceJsonSortNo($parsedPayload, $existingMappedPayload, '_status_sort_no', $nextStatusSortNo);
                $this->evidenceBatchSaveService->assignEvidenceJsonSortNo($parsedPayload, $existingMappedPayload, '_create_sort_no', $nextCreateSortNo);
                $parsedJson = $this->evidencePayloadHelperService->jsonEncodeForStorage($parsedPayload);

                if ($this->evidenceBatchSaveService->isUnchangedExistingSeed($existingSeed, $rawJson, $parsedJson)) {
                    $this->evidenceBatchSaveService->incrementUnchanged($counters);
                    $this->evidenceBatchSaveService->commitUploadChunkIfNeeded(++$processedRows, $this->chunkSize);
                    continue;
                }

                $protectedSeedInfo = $this->evidenceBatchSaveService->protectedExistingSeedInfo($existingSeed);
                if ($protectedSeedInfo['is_protected']) {
                    $this->evidenceBatchSaveService->incrementProtectedSkip($counters, $protectedSeedInfo);
                    $this->evidenceBatchSaveService->commitUploadChunkIfNeeded(++$processedRows, $this->chunkSize);
                    continue;
                }

                $evidenceId = (string) ($existingSeed['id'] ?? UuidHelper::generate());
                $this->evidenceBatchSaveService->incrementPersisted($counters, $existingSeed !== null);
                $persistParams = $this->evidenceBatchSaveService->buildPersistParams(
                    $evidenceId,
                    $dataType,
                    (string) ($format['id'] ?? ''),
                    $sourceKey,
                    $parsedPayload,
                    $processStatus,
                    $voucherStatus,
                    $errorMessage,
                    $rawJson,
                    $parsedJson,
                    $actor
                );

                $upsertPayload->execute([
                    ':payload_id' => UuidHelper::generate(),
                    ':source_type' => $dataType,
                    ':id' => $evidenceId,
                    ':source_key' => $sourceKey,
                    ':format_id' => (string) ($format['id'] ?? ''),
                    ':raw_json' => $rawJson,
                    ':mapped_payload_json' => $parsedJson,
                    ':mapped_payload_json_hash' => $parsedJson,
                    ':created_by' => $actor,
                    ':updated_by' => $actor,
                ]);
                $upsertProcessing->execute([
                    ':processing_id' => UuidHelper::generate(),
                    ':source_type' => $dataType,
                    ':id' => $evidenceId,
                    ':processing_status' => $processStatus === 'ERROR' ? 'ERROR' : 'READY',
                    ':review_status' => 'NORMAL',
                    ':error_message' => $errorMessage,
                ]);

                $legacyForEvidence = [
                    'id' => $evidenceId,
                    'source_type' => $dataType,
                    'source_key' => $sourceKey,
                    'format_id' => (string) ($format['id'] ?? ''),
                    'evidence_date' => $persistParams[':evidence_date'],
                    'client_id' => $persistParams[':client_id'],
                    'project_id' => $persistParams[':project_id'],
                    'employee_id' => $persistParams[':employee_id'],
                    'bank_account_id' => $persistParams[':bank_account_id'],
                    'card_id' => $persistParams[':card_id'],
                    'client_name' => $persistParams[':client_name'],
                    'project_name' => $persistParams[':project_name'],
                    'employee_name' => $persistParams[':employee_name'],
                    'bank_account_name' => $persistParams[':bank_account_name'],
                    'card_name' => $persistParams[':card_name'],
                    'currency' => $persistParams[':currency'],
                    'supply_amount' => $persistParams[':supply_amount'],
                    'vat_amount' => $persistParams[':vat_amount'],
                    'total_amount' => $persistParams[':total_amount'],
                    'create_sort_no' => $persistParams[':create_sort_no'],
                    'status_sort_no' => $persistParams[':status_sort_no'],
                    'evidence_status' => $persistParams[':evidence_status'],
                    'transaction_status' => $persistParams[':transaction_status'],
                    'voucher_status' => $persistParams[':voucher_status'],
                    'error_message' => $persistParams[':error_message'],
                    'raw_json' => $rawJson,
                    'mapped_payload_json' => $parsedJson,
                    'created_by' => $actor,
                    'updated_by' => $actor,
                    'deleted_at' => null,
                ];
                $dualWriteResult = $dualWrite->syncFromLegacyRow($legacyForEvidence);
                if (($dualWriteResult['dual_write_status'] ?? '') !== 'success') {
                    throw new \RuntimeException('evidence body update failed: ' . (string) ($dualWriteResult['message'] ?? 'unknown'));
                }

                $cachedSeed = $this->evidenceBatchSaveService->buildCachedSeed(
                    $evidenceId,
                    $sourceKey,
                    $rawJson,
                    $parsedJson,
                    $processStatus,
                    $voucherStatus
                );
                if ($cachedSeed !== null) {
                    $this->evidenceUploadService->rememberExistingSeedRow($dataType, $sourceKey, $cachedSeed, $parsedPayload);
                }

                $this->evidenceBatchSaveService->incrementErrorIfNeeded($counters, $processStatus);
                $this->evidenceBatchSaveService->commitUploadChunkIfNeeded(++$processedRows, $this->chunkSize);
            }

            if ($this->pdo->inTransaction()) {
                $this->pdo->commit();
            }
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }

        return $this->evidenceBatchSaveService->buildBatchResult(
            $counters,
            $batchId,
            $fileName,
            $dataType,
            (string) ($format['id'] ?? ''),
            count($rows)
        );
    }

    private function normalizeDataType(string $type): string
    {
        return ($this->normalizeDataType)($type);
    }
}
