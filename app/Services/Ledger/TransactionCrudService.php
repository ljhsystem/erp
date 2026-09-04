<?php

namespace App\Services\Ledger;

use App\Models\Ledger\TransactionItemModel;
use App\Models\Ledger\TransactionFileModel;
use App\Models\Ledger\TransactionModel;
use App\Models\Ledger\TransactionSettlementModel;
use App\Models\Ledger\EvidenceLinkModel;
use App\Repositories\Ledger\EvidenceSourceRepository;
use App\Services\File\FileService;
use Core\Helpers\ActorHelper;
use Core\Helpers\SequenceHelper;
use Core\Helpers\UuidHelper;
use Core\LoggerFactory;
use PDO;
use Psr\Log\LoggerInterface;

class TransactionCrudService
{
    private $failureInjector;
    private TransactionModel $transactionModel;
    private TransactionItemModel $transactionItemModel;
    private TransactionSettlementModel $transactionSettlementModel;
    private TransactionFileModel $transactionFileModel;
    private FileService $fileService;
    private EvidenceLinkModel $evidenceLinkModel;
    private EvidenceSourceRepository $evidenceSourceRepository;
    private TransactionEvidenceReferenceService $evidenceReferenceService;
    private TransactionReferenceValidatorService $referenceValidator;
    private LoggerInterface $logger;

    public function __construct(private readonly PDO $pdo, ?callable $failureInjector = null)
    {
        $this->failureInjector = $failureInjector;
        $this->transactionModel = new TransactionModel($pdo);
        $this->transactionItemModel = new TransactionItemModel($pdo);
        $this->transactionSettlementModel = new TransactionSettlementModel($pdo);
        $this->transactionFileModel = new TransactionFileModel($pdo);
        $this->fileService = new FileService($pdo);
        $this->evidenceLinkModel = new EvidenceLinkModel($pdo);
        $this->evidenceSourceRepository = new EvidenceSourceRepository($pdo);
        $this->evidenceReferenceService = new TransactionEvidenceReferenceService($pdo);
        $this->referenceValidator = new TransactionReferenceValidatorService($pdo);
        $this->logger = LoggerFactory::getLogger('service-ledger-transaction');
    }

    private function checkpoint(string $name): void
    {
        if ($this->failureInjector !== null) ($this->failureInjector)('transaction.' . $name);
    }

    public function getList(array $filters): array
    {
        $filters = $this->normalizeSearchFilters($filters);

        $allowedKeys = [
            'business_unit',
            'transaction_direction',
            'status',
            'project_id',
            'client_id',
            'date_from',
            'date_to',
            'updated_from',
            'updated_to',
            'search_conditions',
            '_start',
            '_length',
            '_order_field',
            '_order_direction',
        ];

        $normalized = [];
        foreach ($allowedKeys as $key) {
            if (!array_key_exists($key, $filters)) {
                continue;
            }

            $value = is_string($filters[$key]) ? trim($filters[$key]) : $filters[$key];
            if ($value === '' || $value === null || $value === []) {
                continue;
            }

            $normalized[$key] = $value;
        }

        $rows = $this->transactionModel->getList($normalized);

        return array_map(function (array $row): array {
            $row['description'] = $row['transaction_description'] ?? '';
            $row['note'] = $row['transaction_note'] ?? '';
            $row['memo'] = $row['transaction_memo'] ?? '';
            $row['exchange_rate'] = $row['transaction_exchange_rate'] ?? null;
            $row['foreign_amount'] = $row['transaction_foreign_amount'] ?? 0;
            $row['supply_amount'] = $row['transaction_supply_amount'] ?? 0;
            $row['settlement_amount'] = $row['transaction_settlement_amount'] ?? 0;
            $row['final_amount'] = $row['transaction_final_amount'] ?? 0;
            $row['base_amount'] = $row['supply_amount'];
            $row['adjustment_amount'] = $row['settlement_amount'];
            $row['total_amount'] = $row['final_amount'];

            return $row;
        }, $rows);
    }

    public function reorder(array $changes): bool
    {
        return $this->logged('TRANSACTION_REORDERED','reorder',['requested_count'=>count($changes)],fn():bool=>$this->reorderInternal($changes));
    }

