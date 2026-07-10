<?php

namespace App\Services\Ledger;

use Core\Helpers\ActorHelper;
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
        $actor = ActorHelper::user();
        $batchId = 'EV-' . date('YmdHis') . '-' . bin2hex(random_bytes(3));
        $fileName = trim((string) ($file['name'] ?? 'upload'));
        $dataType = $this->normalizeDataType((string) ($format['data_type'] ?? 'ETC'));
        $hasSourceTable = $this->tableExists('ledger_data_evidences');
        if (!$hasSourceTable && !$this->hasWritableBodyTable($dataType)) {
            return [
                'success' => false,
                'message' => '증빙 업로드 저장 기능을 사용할 수 없습니다.',
            ];
        }

        ($this->ensureEvidenceBusinessInfoColumns)();
        $this->evidenceSortHelperService->ensureEvidenceSortColumns();

        try {
            $upsertPayload = $hasSourceTable ? $this->pdo->prepare("
                INSERT INTO ledger_data_evidences
                    (id, source_type, source_key, format_id, raw_json, mapped_payload_json, payload_hash, latest_imported_at, created_at, updated_at, created_by, updated_by)
                VALUES
                    (:id, :source_type, :source_key, :format_id, :raw_json, :mapped_payload_json, SHA2(COALESCE(:mapped_payload_json_hash, ''), 256), NOW(), NOW(), NOW(), :created_by, :updated_by)
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
            ") : null;
            $upsertProcessing = null;
            if ($this->tableExists('ledger_evidence_processing')) {
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
            }
            $dualWrite = new EvidenceDualWriteService($this->pdo);
            $counters = $this->evidenceBatchSaveService->createBatchCounters();
            $processedRows = 0;
            if ($hasSourceTable) {
                $this->evidenceUploadService->preloadExistingSeedRowsForUploadRows($rows, $dataType);
            }
            $this->pdo->beginTransaction();

            foreach ($rows as $row) {
                $this->evidenceUploadService->assertUploadNotCanceled($cancelToken);
                if (connection_aborted()) {
                    throw new \RuntimeException('브라우저 연결이 종료되었습니다.');
                }

                $rowState = $this->evidenceBatchSaveService->buildUploadRowState($row, $dataType);
                $parsedPayload = $rowState['parsed_payload'];
                $processStatus = $rowState['process_status'];
                $evidenceStatus = (string) ($rowState['evidence_status'] ?? '');
                $voucherStatus = $rowState['voucher_status'];
                $sourceKey = $rowState['source_key'];
                $rawJson = $rowState['raw_json'];
                $errorMessage = $rowState['error_message'];
                $existingSeed = $hasSourceTable
                    ? $this->evidenceBatchSaveService->findExistingUploadSeed($dataType, $sourceKey, $parsedPayload)
                    : $this->findExistingBodySeed($dataType, $sourceKey);
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
                $targetTable = $this->bodyTableForDataType($dataType);
                if ($targetTable === null) {
                    throw new \RuntimeException('evidence body table not mapped: ' . $dataType);
                }
                $sortNo = (int) ($existingSeed['sort_no'] ?? 0);
                if ($sortNo < 1) {
                    $sortNo = $this->evidenceBatchSaveService->nextBodySortNo($targetTable);
                }
                $evidenceSortNo = (int) ($existingSeed['evidence_sort_no'] ?? 0);
                if ($evidenceSortNo < 1) {
                    $evidenceSortNo = $this->evidenceBatchSaveService->nextEvidenceSortNo($actor);
                }
                $this->evidenceBatchSaveService->incrementPersisted($counters, $existingSeed !== null);
                $persistParams = $this->evidenceBatchSaveService->buildPersistParams(
                    $evidenceId,
                    $dataType,
                    (string) ($format['id'] ?? ''),
                    $sourceKey,
                    $sortNo,
                    $evidenceSortNo,
                    $parsedPayload,
                    $processStatus,
                    $evidenceStatus,
                    $voucherStatus,
                    $errorMessage,
                    $rawJson,
                    $parsedJson,
                    $actor
                );

                if ($upsertPayload instanceof \PDOStatement) {
                    $upsertPayload->execute([
                        ':id' => $evidenceId,
                        ':source_type' => $dataType,
                        ':source_key' => $sourceKey,
                        ':format_id' => (string) ($format['id'] ?? ''),
                        ':raw_json' => $rawJson,
                        ':mapped_payload_json' => $parsedJson,
                        ':mapped_payload_json_hash' => $parsedJson,
                        ':created_by' => $actor,
                        ':updated_by' => $actor,
                    ]);
                }
                if ($upsertProcessing instanceof \PDOStatement) {
                    $upsertProcessing->execute([
                        ':processing_id' => UuidHelper::generate(),
                        ':source_type' => $dataType,
                        ':id' => $evidenceId,
                        ':processing_status' => $processStatus === 'ERROR' ? 'ERROR' : 'READY',
                        ':review_status' => 'NORMAL',
                        ':error_message' => $errorMessage,
                    ]);
                }

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
                    'sort_no' => $persistParams[':sort_no'],
                    'evidence_sort_no' => $persistParams[':evidence_sort_no'],
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
                    $evidenceStatus,
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

    private function hasWritableBodyTable(string $dataType): bool
    {
        $table = $this->bodyTableForDataType($dataType);
        return $table !== null && $this->tableExists($table);
    }

    private function findExistingBodySeed(string $dataType, ?string $sourceKey): ?array
    {
        $table = $this->bodyTableForDataType($dataType);
        $sourceKey = trim((string) $sourceKey);
        if ($table === null || !$this->tableExists($table) || $sourceKey === '') {
            return null;
        }

        $sourceKeyColumn = null;
        if ($this->tableColumnExists($table, 'external_key')) {
            $sourceKeyColumn = 'external_key';
        } elseif ($this->tableColumnExists($table, 'source_key')) {
            $sourceKeyColumn = 'source_key';
        }
        if ($sourceKeyColumn === null) {
            return null;
        }

        $sortNoExpr = $this->tableColumnExists($table, 'sort_no') ? 'body.sort_no' : 'NULL';
        $evidenceSortNoExpr = $this->tableColumnExists($table, 'evidence_sort_no') ? 'body.evidence_sort_no' : 'NULL';
        $updatedAtExpr = $this->tableColumnExists($table, 'updated_at') ? 'body.updated_at' : 'NULL';
        $createdAtExpr = $this->tableColumnExists($table, 'created_at') ? 'body.created_at' : 'NULL';

        $processingJoin = '';
        $transactionStatusExpr = "'READY'";
        if ($this->tableExists('ledger_evidence_processing')) {
            $processingJoin = "
                LEFT JOIN ledger_evidence_processing pr
                    ON pr.evidence_id = body.id
                   AND pr.deleted_at IS NULL";
            $transactionStatusExpr = "COALESCE(pr.processing_status, 'READY')";
        }

        $stmt = $this->pdo->prepare("
            SELECT
                body.id,
                body.{$sourceKeyColumn} AS source_key,
                {$sortNoExpr} AS sort_no,
                {$evidenceSortNoExpr} AS evidence_sort_no,
                {$transactionStatusExpr} AS transaction_status,
                CASE WHEN vx.target_id IS NULL THEN 'WAITING' ELSE 'LINKED' END AS voucher_status,
                tx.target_id AS transaction_id
            FROM {$table} body
            {$processingJoin}
            LEFT JOIN ledger_evidence_links tx
                ON tx.evidence_id = body.id
               AND tx.target_type = 'TRANSACTION'
               AND tx.deleted_at IS NULL
            LEFT JOIN ledger_evidence_links vx
                ON vx.evidence_id = body.id
               AND vx.target_type = 'VOUCHER'
               AND vx.deleted_at IS NULL
            WHERE body.{$sourceKeyColumn} = :source_key
              AND body.deleted_at IS NULL
            ORDER BY {$updatedAtExpr} DESC, {$createdAtExpr} DESC
            LIMIT 1
        ");
        $stmt->execute([':source_key' => $sourceKey]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        if (!is_array($row)) {
            return null;
        }

        return [
            'id' => (string) ($row['id'] ?? ''),
            'source_key' => (string) ($row['source_key'] ?? ''),
            'raw_json' => '',
            'mapped_payload_json' => '',
            'sort_no' => (int) ($row['sort_no'] ?? 0),
            'evidence_sort_no' => (int) ($row['evidence_sort_no'] ?? 0),
            'transaction_status' => (string) ($row['transaction_status'] ?? 'READY'),
            'voucher_status' => (string) ($row['voucher_status'] ?? 'WAITING'),
            'transaction_id' => (string) ($row['transaction_id'] ?? ''),
        ];
    }

    private function bodyTableForDataType(string $dataType): ?string
    {
        return match ($this->normalizeDataType($dataType)) {
            'BANK_TRANSACTION' => 'ledger_evidence_bank_transaction',
            'TAX_INVOICE' => 'ledger_evidence_tax_invoice',
            'TAX_INVOICE_MANUAL' => 'ledger_evidence_tax_invoice_manual',
            'CASH_RECEIPT' => 'ledger_evidence_cash_receipt',
            'CARD_HOMETAX' => 'ledger_evidence_card_hometax',
            'CARD', 'CARD_STATEMENT', 'CARD_APPROVAL' => 'ledger_evidence_card_statement',
            'EMPLOYEE_EXPENSE' => 'ledger_evidence_employee_expense',
            'PAYROLL', 'PAYROLL_WITHHOLDING' => 'ledger_evidence_payroll',
            'BUSINESS_INCOME' => 'ledger_evidence_business_income',
            'BUSINESS_DATA' => 'ledger_evidence_cash_sales',
            'CONSTRUCTION' => 'ledger_evidence_daily_worker',
            'SHOPPING_ORDER', 'IMPORT_INVOICE' => 'ledger_evidence_business_data',
            default => null,
        };
    }

    private function tableColumnExists(string $table, string $column): bool
    {
        $stmt = $this->pdo->prepare("
            SELECT 1
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = :table_name
              AND COLUMN_NAME = :column_name
            LIMIT 1
        ");
        $stmt->execute([
            ':table_name' => $table,
            ':column_name' => $column,
        ]);

        return (bool) $stmt->fetchColumn();
    }

    private function tableExists(string $table): bool
    {
        $stmt = $this->pdo->prepare("
            SELECT 1
            FROM information_schema.TABLES
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = :table_name
            LIMIT 1
        ");
        $stmt->execute([':table_name' => $table]);

        return (bool) $stmt->fetchColumn();
    }
}
