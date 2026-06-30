<?php

namespace App\Services\Ledger;

class TransactionPayloadBuildService
{
    public function __construct(private array $callbacks = [])
    {
    }

    public function shouldRetryTransactionHeaderOnly(string $message, array $payload): bool
    {
        if (empty($payload['items']) || !is_array($payload['items'])) {
            return false;
        }

        return str_contains($message, 'line')
            || str_contains($message, 'item')
            || str_contains($message, 'ledger_transaction_items');
    }

    public function buildTransactionCreatePayload(array $row, string $dataType): array
    {
        if ($this->call('normalizeDataType', $dataType) === 'BANK_TRANSACTION') {
            $row = $this->call('normalizeBankTransactionPayload', $row);
        }

        $context = $this->call('resolveUploadTransactionContext', $row, $dataType);
        if (!empty($context['_direction_error'])) {
            throw new \RuntimeException((string) $context['_direction_error']);
        }

        $supplyRaw = $this->call('amountOrNull', $row['supply_amount'] ?? null);
        $vatRaw = $this->call('amountOrNull', $row['vat_amount'] ?? null);
        $totalRaw = $this->call('amountOrNull', $row['total_amount'] ?? null);
        $supply = (float) ($supplyRaw ?? $totalRaw ?? 0);
        $vat = (float) ($vatRaw ?? 0);
        $total = (float) ($totalRaw ?? ($supply + $vat));
        if (($supplyRaw === null || $supply == 0.0) && $totalRaw !== null && $total != 0.0) {
            $supply = $total - $vat;
        }

        $taxType = abs($vat) > 0 ? 'TAXABLE' : 'EXEMPT';
        $note = trim((string) ($row['transaction_note'] ?? $row['note'] ?? ''));
        $items = $this->transactionLinePayloadsForUpload($row, $supply, $taxType);
        $settlements = $this->transactionSettlementPayloadsForUpload($vat);

        $clientId = $this->call('businessRefIdForStorage', 'CLIENT', $row)
            ?? $this->call('existingClientIdByBusinessNumber', (string) ($context['client_business_number'] ?? $row['client_business_number'] ?? $row['business_number'] ?? ''));

        return [
            'transaction_date' => $this->call('dateValue', $row['transaction_date'] ?? date('Y-m-d')),
            'source_type' => (string) ($context['source_type'] ?? $this->call('normalizeDataType', $dataType)),
            'import_type' => $this->call('normalizeDataType', $dataType),
            'business_unit' => $this->call('businessUnitForUpload', $row, $dataType),
            'transaction_direction' => $this->call('transactionDirectionForStorage', (string) ($context['transaction_direction'] ?? ''), $row, $dataType),
            'transaction_type' => (string) ($context['transaction_type'] ?? 'GENERAL'),
            'client_id' => $clientId,
            'project_id' => $this->call('findProjectId', (string) ($row['project_name'] ?? '')),
            'transaction_description' => trim((string) ($row['transaction_description'] ?? $row['description'] ?? '')),
            'transaction_supply_amount' => $supply,
            'transaction_settlement_amount' => $vat,
            'transaction_final_amount' => $total,
            'status' => 'draft',
            'match_status' => 'none',
            'transaction_note' => $note !== '' ? $note : null,
            'transaction_memo' => trim((string) ($row['transaction_memo'] ?? $row['memo'] ?? '')) ?: null,
            'items' => $items,
            'settlements' => $settlements,
        ];
    }

