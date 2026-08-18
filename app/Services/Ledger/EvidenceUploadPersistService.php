<?php

namespace App\Services\Ledger;

use App\Models\Ledger\EvidenceBodyStorageModel;
use App\Models\Ledger\EvidenceSchemaModel;
use Core\Helpers\ActorHelper;
use Core\Helpers\UuidHelper;
use PDO;

class EvidenceUploadPersistService
{
    private EvidenceBodyStorageModel $bodyStorageModel;
    private EvidenceSchemaModel $schemaModel;
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
        $this->bodyStorageModel = new EvidenceBodyStorageModel($pdo);
        $this->schemaModel = new EvidenceSchemaModel($pdo);
    }

    public function storeUploadBatch(array $format, array $file, array $rows, string $cancelToken = ''): array
    {
        $actor = ActorHelper::user();
        $batchId = 'EV-' . date('YmdHis') . '-' . bin2hex(random_bytes(3));
        $fileName = trim((string) ($file['name'] ?? 'upload'));
        $dataType = $this->normalizeDataType((string) ($format['data_type'] ?? 'ETC'));
        if (!$this->hasWritableBodyTable($dataType)) {
            return [
                'success' => false,
                'message' => '증빙 업로드 저장 기능을 사용할 수 없습니다.',
            ];
        }

        ($this->ensureEvidenceBusinessInfoColumns)();
        $this->evidenceSortHelperService->ensureEvidenceSortColumns();

        try {
            $bodyWriter = new EvidenceBodyWriteService($this->pdo);
            $counters = $this->evidenceBatchSaveService->createBatchCounters();
            $processedRows = 0;
            $seenExternalKeys = [];
            $this->pdo->beginTransaction();

            foreach ($rows as $row) {
                $this->evidenceUploadService->assertUploadNotCanceled($cancelToken);
                if (connection_aborted()) {
                    throw new \RuntimeException('브라우저 연결이 종료되었습니다.');
                }

                $rowState = $this->evidenceBatchSaveService->buildUploadRowState(
                    $row,
                    $dataType,
                    is_array($format['evidence_status_column_display_name'] ?? null) ? $format['evidence_status_column_display_name'] : [],
                    is_array($format['evidence_status_column_requirement_policy'] ?? null) ? $format['evidence_status_column_requirement_policy'] : []
                );
                $parsedPayload = $rowState['parsed_payload'];
                $processStatus = $rowState['process_status'];
                $evidenceStatus = (string) ($rowState['evidence_status'] ?? '');
                $voucherStatus = $rowState['voucher_status'];
                $sourceKey = $rowState['source_key'];
                $rawJson = $rowState['raw_json'];
                $errorMessage = $rowState['error_message'];
                if ($processStatus === 'ERROR') {
                    $this->evidenceBatchSaveService->incrementError($counters, $parsedPayload, (string) ($errorMessage ?? '행 검증 오류'));
                    $this->evidenceBatchSaveService->commitUploadChunkIfNeeded(++$processedRows, $this->chunkSize);
                    continue;
                }
                $sourceKey = trim((string) $sourceKey);
                $parsedJson = $this->evidencePayloadHelperService->jsonEncodeForStorage($parsedPayload);
                if (isset($seenExternalKeys[$sourceKey])) {
                    $this->evidenceBatchSaveService->incrementDuplicate($counters, $parsedPayload, '파일 내부 동일 원본');
                    $this->evidenceBatchSaveService->commitUploadChunkIfNeeded(++$processedRows, $this->chunkSize);
                    continue;
                }
                $seenExternalKeys[$sourceKey] = true;

                $existingSeeds = $this->bodyStorageModel->findUploadSeedsByImportType($dataType, $sourceKey);
                if ($existingSeeds !== []) {
                    $activeSeeds = array_values(array_filter($existingSeeds, static fn(array $seed): bool => empty($seed['deleted_at'])));
                    $deletedDuplicate = $activeSeeds === [];
                    $incomingDigest = $this->evidenceUploadService->externalKeyContentDigest($parsedPayload, $dataType);
                    $existingDigests = array_values(array_unique(array_map(
                        fn(array $seed): string => $this->evidenceUploadService->externalKeyContentDigest($seed, $dataType),
                        $existingSeeds
                    )));
                    $conflict = count($existingSeeds) > 1 || !in_array($incomingDigest, $existingDigests, true);
                    $reason = count($existingSeeds) > 1
                        ? '동일 외부원본식별키가 기존 DB에 여러 건 존재함'
                        : ($deletedDuplicate ? '삭제자료 중복' : ($conflict ? '동일 키 원본내용 충돌' : '기존 동일 원본'));
                    $this->evidenceBatchSaveService->incrementDuplicate(
                        $counters,
                        $parsedPayload,
                        $reason,
                        $deletedDuplicate,
                        $conflict
                    );
                    $this->evidenceBatchSaveService->commitUploadChunkIfNeeded(++$processedRows, $this->chunkSize);
                    continue;
                }
                $evidenceId = UuidHelper::generate();
                $targetTable = $this->bodyTableForDataType($dataType);
                if ($targetTable === null) {
                    throw new \RuntimeException('evidence body table not mapped: ' . $dataType);
                }
                $sortNo = $this->evidenceBatchSaveService->nextBodySortNo($targetTable);
                $persistParams = $this->evidenceBatchSaveService->buildPersistParams(
                    $evidenceId,
                    $dataType,
                    (string) ($format['id'] ?? ''),
                    $sourceKey,
                    $sortNo,
                    $parsedPayload,
                    $processStatus,
                    $evidenceStatus,
                    $voucherStatus,
                    $errorMessage,
                    $rawJson,
                    $parsedJson,
                    $actor
                );

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
                try {
                    $writeResult = $bodyWriter->save($legacyForEvidence);
                    if (($writeResult['status'] ?? '') !== 'success') {
                        throw new \RuntimeException('evidence body insert failed: ' . (string) ($writeResult['message'] ?? 'unknown'));
                    }
                } catch (\PDOException $e) {
                    if ((string) $e->getCode() !== '23000' || !str_contains(strtolower($e->getMessage()), 'duplicate')) {
                        throw $e;
                    }
                    $this->evidenceBatchSaveService->incrementDuplicate($counters, $parsedPayload, '동시 업로드 동일 원본');
                    $this->evidenceBatchSaveService->commitUploadChunkIfNeeded(++$processedRows, $this->chunkSize);
                    continue;
                }

                $this->evidenceBatchSaveService->incrementPersisted($counters);
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

        $row = $this->bodyStorageModel->findUploadSeedByImportType($dataType, $sourceKey);
        if (!is_array($row)) {
            return null;
        }

        return [
            'id' => (string) ($row['id'] ?? ''),
            'source_key' => (string) ($row['source_key'] ?? ''),
            'raw_json' => '',
            'mapped_payload_json' => '',
            'sort_no' => (int) ($row['sort_no'] ?? 0),
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
        return $this->schemaModel->columnExists($table, $column);
    }

    private function tableExists(string $table): bool
    {
        return $this->schemaModel->tableExists($table);
    }
}
