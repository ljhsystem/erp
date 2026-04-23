<?php

namespace App\Services\Site;

use App\Models\Ledger\TransactionItemModel;
use App\Models\Ledger\TransactionLinkModel;
use App\Models\Ledger\TransactionModel;
use Core\Helpers\ActorHelper;
use Core\Helpers\RefTypeHelper;
use Core\Helpers\UuidHelper;
use PDO;

class TransactionService
{
    private TransactionModel $transactionModel;
    private TransactionItemModel $transactionItemModel;
    private TransactionLinkModel $transactionLinkModel;

    public function __construct(private readonly PDO $pdo)
    {
        $this->transactionModel = new TransactionModel($pdo);
        $this->transactionItemModel = new TransactionItemModel($pdo);
        $this->transactionLinkModel = new TransactionLinkModel($pdo);
    }

    public function getList(array $filters): array
    {
        $allowedKeys = [
            'work_unit',
            'source_type',
            'transaction_type',
            'status',
            'doc_status',
            'match_status',
            'acct_status',
            'project_id',
            'client_id',
            'date_from',
            'date_to',
        ];

        $normalized = [];
        foreach ($allowedKeys as $key) {
            if (!array_key_exists($key, $filters)) {
                continue;
            }

            $value = is_string($filters[$key]) ? trim($filters[$key]) : $filters[$key];
            if ($value === '' || $value === null) {
                continue;
            }

            $normalized[$key] = $value;
        }

        return $this->transactionModel->getList($normalized);
    }

    public function getById(string $id): ?array
    {
        $transaction = $this->transactionModel->getById($id);
        if (!$transaction) {
            return null;
        }

        $transaction['items'] = $this->transactionItemModel->getByTransactionId($id);
        $transaction['links'] = $this->transactionLinkModel->getByTransactionId($id);

        return $transaction;
    }