    private function reorderInternal(array $changes): bool
    {
        if ($changes === []) {
            throw new \InvalidArgumentException('정렬 데이터가 없습니다.');
        }

        $normalized = [];
        foreach ($changes as $row) {
            $id = trim((string) ($row['id'] ?? ''));
            $sortNo = filter_var($row['newSortNo'] ?? null, FILTER_VALIDATE_INT);
            if ($id === '' || $sortNo === false || $sortNo < 1) {
                throw new \InvalidArgumentException('정렬 저장에 필요한 거래 ID 또는 순번이 올바르지 않습니다.');
            }
            if (isset($normalized[$id])) {
                throw new \InvalidArgumentException('중복된 거래 정렬 데이터가 있습니다.');
            }
            $normalized[$id] = $sortNo;
        }

        $ownsTransaction = !$this->pdo->inTransaction();
        try {
            if ($ownsTransaction) {
                $this->pdo->beginTransaction();
            }
            foreach ($normalized as $id => $sortNo) {
                $this->transactionModel->updateSortNo($id, $sortNo + 1000000);
            }
            foreach ($normalized as $id => $sortNo) {
                $this->transactionModel->updateSortNo($id, $sortNo);
            }
            if ($ownsTransaction) {
                $this->pdo->commit();
            }
            return true;
        } catch (\Throwable $e) {
            if ($ownsTransaction && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    public function getPage(array $filters): array
    {
        $rows = $this->getList($filters);
        $normalizedFilters = $this->normalizeSearchFilters($filters);
        $businessFilters = array_diff_key($normalizedFilters, [
            '_start' => true,
            '_length' => true,
            '_order_field' => true,
            '_order_direction' => true,
            'draw' => true,
            'start' => true,
            'length' => true,
            'columns' => true,
            'order' => true,
            'search' => true,
            'sort_field' => true,
            'sort_direction' => true,
        ]);
        $recordsFiltered = $this->transactionModel->lastFilteredCount();
        return [
            'rows' => $rows,
            'records_total' => $businessFilters === []
                ? $recordsFiltered
                : $this->transactionModel->activeTotalCount(),
            'records_filtered' => $recordsFiltered,
        ];
    }

    private function normalizeSearchFilters(array $filters): array
    {
        if ($filters === []) {
            return [];
        }

        $page = array_intersect_key($filters, ['_start' => true, '_length' => true]);
        unset($filters['_start'], $filters['_length']);

        $keys = array_keys($filters);
        $isList = $keys === range(0, count($filters) - 1);
        if (!$isList) {
            return $filters + $page;
        }

        $normalized = [];
        $searchConditions = [];
        $searchableFields = [
            'sort_no',
            'business_unit',
            'business_unit_name',
            'transaction_direction',
            'transaction_direction_name',
            'operation_type',
            'operation_type_name',
            'transaction_date',
            'bank_account_id',
            'bank_account_name',
            'card_id',
            'card_name',
            'team_id',
            'team_name',
            'employee_id',
            'employee_name',
            'project_id',
            'project_name',
            'client_id',
            'client_name',
            'foreign_amount',
            'supply_amount',
            'settlement_amount',
            'final_amount',
            'transaction_supply_amount',
            'transaction_settlement_amount',
            'transaction_final_amount',
            'transaction_foreign_amount',
            'transaction_description',
            'currency',
            'currency_name',
            'transaction_exchange_rate',
            'status',
            'transaction_note',
            'transaction_memo',
            'created_at',
            'created_by',
            'updated_at',
            'updated_by',
            'deleted_at',
            'deleted_by',
        ];

        foreach ($filters as $filter) {
            if (!is_array($filter)) {
                continue;
            }

            $field = (string) ($filter['field'] ?? '');
            $value = $filter['value'] ?? null;
            if ($field === '' || $value === null || $value === '') {
                continue;
            }

            if (is_array($value) && isset($value['start'], $value['end'])) {
                if ($field === 'transaction_date') {
                    $normalized['date_from'] = (string) $value['start'];
                    $normalized['date_to'] = (string) $value['end'];
                } elseif ($field === 'updated_at') {
                    $normalized['updated_from'] = (string) $value['start'];
                    $normalized['updated_to'] = (string) $value['end'];
                }
                continue;
            }

            if (in_array($field, $searchableFields, true)) {
                $searchConditions[] = [
                    'field' => $field,
                    'value' => trim((string) $value),
                ];
            }
        }

        if ($searchConditions !== []) {
            $normalized['search_conditions'] = $searchConditions;
        }

        return $normalized + $page;
    }

    public function getById(string $id): ?array
    {
        $transaction = $this->transactionModel->getById($id);
        if (!$transaction) {
            return null;
        }

        $transaction['items'] = $this->transactionItemModel->getByTransactionId($id);
        $transaction['settlements'] = $this->transactionSettlementModel->getByTransactionId($id);
        $transaction['files'] = $this->transactionFileModel->getByTransactionId($id);
        $transaction['links'] = [];
        $transaction['linked_evidences'] = $this->evidenceReferenceService->hydrateLinks(
            $this->evidenceLinkModel->getTransactionEvidences($id)
        );

        $transaction['description'] = $transaction['transaction_description'] ?? '';
        $transaction['note'] = $transaction['transaction_note'] ?? '';
        $transaction['memo'] = $transaction['transaction_memo'] ?? '';
        $transaction['exchange_rate'] = $transaction['transaction_exchange_rate'] ?? null;
        $transaction['foreign_amount'] = $transaction['transaction_foreign_amount'] ?? 0;
        $transaction['supply_amount'] = $transaction['transaction_supply_amount'] ?? 0;
        $transaction['settlement_amount'] = $transaction['transaction_settlement_amount'] ?? 0;
        $transaction['final_amount'] = $transaction['transaction_final_amount'] ?? 0;
        $transaction['base_amount'] = $transaction['supply_amount'];
        $transaction['adjustment_amount'] = $transaction['settlement_amount'];
        $transaction['total_amount'] = $transaction['final_amount'];

        return $transaction;
    }

    public function getFileDownloadPayload(string $fileId): ?array
    {
        $file = $this->transactionFileModel->getById($fileId);
        if (!$file || empty($file['file_path'])) {
            return null;
        }

        $absolutePath = \Core\storage_resolve_abs((string) $file['file_path']);
        if (!$absolutePath || !is_file($absolutePath)) {
            return null;
        }

        $fileName = (string) ($file['file_name'] ?: basename($absolutePath));
        $mime = mime_content_type($absolutePath) ?: 'application/octet-stream';
        $disposition = in_array($mime, [
            'image/jpeg',
            'image/png',
            'image/gif',
            'image/webp',
            'application/pdf',
            'text/plain',
        ], true) ? 'inline' : 'attachment';

        return [
            'absolute_path' => $absolutePath,
            'file_name' => $fileName,
            'mime' => $mime,
            'disposition' => $disposition,
            'size' => filesize($absolutePath),
        ];
    }

    public function getTrashList(): array
    {
        return $this->transactionModel->getTrashList();
    }

    public function restoreTransactions(array $ids): void
    {
        $this->logged('TRANSACTION_RESTORED','restore',['requested_count'=>count($ids)],function()use($ids):bool{$this->restoreTransactionsInternal($ids);return true;});
    }

    private function restoreTransactionsInternal(array $ids): void
    {
        if ($ids === []) {
            return;
        }

        $actor = ActorHelper::user();
        $actorId = is_array($actor) ? ($actor['id'] ?? null) : $actor;

        try {
            $this->pdo->beginTransaction();

            foreach ($ids as $id) {
                if ($this->evidenceLinkModel->transactionRestoreHasConflict($id)) {
                    throw new \RuntimeException('현재 증빙 연결과 충돌하는 거래는 복원할 수 없습니다.');
                }
                $this->transactionModel->update($id, [
                    'deleted_at' => null,
                    'deleted_by' => null,
                    'updated_at' => date('Y-m-d H:i:s'),
                    'updated_by' => $actorId,
                ]);
                $this->evidenceLinkModel->restoreByTarget('TRANSACTION', $id);
            }

            $this->pdo->commit();
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            throw $e;
        }
    }

    public function restoreAllTransactions(): void
    {
        $this->restoreTransactions($this->deletedTransactionIds());
    }

    public function purgeTransactions(array $ids): void
    {
        $this->logged('TRANSACTION_PURGED','purge',['requested_count'=>count($ids)],function()use($ids):bool{$this->purgeTransactionsInternal($ids);return true;},true);
    }

    private function purgeTransactionsInternal(array $ids): void
    {
        if ($ids === []) {
            return;
        }

        $filePaths = [];

        try {
            $this->pdo->beginTransaction();

            foreach ($ids as $id) {
                $transaction = $this->transactionModel->getByIdForUpdate($id) ?: [];
                if (empty($transaction['deleted_at'])) {
                    throw new \RuntimeException('휴지통에 있는 거래만 영구삭제할 수 있습니다.');
                }
                if (($transaction['status'] ?? '') === 'closed') {
                    throw new \RuntimeException('마감된 거래는 영구삭제할 수 없습니다.');
                }
                if ($this->journalLearningEventCount($id) > 0) {
                    throw new \RuntimeException('분개 학습 이력이 있는 거래는 영구삭제할 수 없습니다.');
                }
                foreach ($this->transactionFileModel->getByTransactionId($id) as $file) {
                    if (!empty($file['file_path'])) {
                        $filePaths[] = (string) $file['file_path'];
                    }
                }

                $this->transactionItemModel->hardDeleteByTransactionId($id);
                $this->transactionSettlementModel->hardDeleteByTransactionId($id);
                $this->evidenceLinkModel->purgeByTransactionId($id);
                $this->transactionModel->hardDelete($id);
            }

            $this->pdo->commit();

            foreach (array_unique($filePaths) as $filePath) {
                $this->fileService->delete($filePath);
            }
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            throw $e;
        }
    }

    public function purgeAllTransactions(): void
    {
        $this->purgeTransactions($this->deletedTransactionIds());
    }

    public function softDelete(string $transactionId): array
    {
        return $this->logged('TRANSACTION_DELETED','delete',['target_id'=>$transactionId],fn():array=>$this->softDeleteInternal($transactionId),true);
    }

    private function softDeleteInternal(string $transactionId): array
    {
        $actor = ActorHelper::user();
        try {
            $this->pdo->beginTransaction();

            $existing = $this->transactionModel->getByIdForUpdate($transactionId);
            if (!$existing || !empty($existing['deleted_at'])) {
                throw new \RuntimeException('삭제할 거래를 찾을 수 없습니다.');
            }
            if (($existing['status'] ?? '') !== 'draft') {
                throw new \RuntimeException('작성 중인 거래만 삭제할 수 있습니다.');
            }

            if (!$this->transactionModel->update($transactionId, [
                'deleted_at' => date('Y-m-d H:i:s'),
                'deleted_by' => $actor,
                'updated_at' => date('Y-m-d H:i:s'),
                'updated_by' => $actor,
            ])) {
                throw new \RuntimeException('거래 삭제에 실패했습니다.');
            }
            $this->evidenceLinkModel->softDeleteByTarget('TRANSACTION', $transactionId);

            $this->pdo->commit();

            return [
                'success' => true,
                'message' => 'Transaction deleted.',
            ];
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            return [
                'success' => false,
                'message' => $e instanceof \RuntimeException || $e instanceof \InvalidArgumentException
                    ? $e->getMessage()
                    : '삭제 중 오류가 발생했습니다.',
            ];
        }
    }

    private function deletedTransactionIds(): array
    {
        return $this->transactionModel->getDeletedIds();
    }

    private function dateString(mixed $value): string
    {
        $value = trim((string) ($value ?? ''));
        if ($value === '') {
            return '';
        }

        $timestamp = strtotime($value);
        return $timestamp ? date('Y-m-d', $timestamp) : '';
    }

    private function numberOrNull(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_numeric($value)) {
            return (float) $value;
        }

        $normalized = str_replace(',', '', trim((string) $value));
        return is_numeric($normalized) ? (float) $normalized : null;
    }

    public function save(array $data, array $files = []): array
    {
        return $this->logged('TRANSACTION_SAVED','save',['target_id'=>trim((string)($data['id']??''))?:null],fn():array=>$this->saveInternal($data,$files));
    }

    private function saveInternal(array $data, array $files = []): array
    {
        $actor = ActorHelper::user();
        $timestamp = date('Y-m-d H:i:s');
        $transactionId = trim((string) ($data['id'] ?? ''));
        $items = $this->normalizeItems($data['items'] ?? [], $data);
        if ($items === []) {
            return ['success' => false, 'message' => '거래 항목을 한 건 이상 입력해 주세요.'];
        }
        $settlements = $this->normalizeSettlements($data['settlements'] ?? [], $data);
        $linkedEvidences = $this->normalizeLinkedEvidences($data['linked_evidences'] ?? []);
        $evidenceWorkflowPolicy = new EvidenceWorkflowPolicyService();
        $existingEvidenceIdentities = [];
        if ($transactionId !== '') {
            foreach ($this->evidenceLinkModel->getTransactionEvidences($transactionId) as $existingLink) {
                $existingImportType = strtoupper(trim((string) ($existingLink['import_type'] ?? '')));
                $existingEvidenceId = trim((string) ($existingLink['evidence_id'] ?? ''));
                if ($existingImportType !== '' && $existingEvidenceId !== '') {
                    $existingEvidenceIdentities[$existingImportType . "\0" . $existingEvidenceId] = true;
                }
            }
        }
        foreach ($linkedEvidences as $linkedEvidence) {
            $identity = $linkedEvidence['import_type'] . "\0" . $linkedEvidence['evidence_id'];
            $linkPurpose = (string) ($linkedEvidence['link_purpose'] ?? '');
            $evidence = $this->evidenceSourceRepository->find(
                $linkedEvidence['import_type'],
                $linkedEvidence['evidence_id']
            );
            if (!$evidence || !in_array((string) ($evidence['evidence_type'] ?? ''), ['DATA', 'BOTH'], true)) {
                return ['success' => false, 'message' => '증빙정책에서 거래 연결이 허용되지 않은 증빙입니다.'];
            }
            if (isset($existingEvidenceIdentities[$identity])) {
                continue;
            }
            if ($linkPurpose === '') {
                $linkPurpose = EvidenceWorkflowPolicyService::LINK_ACCOUNTING_READY;
            }
            if (!$evidenceWorkflowPolicy->canLink(
                (string) ($evidence['evidence_status'] ?? ''),
                $linkPurpose
            )) {
                return ['success' => false, 'message' => '현재 증빙상태에서는 요청한 목적으로 거래에 연결할 수 없습니다.'];
            }
        }

        $itemTotals = $this->calculateItemTotals($items);
        $settlementTotals = $this->calculateSettlementTotals($settlements);
        $totals = $this->resolveTransactionTotals($data, $itemTotals, $settlementTotals);
        if (abs((float) $totals['transaction_final_amount']) <= 0) {
            return [
                'success' => false,
                'message' => '거래금액을 확인해 주세요.',
            ];
        }
        $ownsTransaction = !$this->pdo->inTransaction();
        $isUpdate = $transactionId !== '';
        try {
            if ($ownsTransaction) {
                $this->pdo->beginTransaction();
            }

            $isUpdate = false;
            $existing = null;
            if ($transactionId !== '') {
                $existing = $this->transactionModel->getByIdForUpdate($transactionId);
                if (!$existing) {
                    throw new \RuntimeException('수정할 거래를 찾을 수 없습니다.');
                }

                if (!empty($existing['deleted_at'])) {
                    throw new \RuntimeException('삭제된 거래는 수정할 수 없습니다.');
                }
                if (!in_array((string) ($existing['status'] ?? ''), ['draft', 'completed'], true)) {
                    throw new \RuntimeException('마감되거나 취소된 거래는 수정할 수 없습니다.');
                }
                $loadedUpdatedAt = trim((string) ($data['loaded_updated_at'] ?? ''));
                if ($loadedUpdatedAt === '') {
                    throw new \InvalidArgumentException('거래 수정 기준시각이 없습니다. 다시 불러온 후 저장해 주세요.');
                }
                if (!hash_equals((string) ($existing['updated_at'] ?? ''), $loadedUpdatedAt)) {
                    throw new \RuntimeException('다른 사용자가 먼저 수정했습니다. 다시 불러온 후 저장해 주세요.');
                }

                $isUpdate = true;
                $transactionPayload = $this->buildTransactionPayload($data, $actor, $timestamp, $totals);
                $this->referenceValidator->validate(
                    $transactionPayload['update'],
                    $existing,
                    is_array($data['reference_validation_context'] ?? null) ? $data['reference_validation_context'] : []
                );
                if (!$this->transactionModel->update($transactionId, $transactionPayload['update'])) {
                    throw new \RuntimeException('거래 수정에 실패했습니다.');
                }
                $this->recreateSettlements($transactionId, [], $actor, $timestamp);
            } else {
                $transactionId = UuidHelper::generate();
                $transactionPayload = $this->buildTransactionPayload($data, $actor, $timestamp, $totals);
                $this->referenceValidator->validate(
                    $transactionPayload['insert'],
                    null,
                    is_array($data['reference_validation_context'] ?? null) ? $data['reference_validation_context'] : []
                );
                $insertPayload = $transactionPayload['insert'];
                $insertPayload['id'] = $transactionId;
                $insertPayload['sort_no'] = SequenceHelper::next('ledger_transactions', 'sort_no');

                if (!$this->transactionModel->insert($insertPayload)) {
                    throw new \RuntimeException('거래 저장에 실패했습니다.');
                }
            }

            $this->checkpoint('after_header');
            if (!empty($data['_header_only_retry'])) {
                $existingItems = $this->transactionItemModel->getByTransactionId($transactionId);
                foreach ($existingItems as $row) {
                    if (!$this->transactionItemModel->hardDelete((string) $row['id'])) {
                        throw new \RuntimeException('기존 거래 항목 정리에 실패했습니다.');
                    }
                }
            } else {
                $this->recreateItems($transactionId, $items, $actor, $timestamp);
                $this->checkpoint('after_items');
                $this->recreateSettlements($transactionId, $settlements, $actor, $timestamp);
                $this->checkpoint('after_settlements');
            }
            $deletedFilePaths = $this->syncFiles($transactionId, $data, $files, $actor, $timestamp);
            $this->evidenceLinkModel->replaceTransactionEvidences($transactionId, $linkedEvidences);
            $this->checkpoint('after_links');

            if ($ownsTransaction) {
                $this->pdo->commit();
                foreach ($deletedFilePaths as $deletedFilePath) {
                    $this->fileService->delete($deletedFilePath);
                }
            }

            return [
                'success' => true,
                'message' => $isUpdate ? '거래가 수정되었습니다.' : '거래가 저장되었습니다.',
                'id' => $transactionId,
            ];
        } catch (\Throwable $e) {
            if ($ownsTransaction && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            return [
                'success' => false,
                'message' => $e instanceof \RuntimeException || $e instanceof \InvalidArgumentException
                    ? $e->getMessage()
                    : ($isUpdate ? '수정 중 오류가 발생했습니다.' : '저장 중 오류가 발생했습니다.'),
            ];
        }
    }

    private function buildTransactionPayload(array $data, string $actor, string $timestamp, array $totals): array
    {
        $transactionDate = trim((string) ($data['transaction_date'] ?? ''));
        if ($transactionDate === '') {
            $transactionDate = date('Y-m-d');
        }

        $businessUnit = trim((string) ($data['business_unit'] ?? ''));
        if ($businessUnit === '') {
            throw new \InvalidArgumentException('사업구분은 필수입니다.');
        }

        $transactionId = trim((string) ($data['id'] ?? ''));

        $base = [
            'transaction_date' => $transactionDate,
            'business_unit' => $businessUnit,
            'transaction_direction' => $this->nullable($data['transaction_direction'] ?? null),
            'operation_type' => $this->nullable($data['operation_type'] ?? null),
            'client_id' => $this->nullable($data['client_id'] ?? null),
            'project_id' => $this->nullable($data['project_id'] ?? null),
            'bank_account_id' => $this->nullable($data['bank_account_id'] ?? null),
            'card_id' => $this->nullable($data['card_id'] ?? null),
            'team_id' => $this->nullable($data['team_id'] ?? null),
            'employee_id' => $this->nullable($data['employee_id'] ?? null),
            'currency' => !empty($data['is_import']) ? $this->normalizeCurrencyCode($data['currency'] ?? 'KRW') : 'KRW',
            'transaction_exchange_rate' => !empty($data['is_import'])
                ? $this->numericOrNull($data['transaction_exchange_rate'] ?? $data['exchange_rate'] ?? null)
                : $this->numericOrNull($data['transaction_exchange_rate'] ?? $data['exchange_rate'] ?? null),
            'transaction_foreign_amount' => !empty($data['is_import']) ? $totals['transaction_foreign_amount'] : null,
            'transaction_supply_amount' => $totals['transaction_supply_amount'],
            'transaction_settlement_amount' => $totals['transaction_settlement_amount'],
            'transaction_final_amount' => $totals['transaction_final_amount'],
            'transaction_description' => $this->nullable($data['transaction_description'] ?? $data['description'] ?? null),
            'status' => $this->normalizeTransactionStatus($data['status'] ?? 'draft'),
            'transaction_note' => $this->nullable($data['transaction_note'] ?? $data['note'] ?? null),
            'transaction_memo' => $this->nullable($data['transaction_memo'] ?? $data['memo'] ?? null),
            'updated_at' => $timestamp,
            'updated_by' => $actor,
        ];

        return [
            'insert' => $base + [
                'created_at' => $timestamp,
                'created_by' => $actor,
            ],
            'update' => $base,
        ];
    }

    private function normalizeLinkedEvidences(mixed $value): array
    {
        if (is_string($value)) {
            $value = json_decode($value, true);
        }
        if (!is_array($value)) {
            throw new \InvalidArgumentException('연결 증빙 형식이 올바르지 않습니다.');
        }
        $normalized = [];
        foreach ($value as $row) {
            if (!is_array($row)) {
                continue;
            }
            $importType = strtoupper(trim((string) ($row['import_type'] ?? '')));
            $evidenceId = trim((string) ($row['evidence_id'] ?? ''));
            if ($importType === '' || $evidenceId === '') {
                continue;
            }
            $normalized[$importType . "\0" . $evidenceId] = [
                'import_type' => $importType,
                'evidence_id' => $evidenceId,
                'link_purpose' => strtoupper(trim((string) ($row['link_purpose'] ?? ''))),
            ];
        }
        return array_values($normalized);
    }

    private function recreateItems(string $transactionId, array $items, string $actor, string $timestamp): void
    {
        $existingItems = $this->transactionItemModel->getByTransactionId($transactionId);
        foreach ($existingItems as $row) {
            if (!$this->transactionItemModel->hardDelete((string) $row['id'])) {
                throw new \RuntimeException('기존 거래 항목 정리에 실패했습니다.');
            }
        }

        foreach ($items as $index => $item) {
            $payload = [
                'id' => UuidHelper::generate(),
                'sort_no' => $index + 1,
                'transaction_id' => $transactionId,
                'item_date' => $item['item_date'],
                'item_name' => $item['item_name'],
                'item_specification' => $item['item_specification'],
                'item_unit_name' => $item['item_unit_name'],
                'item_quantity' => $item['item_quantity'],
                'item_unit_price' => $item['item_unit_price'],
                'item_foreign_unit_price' => $item['item_foreign_unit_price'],
                'item_foreign_amount' => $item['item_foreign_amount'],
                'item_supply_amount' => $item['item_supply_amount'],
                'item_description' => $item['item_description'],
                'regular_employment_income_line_item_id' => $item['regular_employment_income_line_item_id'] ?? null,
                'statutory_standard_revision_id' => $item['statutory_standard_revision_id'] ?? null,
                'calculation_basis_id' => $item['calculation_basis_id'] ?? null,
                'created_at' => $timestamp,
                'created_by' => $actor,
                'updated_at' => $timestamp,
                'updated_by' => $actor,
            ];

            if (!$this->transactionItemModel->insert($payload)) {
                throw new \RuntimeException(($index + 1) . '번째 거래품목 저장에 실패했습니다.');
            }
        }
    }

    private function recreateSettlements(string $transactionId, array $settlements, string $actor, string $timestamp): void
    {
        $existingRows = $this->transactionSettlementModel->getByTransactionId($transactionId);
        foreach ($existingRows as $row) {
            if (!$this->transactionSettlementModel->hardDelete((string) $row['id'])) {
                throw new \RuntimeException('기존 정산내역 정리에 실패했습니다.');
            }
        }

        foreach ($settlements as $index => $settlement) {
            $payload = [
                'id' => UuidHelper::generate(),
                'sort_no' => $index + 1,
                'transaction_id' => $transactionId,
                'transaction_item_id' => null,
                'regular_employment_income_line_item_id' => $settlement['regular_employment_income_line_item_id'] ?? null,
                'statutory_standard_revision_id' => $settlement['statutory_standard_revision_id'] ?? null,
                'calculation_basis_id' => $settlement['calculation_basis_id'] ?? null,
                'settlement_type' => $settlement['settlement_type'],
                'amount_sign' => $settlement['amount_sign'],
                'amount' => $settlement['amount'],
                'currency' => $settlement['currency'],
                'exchange_rate' => $settlement['exchange_rate'],
                'settlement_description' => $settlement['settlement_description'],
                'meta_json' => $settlement['meta_json'],
                'created_at' => $timestamp,
                'created_by' => $actor,
                'updated_at' => $timestamp,
                'updated_by' => $actor,
            ];

            if (!$this->transactionSettlementModel->insert($payload)) {
                throw new \RuntimeException(($index + 1) . '번째 정산정보 저장에 실패했습니다.');
            }
        }
    }

    private function normalizeTransactionStatus(mixed $value): string
    {
        $status = strtolower(trim((string) ($value ?? 'draft')));
        return in_array($status, ['draft', 'completed', 'closed', 'cancelled'], true) ? $status : 'draft';
    }

    private function calculateItemTotals(array $items): array
    {
        $foreignAmount = 0.0;
        $supplyAmount = 0.0;

        foreach ($items as $item) {
            $foreignAmount += (float) ($item['item_foreign_amount'] ?? 0);
            $supplyAmount += (float) ($item['item_supply_amount'] ?? 0);
        }

        return [
            'transaction_foreign_amount' => round($foreignAmount, 2),
            'transaction_supply_amount' => round($supplyAmount, 2),
        ];
    }

    private function calculateSettlementTotals(array $settlements): array
    {
        $settlementAmount = 0.0;

        foreach ($settlements as $settlement) {
            $signedBaseAmount = (float) ($settlement['signed_base_amount'] ?? 0);
            $settlementAmount += $signedBaseAmount;
        }

        return [
            'transaction_settlement_amount' => round($settlementAmount, 2),
        ];
    }

    private function normalizeCurrencyCode(mixed $value): string
    {
        $currency = strtoupper(trim((string) ($value ?? 'KRW')));
        return preg_match('/^[A-Z]{3}$/', $currency) ? $currency : 'KRW';
    }

    private function resolveTransactionTotals(array $data, array $itemTotals, array $settlementTotals): array
    {
        $supplyAmount = (float) ($itemTotals['transaction_supply_amount'] ?? 0);
        $foreignAmount = (float) ($itemTotals['transaction_foreign_amount'] ?? 0);
        $settlementAmount = (float) ($settlementTotals['transaction_settlement_amount'] ?? 0);
        $finalAmount = $supplyAmount + $settlementAmount;

        if (abs($supplyAmount) > 0 || abs($settlementAmount) > 0) {
            $explicitFinalAmount = $this->numericOrNull($data['transaction_final_amount'] ?? $data['final_amount'] ?? null);
            if ($explicitFinalAmount !== null && abs($explicitFinalAmount - $finalAmount) >= 0.01) {
                throw new \InvalidArgumentException('거래금액이 항목 및 정산내역 합계와 일치하지 않습니다.');
            }
            return [
                'transaction_foreign_amount' => round($foreignAmount, 2),
                'transaction_supply_amount' => round($supplyAmount, 2),
                'transaction_settlement_amount' => round($settlementAmount, 2),
                'transaction_final_amount' => round($finalAmount, 2),
            ];
        }

        throw new \InvalidArgumentException('거래 항목을 한 건 이상 입력해 주세요.');
    }

    private function journalLearningEventCount(string $transactionId): int
    {
        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*) FROM ledger_journal_learning_events WHERE transaction_id = :transaction_id'
        );
        $stmt->execute([':transaction_id' => $transactionId]);
        return (int) $stmt->fetchColumn();
    }

    private function syncFiles(string $transactionId, array $data, array $files, string $actor, string $timestamp): array
    {
        $deletedFilePaths = [];
        foreach ($this->normalizeIdList($data['delete_file_ids'] ?? []) as $fileId) {
            $fileRow = $this->transactionFileModel->getById($fileId);
            if ($fileRow && !empty($fileRow['file_path'])) {
                $deletedFilePaths[] = (string) $fileRow['file_path'];
            }
            $this->transactionFileModel->hardDelete($fileId);
        }

        $fileOrders = $this->normalizeFileOrders($data['file_orders'] ?? []);
        foreach ($fileOrders as $fileId => $fileOrder) {
            $this->transactionFileModel->updateOrder($fileId, $fileOrder + 100000);
        }
        foreach ($fileOrders as $fileId => $fileOrder) {
            $this->transactionFileModel->updateOrder($fileId, $fileOrder);
        }

        $newFileOrders = $this->normalizeNewFileOrders($data['new_file_orders'] ?? []);
        foreach ($this->normalizeUploadedFiles($files['transaction_files'] ?? null) as $file) {
            $upload = $this->fileService->uploadByPolicyKey($file, 'transaction_evidence');
            if (empty($upload['success'])) {
                throw new \RuntimeException((string) ($upload['message'] ?? '증빙 파일 업로드에 실패했습니다.'));
            }

            $originalName = (string) ($file['name'] ?? ($upload['file'] ?? ''));
            $fileOrder = (int) (array_shift($newFileOrders) ?? $this->nextFileOrder($transactionId));

            if (!$this->transactionFileModel->insert([
                'id' => UuidHelper::generate(),
                'transaction_id' => $transactionId,
                'file_path' => $upload['db_path'] ?? '',
                'file_name' => $originalName !== '' ? $originalName : ($upload['file'] ?? null),
                'file_order' => $fileOrder > 0 ? $fileOrder : $this->nextFileOrder($transactionId),
                'file_size' => isset($upload['size']) ? (int) $upload['size'] : ($file['size'] ?? null),
                'created_at' => $timestamp,
                'created_by' => $actor,
            ])) {
                throw new \RuntimeException('증빙 파일정보 저장에 실패했습니다.');
            }
        }

        return array_values(array_unique($deletedFilePaths));
    }

    private function normalizeUploadedFiles(mixed $input): array
    {
        if (!is_array($input) || !isset($input['name'])) {
            return [];
        }

        if (!is_array($input['name'])) {
            return (($input['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) ? [] : [$input];
        }

        $files = [];
        foreach ($input['name'] as $index => $name) {
            if (($input['error'][$index] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
                continue;
            }

            $files[] = [
                'name' => $name,
                'type' => $input['type'][$index] ?? '',
                'tmp_name' => $input['tmp_name'][$index] ?? '',
                'error' => $input['error'][$index] ?? UPLOAD_ERR_OK,
                'size' => $input['size'][$index] ?? 0,
            ];
        }

        return $files;
    }

    private function normalizeFileOrders(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $orders = [];
        foreach ($value as $id => $order) {
            $fileId = trim((string) $id);
            if ($fileId === '') {
                continue;
            }
            $orders[$fileId] = max(1, (int) $order);
        }

        return $orders;
    }

    private function normalizeNewFileOrders(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        return array_map(static fn ($order): int => max(1, (int) $order), array_values($value));
    }

    private function nextFileOrder(string $transactionId): int
    {
        $files = $this->transactionFileModel->getByTransactionId($transactionId);
        $max = 0;
        foreach ($files as $file) {
            $max = max($max, (int) ($file['file_order'] ?? 0));
        }

        return $max + 1;
    }

    private function normalizeIdList(mixed $value): array
    {
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            $value = is_array($decoded) ? $decoded : [$value];
        }

        if (!is_array($value)) {
            return [];
        }

        return array_values(array_filter(array_map(static fn ($id): string => trim((string) $id), $value)));
    }

    private function normalizeItems(mixed $items, array $data = []): array
    {
        $decoded = $this->decodeArrayInput($items);
        if (!is_array($decoded)) {
            return [];
        }

        $rows = [];

        foreach ($decoded as $item) {
            if (!is_array($item)) {
                continue;
            }

            $itemName = trim((string) ($item['item_name'] ?? ''));
            if ($itemName === '') {
                continue;
            }

            $itemQuantity = (float) ($this->numericOrNull($item['item_quantity'] ?? $item['quantity'] ?? 0) ?? 0);
            $exchangeRate = (float) ($this->numericOrNull($data['transaction_exchange_rate'] ?? $data['exchange_rate'] ?? null) ?? 0);
            $itemForeignUnitPrice = $this->numericOrNull($item['item_foreign_unit_price'] ?? $item['foreign_unit_price'] ?? null);
            $itemForeignAmount = $this->numericOrNull($item['item_foreign_amount'] ?? $item['foreign_amount'] ?? null);
            $usesForeignAmount = $exchangeRate > 0 && ($itemForeignUnitPrice !== null || $itemForeignAmount !== null);
            if ($usesForeignAmount && $itemForeignAmount === null) {
                $itemForeignAmount = round($itemQuantity * (float) $itemForeignUnitPrice, 2);
            }
            $itemUnitPrice = $usesForeignAmount && $itemQuantity > 0
                ? round(((float) $itemForeignAmount * $exchangeRate) / $itemQuantity, 2)
                : (float) ($this->numericOrNull($item['item_unit_price'] ?? $item['unit_price'] ?? 0) ?? 0);
            $itemDate = trim((string) ($item['item_date'] ?? ($data['transaction_date'] ?? date('Y-m-d'))));
            if ($itemDate === '') {
                $itemDate = date('Y-m-d');
            }

            $givenSupplyAmount = $this->numericOrNull($item['item_supply_amount'] ?? $item['supply_amount'] ?? $item['amount'] ?? null);

            $supplyAmount = $usesForeignAmount
                ? round((float) $itemForeignAmount * $exchangeRate, 2)
                : round($itemQuantity * $itemUnitPrice, 2);
            if ($givenSupplyAmount !== null) {
                $supplyAmount = round((float) $givenSupplyAmount, 2);
                if ($itemQuantity <= 0) {
                    $itemQuantity = 1.0;
                }
                if ($itemUnitPrice <= 0 && $itemQuantity > 0) {
                    $itemUnitPrice = round($supplyAmount / $itemQuantity, 2);
                }
            }

            if ($itemQuantity <= 0) {
                $itemQuantity = 1.0;
            }
            if ($itemUnitPrice <= 0 && $itemQuantity > 0 && abs($supplyAmount) > 0) {
                $itemUnitPrice = round($supplyAmount / $itemQuantity, 2);
            }

            $rows[] = [
                'item_date' => $itemDate,
                'item_name' => $itemName,
                'item_specification' => $this->nullable($item['item_specification'] ?? $item['specification'] ?? null),
                'item_unit_name' => $this->nullable($item['item_unit_name'] ?? $item['unit_name'] ?? null),
                'item_quantity' => $itemQuantity,
                'item_unit_price' => $itemUnitPrice,
                'item_foreign_unit_price' => $usesForeignAmount ? (float) ($itemForeignUnitPrice ?? 0) : null,
                'item_foreign_amount' => $usesForeignAmount ? (float) ($itemForeignAmount ?? 0) : null,
                'item_supply_amount' => $supplyAmount,
                'item_description' => $this->nullable($item['item_description'] ?? $item['description'] ?? null),
                'regular_employment_income_line_item_id' => $this->nullable($item['regular_employment_income_line_item_id'] ?? null),
                'statutory_standard_revision_id' => $this->nullable($item['statutory_standard_revision_id'] ?? null),
                'calculation_basis_id' => $this->nullable($item['calculation_basis_id'] ?? null),
            ];
        }

        return $rows;
    }

    private function normalizeSettlements(mixed $settlements, array $data = []): array
    {
        $decoded = $this->decodeArrayInput($settlements);
        if (!is_array($decoded)) {
            return [];
        }

        $rows = [];
        $defaultCurrency = $this->normalizeCurrencyCode($data['currency'] ?? 'KRW');
        $defaultExchangeRate = $this->numericOrNull($data['transaction_exchange_rate'] ?? $data['exchange_rate'] ?? null);

        foreach ($decoded as $settlement) {
            if (!is_array($settlement)) {
                continue;
            }

            $settlementType = $this->normalizeSettlementType($settlement['settlement_type'] ?? null);
            $amount = (float) ($this->numericOrNull($settlement['amount'] ?? null) ?? 0);
            if ($settlementType === '' || abs($amount) <= 0) {
                continue;
            }

            $amountSign = $this->normalizeAmountSign($settlement['amount_sign'] ?? null);
            $currency = $this->normalizeCurrencyCode($settlement['currency'] ?? $defaultCurrency);
            $exchangeRate = $this->numericOrNull($settlement['exchange_rate'] ?? $defaultExchangeRate);
            $baseAmount = $currency !== 'KRW'
                ? round($amount * (float) ($exchangeRate ?? 0), 2)
                : round($amount, 2);
            $signedBaseAmount = $amountSign === 'MINUS' ? (-1 * $baseAmount) : $baseAmount;

            $meta = $settlement['meta_json'] ?? null;
            if (is_array($meta)) {
                $meta = json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            } elseif (!is_string($meta) || trim($meta) === '') {
                $meta = null;
            }

            $rows[] = [
                'transaction_item_id' => null,
                'regular_employment_income_line_item_id' => $this->nullable($settlement['regular_employment_income_line_item_id'] ?? null),
                'statutory_standard_revision_id' => $this->nullable($settlement['statutory_standard_revision_id'] ?? null),
                'calculation_basis_id' => $this->nullable($settlement['calculation_basis_id'] ?? null),
                'settlement_type' => $settlementType,
                'amount_sign' => $amountSign,
                'amount' => round($amount, 2),
                'currency' => $currency,
                'exchange_rate' => $currency === 'KRW' ? null : $exchangeRate,
                'settlement_description' => $this->nullable($settlement['settlement_description'] ?? $settlement['description'] ?? null),
                'meta_json' => $meta,
                'signed_base_amount' => round($signedBaseAmount, 2),
            ];
        }

        return $rows;
    }

    private function normalizeSettlementType(mixed $value): string
    {
        $type = strtoupper(trim((string) ($value ?? '')));
        return preg_match('/^[A-Z0-9_]+$/', $type) ? $type : '';
    }

    private function normalizeAmountSign(mixed $value): string
    {
        $raw = strtoupper(trim((string) ($value ?? 'PLUS')));
        return in_array($raw, ['-', 'MINUS', 'NEGATIVE'], true) ? 'MINUS' : 'PLUS';
    }

    private function decodeArrayInput(mixed $value): mixed
    {
        if (is_string($value)) {
            $trimmed = trim($value);
            if ($trimmed === '') {
                return [];
            }

            $decoded = json_decode($trimmed, true);
            return is_array($decoded) ? $decoded : [];
        }

        return $value;
    }

    private function nullable(mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }

        if (is_string($value)) {
            $value = trim($value);
            return $value === '' ? null : $value;
        }

        return $value;
    }

    private function numericOrNull(mixed $value): float|int|null
    {
        if ($value === null || $value === '') {
            return null;
        }

        return is_numeric($value) ? $value + 0 : null;
    }

    private function logged(string$event,string$action,array$context,callable$operation,bool$warning=false):mixed
    {
        $started=microtime(true);$base=['service'=>self::class,'action'=>$action,'actor'=>ActorHelper::user()]+$context;
        try{$result=$operation();$payload=['event_code'=>$event,'result'=>'SUCCESS','duration_ms'=>(int)round((microtime(true)-$started)*1000)]+$base;if($warning)$this->logger->warning('거래 업무 처리가 완료되었습니다.',$payload);else$this->logger->info('거래 업무 처리가 완료되었습니다.',$payload);return$result;}
        catch(\PDOException$e){$this->logger->error('거래 업무 처리에 실패했습니다.',['event_code'=>$event.'_FAILED','result'=>'FAILED','error_code'=>get_class($e),'error'=>$e,'duration_ms'=>(int)round((microtime(true)-$started)*1000)]+$base);throw$e;}
        catch(\InvalidArgumentException|\DomainException|\RuntimeException$e){$this->logger->warning('거래 업무 처리가 차단되었습니다.',['event_code'=>$event.'_BLOCKED','result'=>'BLOCKED','error_code'=>get_class($e),'error'=>$e,'duration_ms'=>(int)round((microtime(true)-$started)*1000)]+$base);throw$e;}
        catch(\Throwable$e){$this->logger->error('거래 업무 처리에 실패했습니다.',['event_code'=>$event.'_FAILED','result'=>'FAILED','error_code'=>get_class($e),'error'=>$e,'duration_ms'=>(int)round((microtime(true)-$started)*1000)]+$base);throw$e;}
    }
}
