<?php

namespace App\Services\Ledger;

use App\Models\Ledger\TransactionItemModel;
use App\Models\Ledger\TransactionFileModel;
use App\Models\Ledger\TransactionLinkModel;
use App\Models\Ledger\TransactionModel;
use App\Models\Ledger\TransactionSettlementModel;
use App\Services\File\FileService;
use Core\Helpers\ActorHelper;
use Core\Helpers\SequenceHelper;
use Core\Helpers\UuidHelper;
use PDO;

class TransactionCrudService
{
    private TransactionModel $transactionModel;
    private TransactionItemModel $transactionItemModel;
    private TransactionSettlementModel $transactionSettlementModel;
    private TransactionFileModel $transactionFileModel;
    private TransactionLinkModel $transactionLinkModel;
    private FileService $fileService;

    public function __construct(private readonly PDO $pdo)
    {
        $this->transactionModel = new TransactionModel($pdo);
        $this->transactionItemModel = new TransactionItemModel($pdo);
        $this->transactionSettlementModel = new TransactionSettlementModel($pdo);
        $this->transactionFileModel = new TransactionFileModel($pdo);
        $this->transactionLinkModel = new TransactionLinkModel($pdo);
        $this->fileService = new FileService($pdo);
    }

    public function getList(array $filters): array
    {
        $filters = $this->normalizeSearchFilters($filters);

        $allowedKeys = [
            'business_unit',
            'transaction_direction',
            'status',
            'match_status',
            'project_id',
            'client_id',
            'date_from',
            'date_to',
            'updated_from',
            'updated_to',
            'search_conditions',
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

    private function normalizeSearchFilters(array $filters): array
    {
        if ($filters === []) {
            return [];
        }

        $keys = array_keys($filters);
        $isList = $keys === range(0, count($filters) - 1);
        if (!$isList) {
            return $filters;
        }

        $normalized = [];
        $searchConditions = [];
        $searchableFields = [
            'sort_no',
            'business_unit',
            'transaction_direction',
            'transaction_date',
            'bank_account_id',
            'card_id',
            'team_id',
            'employee_id',
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
            'transaction_exchange_rate',
            'status',
            'match_status',
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

        return $normalized;
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
        $transaction['links'] = $this->transactionLinkModel->getByTransactionId($id);

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
        $stmt = $this->pdo->query("
            SELECT
                t.*,
                COALESCE(sc.client_name, '') AS client_name,
                COALESCE(sp.project_name, '') AS project_name
            FROM ledger_transactions t
            LEFT JOIN system_clients sc
                ON t.client_id = sc.id
            LEFT JOIN system_projects sp
                ON t.project_id = sp.id
            WHERE t.deleted_at IS NOT NULL
            ORDER BY t.deleted_at DESC, t.transaction_date DESC
        ");

        return array_map(static function (array $row): array {
            unset($row['tax_type']);
            return $row;
        }, $stmt->fetchAll(PDO::FETCH_ASSOC) ?: []);
    }

    public function restoreTransactions(array $ids): void
    {
        if ($ids === []) {
            return;
        }

        $actor = ActorHelper::user();
        $actorId = is_array($actor) ? ($actor['id'] ?? null) : $actor;

        try {
            $this->pdo->beginTransaction();

            foreach ($ids as $id) {
                $this->transactionModel->update($id, [
                    'deleted_at' => null,
                    'deleted_by' => null,
                    'updated_at' => date('Y-m-d H:i:s'),
                    'updated_by' => $actorId,
                ]);
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
        if ($ids === []) {
            return;
        }

        $filePaths = [];

        try {
            $this->pdo->beginTransaction();

            $deleteItems = $this->pdo->prepare("DELETE FROM ledger_transaction_items WHERE transaction_id = :id");
            $softDeleteLinks = $this->pdo->prepare("
                UPDATE ledger_transaction_links
                SET is_active = 0,
                    deleted_at = NOW(),
                    deleted_by = :deleted_by,
                    updated_at = NOW(),
                    updated_by = :updated_by
                WHERE transaction_id = :id
                  AND is_active = 1
                  AND deleted_at IS NULL
            ");
            $actor = ActorHelper::user();
            $actorId = is_array($actor) ? (string) ($actor['id'] ?? 'SYSTEM') : (string) $actor;

            foreach ($ids as $id) {
                $transaction = $this->transactionModel->getById($id) ?: [];
                foreach ($this->transactionFileModel->getByTransactionId($id) as $file) {
                    if (!empty($file['file_path'])) {
                        $filePaths[] = (string) $file['file_path'];
                    }
                }

                $this->resetGeneratedTransactionState($id, $actorId, $transaction);
                $deleteItems->execute([':id' => $id]);
                $softDeleteLinks->execute([
                    ':id' => $id,
                    ':deleted_by' => $actorId,
                    ':updated_by' => $actorId,
                ]);
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

    public function updateLinkStatus(string $transactionId, string $matchStatus, string $actor): bool
    {
        return $this->transactionModel->update($transactionId, [
            'match_status' => $matchStatus,
            'updated_at' => date('Y-m-d H:i:s'),
            'updated_by' => $actor,
        ]);
    }

    public function recalculateMatchStatus(string $transactionId, string $actor): void
    {
        $activeCount = $this->transactionLinkModel->countActiveByTransactionId($transactionId);
        $matchStatus = $activeCount > 0 ? 'matched' : 'none';

        if (!$this->transactionModel->update($transactionId, [
            'match_status' => $matchStatus,
            'updated_at' => date('Y-m-d H:i:s'),
            'updated_by' => $actor,
        ])) {
            throw new \RuntimeException('Transaction link status update failed.');
        }
    }

    public function reorder(array $changes): bool
    {
        if ($changes === []) {
            return true;
        }

        try {
            if (!$this->pdo->inTransaction()) {
                $this->pdo->beginTransaction();
            }

            foreach ($changes as $row) {
                if (empty($row['id']) || !isset($row['newSortNo'])) {
                    throw new \RuntimeException('정렬 저장에 필요한 거래 ID 또는 순번이 없습니다.');
                }
            }

            foreach ($changes as $row) {
                $this->transactionModel->updateSortNo((string) $row['id'], (int) $row['newSortNo'] + 1000000);
            }

            foreach ($changes as $row) {
                $this->transactionModel->updateSortNo((string) $row['id'], (int) $row['newSortNo']);
            }

            if ($this->pdo->inTransaction()) {
                $this->pdo->commit();
            }

            return true;
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            throw $e;
        }
    }

    public function softDelete(string $transactionId): array
    {
        $actor = ActorHelper::user();
        $transaction = $this->transactionModel->getById($transactionId) ?: [];

        try {
            $this->pdo->beginTransaction();

            $this->assertTransactionDeleteAllowed($transactionId);
            $this->deleteLinkedVouchersForTransaction($transactionId, $actor);
            $this->resetGeneratedTransactionState($transactionId, (string) $actor, $transaction);

            $this->pdo->prepare("
                UPDATE ledger_transaction_links
                SET is_active = 0,
                    deleted_at = NOW(),
                    deleted_by = :deleted_by,
                    updated_at = NOW(),
                    updated_by = :updated_by
                WHERE transaction_id = :transaction_id
                  AND is_active = 1
                  AND deleted_at IS NULL
            ")->execute([
                ':deleted_by' => $actor,
                ':updated_by' => $actor,
                ':transaction_id' => $transactionId,
            ]);

            if (!$this->transactionModel->update($transactionId, [
                'match_status' => 'none',
                'deleted_at' => date('Y-m-d H:i:s'),
                'deleted_by' => $actor,
                'updated_at' => date('Y-m-d H:i:s'),
                'updated_by' => $actor,
            ])) {
                throw new \RuntimeException('거래 삭제에 실패했습니다.');
            }

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
                'message' => $e->getMessage(),
            ];
        }
    }

    public function resetGeneratedTransactionState(string $transactionId, string $actor, array $transaction = []): void
    {
        $this->restoreSeedRowsForDeletedTransaction($transactionId, $actor, $transaction);
    }

    private function restoreSeedRowsForDeletedTransaction(string $transactionId, string $actor, array $transaction = []): void
    {
        if ($this->tableColumnExists('ledger_data_evidences', 'transaction_id')) {
            $stmt = $this->pdo->prepare("
                UPDATE ledger_data_evidences
                SET evidence_status = 'ACTIVE',
                    transaction_status = 'NONE',
                    transaction_id = NULL,
                    error_message = NULL,
                    updated_at = NOW(),
                    updated_by = :actor
                WHERE transaction_id = :transaction_id
                  AND transaction_status NOT IN ('NONE', 'PROCESSING')
            ");
            $stmt->execute([
                ':transaction_id' => $transactionId,
                ':actor' => $actor,
            ]);
        } else {
            return;
        }

        if ($this->tableColumnExists('ledger_data_seed_rows', 'transaction_id')) {
            $stmt = $this->pdo->prepare("
                UPDATE ledger_data_seed_rows
                SET process_status = 'READY',
                    transaction_id = NULL,
                    error_message = NULL,
                    processed_at = NULL,
                    updated_at = NOW(),
                    updated_by = :actor
                WHERE transaction_id = :transaction_id
                  AND process_status NOT IN ('READY', 'PROCESSING')
            ");
            $stmt->execute([
                ':transaction_id' => $transactionId,
                ':actor' => $actor,
            ]);
        }

        $this->restoreProcessingItemsForDeletedTransaction($transactionId, $actor);
    }

    private function restoreProcessingItemsForDeletedTransaction(string $transactionId, string $actor): void
    {
        if (!$this->tableExists('ledger_processing_items')) {
            return;
        }

        $ids = [];
        if ($this->tableExists('ledger_transaction_items')
            && $this->tableColumnExists('ledger_transaction_items', 'processing_item_id')
        ) {
            $stmt = $this->pdo->prepare("
                SELECT processing_item_id
                FROM ledger_transaction_items
                WHERE transaction_id = :transaction_id
                  AND processing_item_id IS NOT NULL
            ");
            $stmt->execute([':transaction_id' => $transactionId]);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
                $ids[] = (string) ($row['processing_item_id'] ?? '');
            }
        }

        $ids = array_values(array_unique(array_filter($ids)));
        if ($ids === []) {
            return;
        }

        [$inSql, $params] = $this->placeholders($ids, 'processing_item_id');
        $params[':actor'] = $actor;
        $this->pdo->prepare("
            UPDATE ledger_processing_items
            SET transaction_status = 'NONE',
                updated_at = NOW(),
                updated_by = :actor
            WHERE id IN ({$inSql})
              AND deleted_at IS NULL
              AND transaction_status NOT IN ('NONE', 'PROCESSING')
        ")->execute($params);
    }

    private function assertTransactionDeleteAllowed(string $transactionId): void
    {
        $stmt = $this->pdo->prepare("
            SELECT v.status, v.voucher_no
            FROM ledger_transaction_links l
            INNER JOIN ledger_vouchers v
                ON v.id = l.voucher_id
               AND v.deleted_at IS NULL
            WHERE l.transaction_id = :transaction_id
              AND l.is_active = 1
              AND l.deleted_at IS NULL
              AND v.status IN ('posted', 'closed', 'confirmed')
            LIMIT 1
        ");
        $stmt->execute([':transaction_id' => $transactionId]);
        $voucher = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        if ($voucher) {
            throw new \RuntimeException('Cannot delete a transaction linked to a confirmed voucher.');
        }
    }

    private function deleteLinkedVouchersForTransaction(string $transactionId, string $actor): void
    {
        $voucherIds = $this->linkedVoucherIdsForTransaction($transactionId);
        if ($voucherIds === []) {
            return;
        }

        [$inSql, $params] = $this->placeholders($voucherIds, 'voucher_id');

        $stmt = $this->pdo->prepare("
            SELECT id, status, voucher_no
            FROM ledger_vouchers
            WHERE id IN ({$inSql})
              AND deleted_at IS NULL
        ");
        $stmt->execute($params);
        $vouchers = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $deletableIds = [];
        foreach ($vouchers as $voucher) {
            $status = strtolower((string) ($voucher['status'] ?? ''));
            if (in_array($status, ['posted', 'closed', 'confirmed'], true)) {
                $label = trim((string) ($voucher['voucher_no'] ?? $voucher['id'] ?? ''));
                throw new \RuntimeException('확정 전표와 연결된 거래는 삭제할 수 없습니다. 전표번호: ' . $label);
            }
            $deletableIds[] = (string) $voucher['id'];
        }

        if ($deletableIds === []) {
            return;
        }

        [$deleteInSql, $deleteParams] = $this->placeholders($deletableIds, 'delete_voucher_id');

        if ($this->tableExists('ledger_voucher_line_refs') && $this->tableExists('ledger_voucher_lines')) {
            $this->pdo->prepare("
                DELETE r
                FROM ledger_voucher_line_refs r
                INNER JOIN ledger_voucher_lines l
                    ON l.id = r.voucher_line_id
                WHERE l.voucher_id IN ({$deleteInSql})
            ")->execute($deleteParams);
        }

        if ($this->tableExists('ledger_voucher_payments')) {
            $this->pdo->prepare("
                DELETE FROM ledger_voucher_payments
                WHERE voucher_id IN ({$deleteInSql})
            ")->execute($deleteParams);
        }

        if ($this->tableExists('ledger_voucher_lines')) {
            $this->pdo->prepare("
                DELETE FROM ledger_voucher_lines
                WHERE voucher_id IN ({$deleteInSql})
            ")->execute($deleteParams);
        }

        $updateParams = $deleteParams;
        $updateParams[':deleted_by'] = $actor;
        $updateParams[':updated_by'] = $actor;
        $this->pdo->prepare("
            UPDATE ledger_vouchers
            SET status = 'deleted',
                deleted_at = NOW(),
                deleted_by = :deleted_by,
                updated_at = NOW(),
                updated_by = :updated_by
            WHERE id IN ({$deleteInSql})
              AND deleted_at IS NULL
        ")->execute($updateParams);
    }

    private function linkedVoucherIdsForTransaction(string $transactionId): array
    {
        $ids = [];

        if ($this->tableExists('ledger_transaction_links')) {
            $stmt = $this->pdo->prepare("
                SELECT DISTINCT voucher_id
                FROM ledger_transaction_links
                WHERE transaction_id = :transaction_id
                  AND voucher_id IS NOT NULL
                  AND deleted_at IS NULL
            ");
            $stmt->execute([':transaction_id' => $transactionId]);
            $ids = array_merge($ids, array_map('strval', $stmt->fetchAll(PDO::FETCH_COLUMN) ?: []));
        }

        if ($this->tableColumnExists('ledger_vouchers', 'transaction_id')) {
            $stmt = $this->pdo->prepare("
                SELECT DISTINCT id
                FROM ledger_vouchers
                WHERE transaction_id = :transaction_id
                  AND deleted_at IS NULL
            ");
            $stmt->execute([':transaction_id' => $transactionId]);
            $ids = array_merge($ids, array_map('strval', $stmt->fetchAll(PDO::FETCH_COLUMN) ?: []));
        }

        return array_values(array_unique(array_filter($ids)));
    }

    private function deletedTransactionIds(): array
    {
        $stmt = $this->pdo->query("SELECT id FROM ledger_transactions WHERE deleted_at IS NOT NULL");
        return array_map('strval', $stmt->fetchAll(PDO::FETCH_COLUMN) ?: []);
    }

    private function placeholders(array $ids, string $prefix): array
    {
        $placeholders = [];
        $params = [];
        foreach (array_values($ids) as $index => $id) {
            $key = ':' . $prefix . '_' . $index;
            $placeholders[] = $key;
            $params[$key] = $id;
        }

        return [implode(', ', $placeholders), $params];
    }

    private function tableExists(string $table): bool
    {
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $table)) {
            return false;
        }

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

    private function tableColumnExists(string $table, string $column): bool
    {
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $table) || !preg_match('/^[a-zA-Z0-9_]+$/', $column)) {
            return false;
        }

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
        $actor = ActorHelper::user();
        $timestamp = date('Y-m-d H:i:s');
        $transactionId = trim((string) ($data['id'] ?? ''));
        $items = $this->normalizeItems($data['items'] ?? [], $data);
        $settlements = $this->normalizeSettlements($data['settlements'] ?? [], $data);

        $itemTotals = $this->calculateItemTotals($items);
        $settlementTotals = $this->calculateSettlementTotals($settlements);
        $totals = $this->resolveTransactionTotals($data, $itemTotals, $settlementTotals);
        if (abs((float) $totals['transaction_final_amount']) <= 0) {
            return [
                'success' => false,
                'message' => 'Enter the transaction header amount.',
            ];
        }
        $transactionPayload = $this->buildTransactionPayload($data, $actor, $timestamp, $totals);

        try {
            $this->pdo->beginTransaction();

            $isUpdate = false;

            if ($transactionId !== '') {
                $existing = $this->transactionModel->getById($transactionId);
                if (!$existing) {
                    throw new \RuntimeException('Transaction not found for update.');
                }

                $isUpdate = true;
                if (!$this->transactionModel->update($transactionId, $transactionPayload['update'])) {
                    throw new \RuntimeException('거래 수정에 실패했습니다.');
                }
                $this->recreateSettlements($transactionId, [], $actor, $timestamp);
            } else {
                $transactionId = UuidHelper::generate();
                $insertPayload = $transactionPayload['insert'];
                $insertPayload['id'] = $transactionId;
                $insertPayload['sort_no'] = SequenceHelper::next('ledger_transactions', 'sort_no');

                if (!$this->transactionModel->insert($insertPayload)) {
                    throw new \RuntimeException('거래 저장에 실패했습니다.');
                }
            }

            if (!empty($data['_header_only_retry'])) {
                $existingItems = $this->transactionItemModel->getByTransactionId($transactionId);
                foreach ($existingItems as $row) {
                    if (!$this->transactionItemModel->hardDelete((string) $row['id'])) {
                        throw new \RuntimeException('Failed to clean existing transaction items.');
                    }
                }
            } else {
                $this->recreateItems($transactionId, $items, $actor, $timestamp);
                $this->recreateSettlements($transactionId, $settlements, $actor, $timestamp);
            }
            $this->syncFiles($transactionId, $data, $files, $actor, $timestamp);

            $this->pdo->commit();

            return [
                'success' => true,
                'message' => $isUpdate ? '거래가 수정되었습니다.' : '거래가 저장되었습니다.',
                'id' => $transactionId,
            ];
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            return [
                'success' => false,
                'message' => $e->getMessage(),
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
            'match_status' => $this->resolveMatchStatusForSave($transactionId),
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

    private function recreateItems(string $transactionId, array $items, string $actor, string $timestamp): void
    {
        $existingItems = $this->transactionItemModel->getByTransactionId($transactionId);
        foreach ($existingItems as $row) {
            if (!$this->transactionItemModel->hardDelete((string) $row['id'])) {
                throw new \RuntimeException('Failed to clean existing transaction items.');
            }
        }

        foreach ($items as $index => $item) {
            $payload = [
                'id' => UuidHelper::generate(),
                'sort_no' => $index + 1,
                'transaction_id' => $transactionId,
                'processing_item_id' => $item['processing_item_id'] ?? null,
                'item_date' => $item['item_date'],
                'item_name' => $item['item_name'],
                'item_specification' => $item['item_specification'],
                'item_unit_name' => $item['item_unit_name'],
                'item_quantity' => $item['item_quantity'],
                'item_unit_price' => $item['item_unit_price'],
                'item_foreign_unit_price' => $item['item_foreign_unit_price'],
                'item_foreign_amount' => $item['item_foreign_amount'],
                'item_supply_amount' => $item['item_supply_amount'],
                'item_tax_type' => $item['item_tax_type'],
                'item_description' => $item['item_description'],
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
                throw new \RuntimeException('Failed to clean existing transaction settlements.');
            }
        }

        foreach ($settlements as $index => $settlement) {
            $payload = [
                'id' => UuidHelper::generate(),
                'sort_no' => $index + 1,
                'transaction_id' => $transactionId,
                'transaction_item_id' => null,
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

    private function normalizeTaxType(mixed $value): ?string
    {
        $taxType = strtoupper(trim((string) ($value ?? '')));
        return preg_match('/^[A-Z0-9_]+$/', $taxType) ? $taxType : null;
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

    private function resolveMatchStatusForSave(string $transactionId): string
    {
        if ($transactionId === '') {
            return 'none';
        }

        return $this->transactionLinkModel->countActiveByTransactionId($transactionId) > 0
            ? 'matched'
            : 'none';
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
            return [
                'transaction_foreign_amount' => round($foreignAmount, 2),
                'transaction_supply_amount' => round($supplyAmount, 2),
                'transaction_settlement_amount' => round($settlementAmount, 2),
                'transaction_final_amount' => round($finalAmount, 2),
            ];
        }

        $foreignAmount = (float) ($this->numericOrNull($data['transaction_foreign_amount'] ?? null) ?? 0);
        $supplyAmount = (float) ($this->numericOrNull($data['transaction_supply_amount'] ?? $data['supply_amount'] ?? null) ?? 0);
        $settlementAmount = (float) ($this->numericOrNull($data['transaction_settlement_amount'] ?? $data['settlement_amount'] ?? null) ?? 0);
        $finalAmount = (float) ($this->numericOrNull($data['transaction_final_amount'] ?? $data['final_amount'] ?? null) ?? 0);
        if (abs($finalAmount) <= 0 && (abs($supplyAmount) > 0 || abs($settlementAmount) > 0)) {
            $finalAmount = $supplyAmount + $settlementAmount;
        }
        if (abs($finalAmount) <= 0 && (abs($supplyAmount) > 0 || abs($settlementAmount) > 0)) {
            $finalAmount = $supplyAmount + $settlementAmount;
        }

        return [
            'transaction_foreign_amount' => round($foreignAmount, 2),
            'transaction_supply_amount' => round($supplyAmount, 2),
            'transaction_settlement_amount' => round($settlementAmount, 2),
            'transaction_final_amount' => round($finalAmount, 2),
        ];
    }

    private function syncFiles(string $transactionId, array $data, array $files, string $actor, string $timestamp): void
    {
        foreach ($this->normalizeIdList($data['delete_file_ids'] ?? []) as $fileId) {
            $fileRow = $this->transactionFileModel->getById($fileId);
            if ($fileRow && !empty($fileRow['file_path'])) {
                $this->fileService->delete((string) $fileRow['file_path']);
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
                throw new \RuntimeException('Failed to save evidence file metadata.');
            }
        }
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
            $itemTaxType = $this->normalizeTaxType($item['item_tax_type'] ?? $item['tax_type'] ?? null)
                ?? ($usesForeignAmount ? 'ZERO' : 'TAXABLE');
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
                'item_tax_type' => $itemTaxType,
                'item_description' => $this->nullable($item['item_description'] ?? $item['description'] ?? null),
                'processing_item_id' => $this->nullable($item['processing_item_id'] ?? null),
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
}