    public function transactionLinePayloadsForUpload(array $row, float $supply, string $taxType): array
    {
        $itemDate = $this->call('dateValue', $row['item_date'] ?? $row['transaction_date'] ?? date('Y-m-d'));
        $description = trim((string) ($row['item_description'] ?? $row['item_note'] ?? $row['description'] ?? '')) ?: null;

        if (!empty($row['_transaction_lines']) && is_array($row['_transaction_lines'])) {
            return $this->normalizeManualTransactionLines($row['_transaction_lines'], $row, $supply, $taxType, $itemDate, $description);
        }

        $itemName = trim((string) ($row['item_name'] ?? ''));
        $amount = (float) ($this->call('amountOrNull', $row['item_supply_amount'] ?? $row['supply_amount'] ?? $row['amount'] ?? null) ?? $supply);
        if ($itemName === '' && abs($amount) <= 0) {
            return [];
        }

        return [[
            'item_date' => $itemDate,
            'item_name' => $itemName !== '' ? $itemName : (trim((string) ($row['transaction_description'] ?? $row['description'] ?? '')) ?: 'Transaction Item'),
            'item_specification' => trim((string) ($row['item_specification'] ?? $row['item_spec'] ?? '')) ?: null,
            'item_unit_name' => trim((string) ($row['item_unit_name'] ?? $row['unit_name'] ?? '')) ?: null,
            'item_quantity' => (float) ($this->call('amountOrNull', $row['item_quantity'] ?? $row['item_qty'] ?? null) ?? 1),
            'item_unit_price' => (float) ($this->call('amountOrNull', $row['item_unit_price'] ?? $row['item_price'] ?? null) ?? $amount),
            'item_foreign_unit_price' => $this->call('amountOrNull', $row['item_foreign_unit_price'] ?? $row['foreign_unit_price'] ?? null),
            'item_foreign_amount' => $this->call('amountOrNull', $row['item_foreign_amount'] ?? $row['foreign_amount'] ?? null),
            'item_supply_amount' => $amount,
            'item_tax_type' => $taxType,
            'item_description' => $description,
        ]];
    }

    public function transactionSettlementPayloadsForUpload(float $vat): array
    {
        if (abs($vat) <= 0) {
            return [];
        }

        return [[
            'settlement_type' => 'VAT',
            'amount_sign' => 'PLUS',
            'amount' => $vat,
            'settlement_description' => 'VAT',
        ]];
    }

    private function normalizeManualTransactionLines(
        array $rows,
        array $payload,
        float $fallbackAmount,
        string $defaultTaxType,
        string $defaultItemDate,
        ?string $defaultDescription
    ): array {
        $normalized = [];

        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $itemName = trim((string) ($row['item_name'] ?? ''));
            $lineAmount = $this->call('amountOrNull', $row['item_supply_amount'] ?? $row['supply_amount'] ?? $row['amount'] ?? null);
            if ($itemName === '' && $lineAmount === null) {
                continue;
            }

            $amount = (float) ($lineAmount ?? $fallbackAmount);
            $normalized[] = [
                'item_date' => $this->call('dateValue', $row['item_date'] ?? $payload['transaction_date'] ?? $defaultItemDate),
                'item_name' => $itemName !== '' ? $itemName : (trim((string) ($payload['transaction_description'] ?? $payload['description'] ?? '')) ?: 'Transaction Item'),
                'item_specification' => trim((string) ($row['item_specification'] ?? $row['item_spec'] ?? '')) ?: null,
                'item_unit_name' => trim((string) ($row['item_unit_name'] ?? $row['unit_name'] ?? '')) ?: null,
                'item_quantity' => (float) ($this->call('amountOrNull', $row['item_quantity'] ?? $row['item_qty'] ?? null) ?? 1),
                'item_unit_price' => (float) ($this->call('amountOrNull', $row['item_unit_price'] ?? $row['item_price'] ?? null) ?? $amount),
                'item_foreign_unit_price' => $this->call('amountOrNull', $row['item_foreign_unit_price'] ?? $row['foreign_unit_price'] ?? null),
                'item_foreign_amount' => $this->call('amountOrNull', $row['item_foreign_amount'] ?? $row['foreign_amount'] ?? null),
                'item_supply_amount' => (float) ($this->call('amountOrNull', $row['item_supply_amount'] ?? null) ?? $amount),
                'item_tax_type' => trim((string) ($row['item_tax_type'] ?? $defaultTaxType)) ?: $defaultTaxType,
                'item_description' => trim((string) ($row['item_description'] ?? $row['item_note'] ?? $defaultDescription ?? '')) ?: null,
            ];
        }

        return $normalized;
    }

    private function call(string $name, mixed ...$args): mixed
    {
        if (!isset($this->callbacks[$name])) {
            throw new \RuntimeException('Missing callback: ' . $name);
        }

        return ($this->callbacks[$name])(...$args);
    }
}