    public function save(array $data): array
    {
        $actor = ActorHelper::user();
        $timestamp = date('Y-m-d H:i:s');
        $transactionId = trim((string) ($data['id'] ?? ''));
        $items = $this->normalizeItems($data['items'] ?? [], $data);

        if ($items === []) {
            return [
                'success' => false,
                'message' => '嫄곕옒 ?덈ぉ? 理쒖냼 1嫄??댁긽 ?꾩슂?⑸땲??',
            ];
        }

        $totals = $this->calculateTotals($items, $data);
        $transactionPayload = $this->buildTransactionPayload($data, $actor, $timestamp, $totals);

        try {
            $this->pdo->beginTransaction();

            $isUpdate = false;

            if ($transactionId !== '') {
                $existing = $this->transactionModel->getById($transactionId);
                if (!$existing) {
                    throw new \RuntimeException('?섏젙??嫄곕옒 ?뺣낫瑜?李얠쓣 ???놁뒿?덈떎.');
                }

                $isUpdate = true;
                if (!$this->transactionModel->update($transactionId, $transactionPayload['update'])) {
                    throw new \RuntimeException('嫄곕옒 ?섏젙???ㅽ뙣?덉뒿?덈떎.');
                }
            } else {
                $transactionId = UuidHelper::generate();
                $insertPayload = $transactionPayload['insert'];
                $insertPayload['id'] = $transactionId;
                $insertPayload['sort_no'] = null;

                if (!$this->transactionModel->insert($insertPayload)) {
                    throw new \RuntimeException('嫄곕옒 ??μ뿉 ?ㅽ뙣?덉뒿?덈떎.');
                }
            }

            $this->recreateItems($transactionId, $items, $actor, $timestamp);

            $this->pdo->commit();

            return [
                'success' => true,
                'message' => $isUpdate ? '嫄곕옒媛 ?섏젙?섏뿀?듬땲??' : '嫄곕옒媛 ??λ릺?덉뒿?덈떎.',
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

        $sourceType = trim((string) ($data['source_type'] ?? 'MANUAL'));
        $transactionType = trim((string) ($data['transaction_type'] ?? 'ETC'));
        $refType = strtoupper(trim((string) ($data['ref_type'] ?? '')));
        $itemSummary = trim((string) ($data['item_summary'] ?? ''));
        if ($itemSummary === '') {
            $itemSummary = (string) ($totals['item_summary'] ?? '');
        }

        if (!RefTypeHelper::isValid($refType)) {
            throw new \InvalidArgumentException('?좏슚?섏? ?딆? 李몄“?좏삎?낅땲??');
        }

        $base = [
            'work_unit' => trim((string) ($data['work_unit'] ?? 'SITE')) ?: 'SITE',
            'source_type' => $sourceType !== '' ? $sourceType : 'MANUAL',
            'transaction_type' => $transactionType !== '' ? $transactionType : 'ETC',
            'transaction_date' => $transactionDate,
            'project_id' => $this->nullable($data['project_id'] ?? null),
            'client_id' => $this->nullable($data['client_id'] ?? null),
            'bank_account_id' => $this->nullable($data['bank_account_id'] ?? null),
            'card_id' => $this->nullable($data['card_id'] ?? null),
            'employee_id' => $this->nullable($data['employee_id'] ?? null),
            'order_ref' => $this->nullable($data['order_ref'] ?? null),
            'document_type' => $this->nullable($data['document_type'] ?? null),
            'document_no' => $this->nullable($data['document_no'] ?? null),
            'ref_type' => $refType !== '' ? $refType : null,
            'tax_type' => $totals['tax_type'],
            'item_summary' => $this->nullable($itemSummary),
            'description' => $this->nullable($data['description'] ?? null),
            'specification' => $this->nullable($data['specification'] ?? null),
            'unit_name' => $this->nullable($data['unit_name'] ?? null),
            'quantity' => $totals['quantity'],
            'unit_price' => null,
            'supply_amount' => $totals['supply_amount'],
            'vat_amount' => $totals['vat_amount'],
            'total_amount' => $totals['total_amount'],
            'currency' => trim((string) ($data['currency'] ?? 'KRW')) ?: 'KRW',
            'exchange_rate' => $this->numericOrNull($data['exchange_rate'] ?? null),
            'status' => trim((string) ($data['status'] ?? 'draft')) ?: 'draft',
            'doc_status' => trim((string) ($data['doc_status'] ?? 'draft')) ?: 'draft',
            'match_status' => trim((string) ($data['match_status'] ?? 'none')) ?: 'none',
            'acct_status' => trim((string) ($data['acct_status'] ?? 'unposted')) ?: 'unposted',
            'is_active' => isset($data['is_active']) ? (int) $data['is_active'] : 1,
            'evidence_file_path' => $this->nullable($data['evidence_file_path'] ?? null),
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
                throw new \RuntimeException('湲곗〈 嫄곕옒 ?덈ぉ ?뺣━???ㅽ뙣?덉뒿?덈떎.');
            }
        }

        foreach ($items as $index => $item) {
            $payload = [
                'id' => UuidHelper::generate(),
                'sort_no' => null,
                'transaction_id' => $transactionId,
                'line_no' => $index + 1,
                'item_name' => $item['item_name'],
                'specification' => $item['specification'],
                'unit_name' => $item['unit_name'],
                'quantity' => $item['quantity'],
                'unit_price' => $item['unit_price'],
                'supply_amount' => $item['supply_amount'],
                'vat_amount' => $item['vat_amount'],
                'total_amount' => $item['total_amount'],
                'tax_type' => $item['tax_type'],
                'description' => $item['description'],
                'is_active' => $item['is_active'],
                'note' => $item['note'],
                'memo' => $item['memo'],
                'created_at' => $timestamp,
                'created_by' => $actor,
                'updated_at' => $timestamp,
                'updated_by' => $actor,
            ];

            if (!$this->transactionItemModel->insert($payload)) {
                throw new \RuntimeException(($index + 1) . '踰덉㎏ 嫄곕옒 ?덈ぉ ??μ뿉 ?ㅽ뙣?덉뒿?덈떎.');
            }
        }
    }

    private function calculateTotals(array $items, array $data): array
    {
        $quantity = 0.0;
        $supplyAmount = 0.0;
        $taxableBase = 0.0;
        $useItemTax = !empty($data['use_item_tax']);
        $headerTaxType = strtoupper(trim((string) ($data['tax_type'] ?? 'TAXABLE')));
        if ($headerTaxType !== 'TAXABLE') {
            $headerTaxType = 'EXEMPT';
        }

        foreach ($items as $item) {
            $quantity += (float) ($item['quantity'] ?? 0);
            $lineTotal = (float) ($item['total_amount'] ?? 0);
            $supplyAmount += $lineTotal;

            if ($useItemTax) {
                if (($item['tax_type'] ?? 'EXEMPT') === 'TAXABLE') {
                    $taxableBase += $lineTotal;
                }
            }
        }

        if (!$useItemTax && $headerTaxType === 'TAXABLE') {
            $taxableBase = $supplyAmount;
        }

        $vatAmount = $this->numericOrNull($data['vat_amount'] ?? null);
        if ($vatAmount === null) {
            $vatAmount = round($taxableBase * 0.1, 2);
        }

        $totalAmount = round($supplyAmount + (float) $vatAmount, 2);
        $hasTaxableItem = $useItemTax
            ? $taxableBase > 0
            : $headerTaxType === 'TAXABLE';

        return [
            'quantity' => $quantity > 0 ? $quantity : null,
            'supply_amount' => round($supplyAmount, 2),
            'vat_amount' => round((float) $vatAmount, 2),
            'total_amount' => $totalAmount,
            'tax_type' => $hasTaxableItem ? 'TAXABLE' : 'EXEMPT',
            'item_summary' => $items[0]['item_name'] ?? null,
        ];
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
            $unitPrice = (float) ($this->numericOrNull($item['unit_price'] ?? 0) ?? 0);
            $taxType = strtoupper(trim((string) ($item['tax_type'] ?? ($data['tax_type'] ?? 'TAXABLE'))));
            if ($taxType !== 'TAXABLE') {
                $taxType = 'EXEMPT';
            }

            $totalAmount = round($quantity * $unitPrice, 2);

            $rows[] = [
                'item_name' => $itemName,
                'specification' => $this->nullable($item['specification'] ?? null),
                'unit_name' => $this->nullable($item['unit_name'] ?? null),
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'supply_amount' => $totalAmount,
                'vat_amount' => 0.0,
                'total_amount' => $totalAmount,
                'tax_type' => $taxType,
                'description' => $this->nullable($item['description'] ?? null),
                'is_active' => isset($item['is_active']) ? (int) $item['is_active'] : 1,
                'note' => $this->nullable($item['note'] ?? null),
                'memo' => $this->nullable($item['memo'] ?? null),
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


