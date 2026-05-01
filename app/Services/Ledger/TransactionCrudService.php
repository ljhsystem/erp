<?php

namespace App\Services\Ledger;

use App\Models\Ledger\TransactionItemModel;
use App\Models\Ledger\TransactionFileModel;
use App\Models\Ledger\TransactionLinkModel;
use App\Models\Ledger\TransactionModel;
use App\Services\File\FileService;
use Core\Helpers\ActorHelper;
use Core\Helpers\SequenceHelper;
use Core\Helpers\UuidHelper;
use PDO;

class TransactionCrudService
{
    private TransactionModel $transactionModel;
    private TransactionItemModel $transactionItemModel;
    private TransactionFileModel $transactionFileModel;
    private TransactionLinkModel $transactionLinkModel;
    private FileService $fileService;

    public function __construct(private readonly PDO $pdo)
    {
        $this->transactionModel = new TransactionModel($pdo);
        $this->transactionItemModel = new TransactionItemModel($pdo);
        $this->transactionFileModel = new TransactionFileModel($pdo);
        $this->transactionLinkModel = new TransactionLinkModel($pdo);
        $this->fileService = new FileService($pdo);
    }

    public function getList(array $filters): array
    {
        $filters = $this->normalizeSearchFilters($filters);

        $allowedKeys = [
            'business_unit',
            'transaction_type',
            'tax_type',
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

        return $this->transactionModel->getList($normalized);
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
            'transaction_type',
            'transaction_date',
            'project_id',
            'project_name',
            'client_id',
            'client_name',
            'tax_type',
            'supply_amount',
            'vat_amount',
            'total_amount',
            'description',
            'currency',
            'exchange_rate',
            'status',
            'match_status',
            'note',
            'memo',
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
        $transaction['files'] = $this->transactionFileModel->getByTransactionId($id);
        $transaction['links'] = $this->transactionLinkModel->getByTransactionId($id);

        return $transaction;
    }

    public function updateLinkStatus(string $transactionId, string $matchStatus, string $actor): bool
    {
        return $this->transactionModel->update($transactionId, [
            'match_status' => $matchStatus,
            'updated_at' => date('Y-m-d H:i:s'),
            'updated_by' => $actor,
        ]);
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
                    throw new \RuntimeException('정렬 데이터가 올바르지 않습니다.');
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

        try {
            $this->pdo->beginTransaction();

            $this->pdo->prepare("
                UPDATE ledger_transaction_links
                SET is_active = 0,
                    deleted_at = NOW(),
                    deleted_by = :deleted_by
                WHERE transaction_id = :transaction_id
                  AND deleted_at IS NULL
            ")->execute([
                ':deleted_by' => $actor,
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
                'message' => '거래가 삭제되었습니다.',
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

    public function save(array $data, array $files = []): array
    {
        $actor = ActorHelper::user();
        $timestamp = date('Y-m-d H:i:s');
        $transactionId = trim((string) ($data['id'] ?? ''));
        $items = $this->normalizeItems($data['items'] ?? [], $data);

        if ($items === []) {
            return [
                'success' => false,
                'message' => '거래 항목은 최소 1개 이상 필요합니다.',
            ];
        }

        $totals = $this->calculateTotals($items);
        $transactionPayload = $this->buildTransactionPayload($data, $actor, $timestamp, $totals);

        try {
            $this->pdo->beginTransaction();

            $isUpdate = false;

            if ($transactionId !== '') {
                $existing = $this->transactionModel->getById($transactionId);
                if (!$existing) {
                    throw new \RuntimeException('수정할 거래 정보를 찾을 수 없습니다.');
                }

                $isUpdate = true;
                if (!$this->transactionModel->update($transactionId, $transactionPayload['update'])) {
                    throw new \RuntimeException('거래 수정에 실패했습니다.');
                }
            } else {
                $transactionId = UuidHelper::generate();
                $insertPayload = $transactionPayload['insert'];
                $insertPayload['id'] = $transactionId;
                $insertPayload['sort_no'] = SequenceHelper::next('ledger_transactions', 'sort_no');

                if (!$this->transactionModel->insert($insertPayload)) {
                    throw new \RuntimeException('거래 저장에 실패했습니다.');
                }
            }

            $this->recreateItems($transactionId, $items, $actor, $timestamp);
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
            throw new \InvalidArgumentException('사업구분을 선택해 주세요.');
        }

        $transactionType = trim((string) ($data['transaction_type'] ?? ''));
        if ($transactionType === '') {
            throw new \InvalidArgumentException('거래유형을 선택해 주세요.');
        }

        $base = [
            'transaction_date' => $transactionDate,
            'business_unit' => $businessUnit,
            'transaction_type' => $transactionType,
            'client_id' => $this->nullable($data['client_id'] ?? null),
            'project_id' => $this->nullable($data['project_id'] ?? null),
            'currency' => trim((string) ($data['currency'] ?? 'KRW')) ?: 'KRW',
            'exchange_rate' => $this->numericOrNull($data['exchange_rate'] ?? null),
            'tax_type' => $this->normalizeTaxType($data['tax_type'] ?? null),
            'supply_amount' => $totals['supply_amount'],
            'vat_amount' => $totals['vat_amount'],
            'total_amount' => $totals['total_amount'],
            'description' => $this->nullable($data['description'] ?? null),
            'status' => $this->normalizeTransactionStatus($data['status'] ?? 'draft'),
            'match_status' => trim((string) ($data['match_status'] ?? 'none')) ?: 'none',
            'note' => $this->nullable($data['note'] ?? null),
            'memo' => $this->nullable($data['memo'] ?? null),
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
                'specification' => $item['specification'],
                'unit_name' => $item['unit_name'],
                'quantity' => $item['quantity'],
                'unit_price' => $item['unit_price'],
                'foreign_unit_price' => $item['foreign_unit_price'],
                'foreign_amount' => $item['foreign_amount'],
                'supply_amount' => $item['supply_amount'],
                'vat_amount' => $item['vat_amount'],
                'total_amount' => $item['total_amount'],
                'tax_type' => $item['tax_type'],
                'description' => $item['description'],
                'created_at' => $timestamp,
                'created_by' => $actor,
                'updated_at' => $timestamp,
                'updated_by' => $actor,
            ];

            if (!$this->transactionItemModel->insert($payload)) {
                throw new \RuntimeException(($index + 1) . '번째 거래 항목 저장에 실패했습니다.');
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
        return in_array($status, ['draft', 'approved', 'rejected'], true) ? $status : 'draft';
    }

    private function calculateTotals(array $items): array
    {
        $supplyAmount = 0.0;
        $vatAmount = 0.0;
        $totalAmount = 0.0;

        foreach ($items as $item) {
            $supplyAmount += (float) ($item['supply_amount'] ?? 0);
            $vatAmount += (float) ($item['vat_amount'] ?? 0);
            $totalAmount += (float) ($item['total_amount'] ?? 0);
        }

        return [
            'supply_amount' => round($supplyAmount, 2),
            'vat_amount' => round($vatAmount, 2),
            'total_amount' => round($totalAmount, 2),
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
                throw new \RuntimeException($upload['message'] ?? '증빙 파일 업로드에 실패했습니다.');
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
                throw new \RuntimeException('증빙 파일 정보를 저장하지 못했습니다.');
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

            $itemName = trim((string) ($item['item_name'] ?? $item['name'] ?? ''));
            if ($itemName === '') {
                continue;
            }

            $quantity = (float) ($this->numericOrNull($item['quantity'] ?? 0) ?? 0);
            $exchangeRate = (float) ($this->numericOrNull($data['exchange_rate'] ?? 0) ?? 0);
            $foreignUnitPrice = $this->numericOrNull($item['foreign_unit_price'] ?? null);
            $foreignAmount = $this->numericOrNull($item['foreign_amount'] ?? null);
            $usesForeignAmount = $exchangeRate > 0 && ($foreignUnitPrice !== null || $foreignAmount !== null);
            if ($usesForeignAmount && $foreignAmount === null) {
                $foreignAmount = round($quantity * (float) $foreignUnitPrice, 2);
            }
            $unitPrice = $usesForeignAmount && $quantity > 0
                ? round(((float) $foreignAmount * $exchangeRate) / $quantity, 2)
                : (float) ($this->numericOrNull($item['unit_price'] ?? 0) ?? 0);
            $taxType = $this->normalizeTaxType($item['tax_type'] ?? ($data['tax_type'] ?? 'TAXABLE')) ?? 'EXEMPT';
            $itemDate = trim((string) ($item['item_date'] ?? ($data['transaction_date'] ?? date('Y-m-d'))));
            if ($itemDate === '') {
                $itemDate = date('Y-m-d');
            }

            $supplyAmount = $usesForeignAmount
                ? round((float) $foreignAmount * $exchangeRate, 2)
                : round($quantity * $unitPrice, 2);
            $vatAmount = $taxType === 'TAXABLE' ? round($supplyAmount * 0.1, 2) : 0.0;
            $totalAmount = round($supplyAmount + $vatAmount, 2);

            $rows[] = [
                'item_date' => $itemDate,
                'item_name' => $itemName,
                'specification' => $this->nullable($item['specification'] ?? null),
                'unit_name' => $this->nullable($item['unit_name'] ?? null),
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'foreign_unit_price' => $usesForeignAmount ? (float) ($foreignUnitPrice ?? 0) : null,
                'foreign_amount' => $usesForeignAmount ? (float) ($foreignAmount ?? 0) : null,
                'supply_amount' => $supplyAmount,
                'vat_amount' => $vatAmount,
                'total_amount' => $totalAmount,
                'tax_type' => $taxType,
                'description' => $this->nullable($item['description'] ?? null),
            ];
        }

        return $rows;
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
