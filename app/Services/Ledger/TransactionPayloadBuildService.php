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

        return str_contains($message, '거래 라인')
            || str_contains($message, '거래 항목')
            || str_contains($message, '라인 저장')
            || str_contains($message, 'line')
            || str_contains($message, 'item')
            || str_contains($message, 'ledger_transaction_lines');
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
        $service = (float) ($this->call('amountOrNull', $row['service_amount'] ?? null) ?? 0);
        $taxType = abs($vat) > 0 ? 'TAXABLE' : 'EXEMPT';
        $note = trim((string) ($row['note'] ?? ''));
        $items = $this->transactionLinePayloadsForUpload($row, $supply, $vat, $service, $taxType);

        $clientId = $this->call('businessRefIdForStorage', 'CLIENT', $row)
            ?? $this->call('existingClientIdByBusinessNumber', (string) ($context['client_business_number'] ?? $row['client_business_number'] ?? $row['business_number'] ?? ''));

        return [
            'transaction_date' => $this->call('dateValue', $row['transaction_date'] ?? date('Y-m-d')),
            'business_unit' => $this->call('businessUnitForUpload', $row, $dataType),
            'transaction_type' => (string) ($context['transaction_type'] ?? 'GENERAL'),
            'transaction_direction' => $this->call('transactionDirectionForStorage', (string) ($context['transaction_direction'] ?? ''), $row, $dataType),
            'import_type' => $this->call('normalizeDataType', $dataType),
            'client_id' => $clientId,
            'project_id' => $this->call('findProjectId', (string) ($row['project_name'] ?? '')),
            'description' => trim((string) ($row['description'] ?? '')),
            'supply_amount' => $supply,
            'vat_amount' => $vat,
            'total_amount' => $total,
            'status' => 'draft',
            'match_status' => 'none',
            'note' => $note !== '' ? $note : null,
            'memo' => trim((string) ($row['memo'] ?? '')) ?: null,
            'items' => $items,
        ];
    }

    public function transactionLinePayloadsForUpload(array $row, float $supply, float $vat, float $service, string $taxType): array
    {
        $autoCreate = trim((string) ($row['auto_create_lines_1set'] ?? '1')) !== '0';
        $itemDate = $this->call('dateValue', $row['item_date'] ?? $row['transaction_date'] ?? date('Y-m-d'));
        $description = trim((string) ($row['item_note'] ?? $row['description'] ?? '')) ?: null;

        if (!$autoCreate) {
            $itemName = trim((string) ($row['item_name'] ?? ''));
            $lineAmount = $this->call('amountOrNull', $row['amount'] ?? null);
            if ($itemName === '' && $lineAmount === null) {
                return [];
            }

            $amount = (float) ($lineAmount ?? $supply);
            return [[
                'line_type' => trim((string) ($row['line_type'] ?? 'ITEM')) ?: 'ITEM',
                'item_date' => $itemDate,
                'item_name' => $itemName !== '' ? $itemName : (trim((string) ($row['description'] ?? '')) ?: '거래 항목'),
                'specification' => trim((string) ($row['item_spec'] ?? '')) ?: null,
                'unit_name' => trim((string) ($row['unit_name'] ?? '')) ?: null,
                'quantity' => (float) ($this->call('amountOrNull', $row['item_qty'] ?? null) ?? 1),
                'unit_price' => (float) ($this->call('amountOrNull', $row['item_price'] ?? null) ?? $amount),
                'amount' => $amount,
                'supply_amount' => $amount,
                'vat_amount' => 0.0,
                'total_amount' => $amount,
                'tax_type' => $taxType,
                'description' => $description,
            ]];
        }

        $itemName = trim((string) ($row['item_name'] ?? $row['description'] ?? '')) ?: '공급가액';
        $lines = [];
        if (abs($supply) > 0) {
            $lines[] = $this->oneSetTransactionLine('ITEM', $itemDate, $itemName, $supply, $taxType, $description);
        }
        if (abs($vat) > 0) {
            $lines[] = $this->oneSetTransactionLine('VAT', $itemDate, '부가세', $vat, $taxType, '부가세');
        }
        if (abs($service) > 0) {
            $lines[] = $this->oneSetTransactionLine('SERVICE', $itemDate, '봉사료', $service, $taxType, '봉사료');
        }

        return $lines;
    }

    public function oneSetTransactionLine(string $lineType, string $itemDate, string $itemName, float $amount, string $taxType, ?string $description): array
    {
        return [
            'line_type' => $lineType,
            'item_date' => $itemDate,
            'item_name' => $itemName,
            'specification' => null,
            'unit_name' => '건',
            'quantity' => 1,
            'unit_price' => $amount,
            'amount' => $amount,
            'supply_amount' => $lineType === 'ITEM' ? $amount : 0.0,
            'vat_amount' => $lineType === 'VAT' ? $amount : 0.0,
            'total_amount' => $amount,
            'tax_type' => $taxType,
            'description' => $description,
        ];
    }

    private function call(string $name, mixed ...$args): mixed
    {
        if (!isset($this->callbacks[$name])) {
            throw new \RuntimeException('Missing callback: ' . $name);
        }

        return ($this->callbacks[$name])(...$args);
    }
}
